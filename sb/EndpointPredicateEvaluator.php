<?php

declare(strict_types=1);

namespace Switchboard\Runtime;

final class EndpointPredicateEvaluator
{
    /**
     * @param array<string, mixed> $endpoint
     * @param array<int, array<string, mixed>> $predicates
     * @return array{passed: bool, failures: list<string>}
     */
    public static function evaluate(array $endpoint, NormalizedRequest $request, array $predicates): array
    {
        $failures = [];
        foreach ($predicates as $predicate) {
            $result = self::evaluateOne($endpoint, $request, $predicate);
            if (!$result['passed']) {
                $failures[] = $result['message'];
            }
        }

        return [
            'passed' => $failures === [],
            'failures' => $failures,
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     * @param array<string, mixed> $predicate
     * @return array{passed: bool, message: string}
     */
    private static function evaluateOne(array $endpoint, NormalizedRequest $request, array $predicate): array
    {
        $source = (string)($predicate['source'] ?? '');
        $name = (string)($predicate['name'] ?? '');
        $op = (string)($predicate['op'] ?? 'present');
        $value = $predicate['value'] ?? null;
        $valueType = $predicate['value_type'] ?? null;
        $actual = self::valueFor($endpoint, $request, $source, $name);
        $exists = $actual !== null && $actual !== '';

        $passed = match ($op) {
            'present' => $exists,
            'absent' => !$exists,
            'equals' => $exists && self::stringValue($actual) === self::stringValue($value),
            'in' => $exists && in_array(self::stringValue($actual), self::listValue($value), true),
            'regex' => $exists && self::matchesRegex(self::stringValue($value), self::stringValue($actual)),
            'type' => $exists && is_string($valueType) && self::matchesType(self::stringValue($actual), $valueType),
            default => false,
        };

        return [
            'passed' => $passed,
            'message' => $passed ? '' : "{$source}.{$name} failed {$op}",
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return mixed
     */
    private static function valueFor(array $endpoint, NormalizedRequest $request, string $source, string $name): mixed
    {
        return match ($source) {
            'path' => ($endpoint['path_params'][$name] ?? $request->getPathParams()[$name] ?? null),
            'query' => self::arrayValue($request->getQuery(), $name),
            'form' => self::arrayValue($request->getForm(), $name),
            'json' => self::jsonPathValue($request->getJson(), $name),
            'header' => self::arrayValue($request->getHeaders(), strtolower($name)),
            'cookie' => self::arrayValue($request->getCookies(), $name),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function arrayValue(array $values, string $name): mixed
    {
        if (!array_key_exists($name, $values)) {
            return null;
        }
        $value = $values[$name];
        return is_array($value) ? ($value[0] ?? null) : $value;
    }

    private static function jsonPathValue(mixed $json, string $path): mixed
    {
        if ($path === '' || !is_array($json)) {
            return null;
        }
        $current = $json;
        foreach (explode('.', $path) as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }

    private static function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value) || $value === null) {
            return (string)$value;
        }
        try {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * @return list<string>
     */
    private static function listValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map(fn($v) => self::stringValue($v), $value));
        }
        return array_values(array_filter(array_map('trim', explode(',', self::stringValue($value))), fn($v) => $v !== ''));
    }

    private static function matchesRegex(string $pattern, string $value): bool
    {
        if ($pattern === '') {
            return false;
        }
        $regex = str_starts_with($pattern, '/') ? $pattern : '/' . str_replace('/', '\/', $pattern) . '/';
        set_error_handler(static fn(): bool => true);
        try {
            return preg_match($regex, $value) === 1;
        } finally {
            restore_error_handler();
        }
    }

    public static function matchesType(string $value, string $type): bool
    {
        return match ($type) {
            'string' => true,
            'number' => is_numeric($value) && $value !== '',
            'integer' => (string)(int)$value === trim($value),
            'boolean' => $value === 'true' || $value === 'false',
            'date' => self::isDate($value),
            'datetime' => strtotime($value) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1,
            default => false,
        };
    }

    private static function isDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }
        return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
    }
}
