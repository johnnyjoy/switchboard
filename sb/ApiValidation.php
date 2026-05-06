<?php

declare(strict_types=1);

namespace Switchboard\Runtime;

final class ApiValidation
{
    public static function appErrors(array $app, array $config, ?string $currentId = null): array
    {
        $errors = [];
        $name = trim((string)($app['name'] ?? ''));
        $slug = trim((string)($app['slug'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'name is required';
        }

        if ($slug === '') {
            $errors['slug'] = 'slug is required';
        } elseif (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            $errors['slug'] = 'slug must use lowercase letters, numbers, and single hyphens';
        } else {
            foreach ($config['apps'] ?? [] as $existing) {
                if (($existing['id'] ?? null) === $currentId) {
                    continue;
                }
                if (($existing['slug'] ?? null) === $slug) {
                    $errors['slug'] = 'slug must be unique';
                    break;
                }
            }
        }

        return $errors;
    }

    public static function endpointErrors(array $endpoint, array $config, ?string $currentId = null): array
    {
        $errors = [];
        $appId = trim((string)($endpoint['app_id'] ?? ''));
        $name = trim((string)($endpoint['name'] ?? ''));
        $rawPath = (string)($endpoint['path'] ?? '');
        $path = self::normalizeRoutePath($rawPath);
        $methods = self::normalizeMethods($endpoint['methods'] ?? ($endpoint['method'] ?? []));
        $handlerClass = trim((string)($endpoint['handler_class'] ?? ''));
        $handlerMethod = trim((string)($endpoint['handler_method'] ?? 'handle'));
        $host = self::normalizeRouteHost($endpoint['host'] ?? null);

        if ($appId === '') {
            $errors['app_id'] = 'app_id is required';
        } elseif (!self::appExists($config, $appId)) {
            $errors['app_id'] = 'app_id must reference an existing app';
        }

        if ($name === '') {
            $errors['name'] = 'name is required';
        }

        if ($rawPath === '') {
            $errors['path'] = 'path is required';
        } elseif ($rawPath[0] !== '/') {
            $errors['path'] = 'path must start with /';
        } elseif (str_contains($rawPath, '://')) {
            $errors['path'] = 'path must be relative to the app route prefix';
        } elseif (!self::validPathPattern($path)) {
            $errors['path'] = 'path parameters must use {name} segments with identifier names';
        }

        if ($methods === []) {
            $errors['methods'] = 'methods must include at least one supported HTTP method';
        } elseif (array_diff($methods, self::allowedHttpMethods()) !== []) {
            $errors['methods'] = 'methods may only include supported HTTP methods';
        }

        if ($handlerClass === '') {
            $errors['handler_class'] = 'handler_class is required';
        } elseif (!self::validClassName($handlerClass)) {
            $errors['handler_class'] = 'handler_class must be a valid PHP class name';
        }

        if ($handlerMethod === '') {
            $errors['handler_method'] = 'handler_method is required';
        } elseif (!self::validMethodName($handlerMethod)) {
            $errors['handler_method'] = 'handler_method must be a valid PHP method name';
        }

        if (!isset($errors['app_id'], $errors['path'], $errors['methods'])) {
            foreach ($config['endpoints'] ?? [] as $existing) {
                if (($existing['id'] ?? null) === $currentId) {
                    continue;
                }
                if (($existing['app_id'] ?? null) !== $appId) {
                    continue;
                }
                if (self::normalizeRouteHost($existing['host'] ?? null) !== $host) {
                    continue;
                }
                if (self::normalizeRoutePath($existing['path'] ?? '') !== $path) {
                    continue;
                }
                $existingMethods = self::normalizeMethods($existing['methods'] ?? ($existing['method'] ?? []));
                if (array_intersect($existingMethods, $methods) === []) {
                    continue;
                }
                $errors['route'] = 'endpoint route must be unique for app, host, path, and overlapping methods';
                break;
            }
        }

        return $errors;
    }

    public static function defaultSlugFromName(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    public static function conditionErrors(array $condition, array $config, ?string $currentId = null): array
    {
        $errors = [];
        $endpointId = trim((string)($condition['endpoint_id'] ?? ''));
        $kind = (string)($condition['kind'] ?? $condition['source'] ?? '');
        $key = trim((string)($condition['key'] ?? ''));
        $value = trim((string)($condition['value'] ?? ''));
        $op = $condition['op'] ?? $condition['operator'] ?? null;
        $op = $op === null || $op === '' ? null : self::normalizeOp((string)$op);

        if ($endpointId === '') {
            $errors['endpoint_id'] = 'endpoint_id is required';
        } elseif (!self::endpointExists($config, $endpointId)) {
            $errors['endpoint_id'] = 'endpoint_id must reference an existing endpoint';
        }

        if (!in_array($kind, self::allowedConditionKinds(), true)) {
            $errors['kind'] = 'kind must be a supported condition source';
        }

        if (in_array($kind, ['query', 'header', 'cookie'], true)) {
            if ($key === '') {
                $errors['key'] = 'key is required for query, header, and cookie conditions';
            }
            if ($op === null) {
                $errors['op'] = 'op is required for query, header, and cookie conditions';
            } elseif (!in_array($op, self::allowedKeyedConditionOperators(), true)) {
                $errors['op'] = 'op must be a supported condition operator';
            }
            if (!in_array($op, ['present', 'absent'], true) && $value === '') {
                $errors['value'] = 'value is required for this condition operator';
            }
            if ($op === 'regex' && $value !== '' && !self::hasValidRegex($value, false)) {
                $errors['value'] = 'value must be a valid PHP regex';
            }
        } elseif (in_array($kind, ['ip_allow', 'ip_deny', 'user_agent'], true)) {
            if ($value === '') {
                $errors['value'] = 'value is required for this condition kind';
            }
            if ($op !== null && !in_array($op, self::conditionOps($kind), true)) {
                $errors['op'] = 'op must be supported for this condition kind';
            }
            if ($kind === 'user_agent' && $op === 'regex' && $value !== '' && !self::hasValidRegex($value, false)) {
                $errors['value'] = 'value must be a valid PHP regex';
            }
        }

        return $errors;
    }

    public static function normalizeOp(string $operator): string
    {
        return $operator === 'eq' ? 'equals' : $operator;
    }

    public static function predicateErrors(array $predicate, array $config, ?string $currentId = null): array
    {
        $errors = [];
        $endpointId = trim((string)($predicate['endpoint_id'] ?? ''));
        $source = (string)($predicate['source'] ?? '');
        $name = trim((string)($predicate['name'] ?? ''));
        $op = (string)($predicate['op'] ?? '');
        $value = $predicate['value'] ?? null;
        $valueType = $predicate['value_type'] ?? null;

        if ($endpointId === '') {
            $errors['endpoint_id'] = 'endpoint_id is required';
        } elseif (!self::endpointExists($config, $endpointId)) {
            $errors['endpoint_id'] = 'endpoint_id must reference an existing endpoint';
        }
        if (!in_array($source, self::allowedPredicateSources(), true)) {
            $errors['source'] = 'source must be path, query, form, json, header, or cookie';
        }
        if ($name === '') {
            $errors['name'] = 'name is required';
        }
        if (!in_array($op, self::allowedPredicateOperators(), true)) {
            $errors['op'] = 'op must be present, absent, equals, in, regex, or type';
        }
        if (in_array($op, ['equals', 'in', 'regex'], true) && ($value === null || $value === '' || $value === [])) {
            $errors['value'] = 'value is required for this predicate operator';
        }
        if ($op === 'regex' && is_string($value) && $value !== '' && !self::hasValidRegex($value, true)) {
            $errors['value'] = 'value must be a valid regex';
        }
        if ($op === 'type' && (!is_string($valueType) || !in_array($valueType, self::allowedPredicateTypes(), true))) {
            $errors['value_type'] = 'value_type must be a supported predicate type';
        }
        if ($source === 'json' && !self::validJsonPath($name)) {
            $errors['name'] = 'json predicate name must use dot-separated identifiers';
        }

        return $errors;
    }

    public static function normalizeRouteHost($host): ?string
    {
        if ($host === null) {
            return null;
        }
        $normalized = strtolower(trim((string)$host));
        return $normalized === '' ? null : $normalized;
    }

    public static function normalizeRoutePath(mixed $path): string
    {
        $path = (string)$path;
        if ($path === '') {
            return '';
        }

        $normalized = '/' . trim($path, '/');
        return $normalized === '' ? '/' : $normalized;
    }

    private static function allowedHttpMethods(): array
    {
        return ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
    }

    private static function validPathPattern(string $path): bool
    {
        foreach (array_values(array_filter(explode('/', trim($path, '/')), fn($p) => $p !== '')) as $segment) {
            $hasOpen = str_contains($segment, '{');
            $hasClose = str_contains($segment, '}');
            if (!$hasOpen && !$hasClose) {
                continue;
            }
            if (preg_match('/^\{[A-Za-z_][A-Za-z0-9_]*(?::[A-Za-z_][A-Za-z0-9_]*)?\}$/', $segment) !== 1) {
                return false;
            }
        }
        return true;
    }

    private static function validClassName(string $className): bool
    {
        return preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $className) === 1;
    }

    private static function validMethodName(string $methodName): bool
    {
        return $methodName === '__invoke' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName) === 1;
    }

    public static function normalizeMethods(mixed $methods): array
    {
        if (is_string($methods)) {
            $methods = [$methods];
        }
        if (!is_array($methods)) {
            return [];
        }
        $normalized = [];
        foreach ($methods as $method) {
            $method = strtoupper(trim((string)$method));
            if ($method !== '') {
                $normalized[] = $method;
            }
        }
        return array_values(array_unique($normalized));
    }

    private static function appExists(array $config, string $appId): bool
    {
        foreach ($config['apps'] ?? [] as $app) {
            if (($app['id'] ?? null) === $appId) {
                return true;
            }
        }
        return false;
    }

    private static function endpointExists(array $config, string $endpointId): bool
    {
        foreach ($config['endpoints'] ?? [] as $endpoint) {
            if (($endpoint['id'] ?? null) === $endpointId) {
                return true;
            }
        }
        return false;
    }

    private static function allowedConditionKinds(): array
    {
        return ['query', 'header', 'cookie', 'ip_allow', 'ip_deny', 'user_agent'];
    }

    private static function allowedKeyedConditionOperators(): array
    {
        return ['equals', 'contains', 'regex', 'present', 'absent', 'in', 'not_in'];
    }

    private static function conditionOps(string $kind): array
    {
        if ($kind === 'user_agent') {
            return ['equals', 'contains', 'regex'];
        }
        return ['equals', 'in', 'not_in'];
    }

    private static function allowedPredicateSources(): array
    {
        return ['path', 'query', 'form', 'json', 'header', 'cookie'];
    }

    private static function allowedPredicateOperators(): array
    {
        return ['present', 'absent', 'equals', 'in', 'regex', 'type'];
    }

    private static function allowedPredicateTypes(): array
    {
        return ['string', 'integer', 'number', 'boolean', 'date', 'datetime', 'uuid'];
    }

    private static function validJsonPath(string $path): bool
    {
        foreach (explode('.', $path) as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                return false;
            }
        }
        return true;
    }

    private static function hasValidRegex(string $pattern, bool $allowBarePattern): bool
    {
        $regex = $allowBarePattern && !str_starts_with($pattern, '/')
            ? '/' . str_replace('/', '\/', $pattern) . '/'
            : $pattern;

        set_error_handler(static fn(): bool => true);
        try {
            return preg_match($regex, '') !== false;
        } finally {
            restore_error_handler();
        }
    }
}
