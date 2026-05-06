<?php

declare(strict_types=1);

/**
 * Evaluate endpoint route conditions against a request.
 *
 * Used by Matcher after host/path/method match. All conditions in the
 * endpoint config must pass (AND). See docs/endpoint-schema.md § conditions.
 *
 * @link docs/endpoint-schema.md Route conditions
 */

namespace Switchboard\Runtime;

final class RouteConditionEvaluator
{
    /**
     * Whether the request satisfies all of the endpoint's route conditions.
     *
     * @param array<string, mixed> $conditions Endpoint 'conditions' object (may be empty).
     * @param SwitchboardRequest   $request   Normalized request.
     */
    public static function evaluate(array $conditions, SwitchboardRequest $request): bool
    {
        if (empty($conditions)) {
            return true;
        }

        if (isset($conditions['query']) && !self::evaluateKeyValues($conditions['query'], $request->getQuery(), true)) {
            return false;
        }
        if (isset($conditions['headers']) && !self::evaluateKeyValues($conditions['headers'], $request->getHeaders(), true)) {
            return false;
        }
        if (isset($conditions['cookies']) && !self::evaluateKeyValues($conditions['cookies'], $request->getCookies(), false)) {
            return false;
        }

        $clientIp = self::clientIp($request);
        if (isset($conditions['ip_allow']) && is_array($conditions['ip_allow'])) {
            if ($clientIp === null || !self::ipMatchesAny($clientIp, $conditions['ip_allow'])) {
                return false;
            }
        }
        if (isset($conditions['ip_deny']) && is_array($conditions['ip_deny'])) {
            if ($clientIp !== null && self::ipMatchesAny($clientIp, $conditions['ip_deny'])) {
                return false;
            }
        }

        if (isset($conditions['user_agent'])) {
            $ua = $request->getHeaders()['user-agent'] ?? '';
            if (is_string($conditions['user_agent'])) {
                if (strpos($ua, $conditions['user_agent']) === false) {
                    return false;
                }
            } elseif (is_array($conditions['user_agent']) && isset($conditions['user_agent']['pattern'])) {
                $pattern = $conditions['user_agent']['pattern'];
                if (!is_string($pattern) || !self::regexMatches($pattern, $ua)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int, array{name: string, value?: string, op?: string}> $rules
     * @param array<string, string|array<string>>             $source Query/headers (values may be string or array)
     * @param bool                                            $lowercaseKeys Whether to compare keys case-insensitively (headers)
     */
    private static function evaluateKeyValues(array $rules, array $source, bool $lowercaseKeys): bool
    {
        $source = $lowercaseKeys ? array_change_key_case($source, CASE_LOWER) : $source;
        foreach ($rules as $rule) {
            if (!is_array($rule) || empty($rule['name'])) {
                continue;
            }
            $name = $rule['name'];
            $key = $lowercaseKeys ? strtolower($name) : $name;
            $wantedValue = isset($rule['value']) ? (string) $rule['value'] : null;
            $op = (string)($rule['op'] ?? 'equals');
            if ($op === 'eq') {
                $op = 'equals';
            }

            $present = array_key_exists($key, $source);
            if ($op === 'absent') {
                if ($present) {
                    return false;
                }
                continue;
            }
            if (!$present) {
                return false;
            }
            if ($op === 'present') {
                continue;
            }
            if ($wantedValue === null) {
                return false;
            }

            $actual = $source[$key];
            $actualStr = is_array($actual) ? (string) reset($actual) : (string) $actual;
            if (!self::valueMatches($actualStr, $wantedValue, $op)) {
                return false;
            }
        }
        return true;
    }

    private static function valueMatches(string $actual, string $expected, string $op): bool
    {
        return match ($op) {
            'equals' => $actual === $expected,
            'contains' => strpos($actual, $expected) !== false,
            'regex' => self::regexMatches($expected, $actual),
            'in' => in_array($actual, self::splitList($expected), true),
            'not_in' => !in_array($actual, self::splitList($expected), true),
            default => false,
        };
    }

    private static function regexMatches(string $pattern, string $value): bool
    {
        set_error_handler(static fn(): bool => true);
        try {
            return preg_match($pattern, $value) === 1;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array<int, string>
     */
    private static function splitList(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(
                fn($v) => is_scalar($v) ? trim((string) $v) : '',
                $decoded
            ), fn($v) => $v !== ''));
        }

        return array_values(array_filter(array_map(
            fn($v) => trim((string) $v),
            explode(',', $value)
        ), fn($v) => $v !== ''));
    }

    private static function clientIp(SwitchboardRequest $request): ?string
    {
        $forwarded = $request->getForwardedFor();
        if ($forwarded !== null && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));
            $first = $parts[0] ?? '';
            if ($first !== '') {
                return $first;
            }
        }
        return $request->getRemoteAddr();
    }

    /**
     * @param string        $ip   Client IP (IPv4 or IPv6).
     * @param array<string> $list List of CIDR or literal IPs.
     */
    private static function ipMatchesAny(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }
            if (strpos($entry, '/') !== false) {
                if (self::ipInCidr($ip, $entry)) {
                    return true;
                }
            } else {
                if ($ip === $entry) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0] ?? '';
        $ipPacked = inet_pton($ip);
        $subnetPacked = inet_pton($subnet);
        if ($subnet === '' || $ipPacked === false || $subnetPacked === false || strlen($ipPacked) !== strlen($subnetPacked)) {
            return false;
        }

        $maxBits = strlen($ipPacked) * 8;
        $bits = isset($parts[1]) ? (int) $parts[1] : $maxBits;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        if ($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($subnetPacked, 0, $fullBytes)) {
            return false;
        }

        $remainingBits = $bits % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipPacked[$fullBytes]) & $mask) === (ord($subnetPacked[$fullBytes]) & $mask);
    }
}
