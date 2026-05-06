<?php

declare(strict_types=1);

/**
 * Match incoming request to a single endpoint.
 *
 * Matching follows the current endpoint model: app scope, methods, path and
 * path params, host, then endpoint predicates.
 * Conflict resolution is deterministic by endpoint id. Supports path parsing
 * for /sb/<app>/<version>/ and scopes endpoints by app when prefix is present.
 *
 * @link docs/endpoint-schema.md Endpoint matching and precedence
 */

namespace Switchboard\Runtime;

final class Matcher
{
    /**
     * Find the single endpoint that matches host, path, methods, and predicates.
     *
     * @param SwitchboardRequest                $request       Normalized request (used for host, path, method and for condition evaluation).
     * @param array<int, array<string, mixed>>  $endpoints     Active endpoints.
     * @param array<int, array<string, mixed>>  $apps          Active apps (id, slug, ...).
     * @param array{app_slug: string, version: string, path: string}|null $prefixContext Parsed /sb/app/version/ context or null.
     * @return array{id: string, app_id: string, host: ?string, path: string, methods: array, handler_class: string, handler_method: string, ...}|null
     */
    public static function match(
        SwitchboardRequest $request,
        array $endpoints,
        array $apps,
        ?array $prefixContext
    ): ?array {
        $host = strtolower($request->getHost());
        $method = strtoupper($request->getMethod());
        $path = PathParser::normalizePath($request->getPath());

        if ($prefixContext !== null) {
            $appSlug = $prefixContext['app_slug'];
            $pathToMatch = $prefixContext['path'];
            $appIds = [];
            foreach ($apps as $app) {
                if ($app['slug'] === $appSlug) {
                    $appIds[$app['id']] = true;
                    break;
                }
            }
            $endpoints = array_values(array_filter($endpoints, fn($e) => isset($appIds[$e['app_id']])));
        } else {
            $pathToMatch = $path;
        }

        if (empty($endpoints)) {
            return null;
        }

        // 1. Method: a request may match any method listed on the endpoint.
        $byMethod = array_values(array_filter($endpoints, fn($e) => in_array($method, self::methodsForEndpoint($e), true)));
        if (empty($byMethod)) {
            return null;
        }

        // 2. Path: exact match or v1 {param} segment pattern. When prefix is set, pathToMatch is the inner path only.
        $byPath = [];
        foreach ($byMethod as $endpoint) {
            $pathMatch = self::matchPathPattern((string)$endpoint['path'], $pathToMatch);
            if ($pathMatch === null) {
                continue;
            }
            if (!empty($pathMatch)) {
                $endpoint['path_params'] = $pathMatch;
            }
            $endpoint['path_param_types'] = self::pathParamTypes((string)$endpoint['path']);
            $byPath[] = $endpoint;
        }
        if (empty($byPath)) {
            return null;
        }

        // 3. Host: prefer exact host, then any-host (empty/null). SW-003 order: host first.
        $withExactHost = array_values(array_filter($byPath, fn($e) => !empty($e['host']) && strtolower((string)$e['host']) === $host));
        $withAnyHost = array_values(array_filter($byPath, fn($e) => empty($e['host'])));
        $hostCandidates = !empty($withExactHost) ? $withExactHost : $withAnyHost;

        if (empty($hostCandidates)) {
            return null;
        }

        // 4. Endpoint predicates: filter by path/query/form/json/header/cookie rules.
        $conditionsCandidates = array_values(array_filter(
            $hostCandidates,
            fn($e) => EndpointPredicateEvaluator::evaluate($e, $request, self::predicatesForEndpoint($e))['passed']
        ));
        if (empty($conditionsCandidates)) {
            return null;
        }

        // 5. Conflict resolution: prefer literal routes, then fewer captured params, then deterministic endpoint id.
        usort($conditionsCandidates, function ($a, $b) {
            $aParamCount = count($a['path_params'] ?? []);
            $bParamCount = count($b['path_params'] ?? []);
            if ($aParamCount !== $bParamCount) {
                return $aParamCount <=> $bParamCount;
            }
            return strcmp($a['id'], $b['id']);
        });
        return $conditionsCandidates[0];
    }

    /**
     * @return array<string, string>|null Captured path params, empty array for literal match, or null for no match.
     */
    private static function matchPathPattern(string $pattern, string $path): ?array
    {
        $pattern = PathParser::normalizePath($pattern);
        $path = PathParser::normalizePath($path);
        if ($pattern === $path) {
            return [];
        }
        if (strpos($pattern, '{') === false) {
            return null;
        }

        $patternParts = array_values(array_filter(explode('/', trim($pattern, '/')), fn($p) => $p !== ''));
        $pathParts = array_values(array_filter(explode('/', trim($path, '/')), fn($p) => $p !== ''));
        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $index => $part) {
            $pathPart = $pathParts[$index] ?? '';
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::([A-Za-z_][A-Za-z0-9_]*))?\}$/', $part, $matches) === 1) {
                $params[$matches[1]] = rawurldecode($pathPart);
                continue;
            }
            if ($part !== $pathPart) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return list<string>
     */
    private static function methodsForEndpoint(array $endpoint): array
    {
        $methods = $endpoint['methods'] ?? null;
        if (is_array($methods)) {
            return array_values(array_unique(array_map(fn($m) => strtoupper((string)$m), $methods)));
        }
        return [strtoupper((string)($endpoint['method'] ?? 'GET'))];
    }

    /**
     * @return array<string, string>
     */
    private static function pathParamTypes(string $pattern): array
    {
        $types = [];
        foreach (array_values(array_filter(explode('/', trim($pattern, '/')), fn($p) => $p !== '')) as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*):([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $types[$matches[1]] = $matches[2];
            }
        }
        return $types;
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return array<int, array<string, mixed>>
     */
    private static function predicatesForEndpoint(array $endpoint): array
    {
        $predicates = is_array($endpoint['predicates'] ?? null) ? $endpoint['predicates'] : [];
        foreach (($endpoint['path_param_types'] ?? []) as $name => $type) {
            $predicates[] = [
                'source' => 'path',
                'name' => $name,
                'op' => 'type',
                'value_type' => $type,
            ];
        }
        return $predicates;
    }
}
