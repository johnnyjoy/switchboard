<?php

declare(strict_types=1);

/**
 * Load endpoint registry from JSON config.
 *
 * Only apps and endpoints with enabled=true are considered active.
 * Aligns with docs/endpoint-schema.md and runtime/config.d.ts.
 *
 * @link docs/endpoint-schema.md Endpoint and app schema
 */

namespace Switchboard\Runtime;

require_once __DIR__ . '/RegistryStore.php';
require_once __DIR__ . '/EndpointPredicateEvaluator.php';

final class Registry
{
    /**
     * Active apps (enabled=true).
     *
     * @var array<int, array{id: string, slug: string, name: string, enabled: bool, ...}>
     */
    public array $apps = [];

    /**
     * Active endpoints (enabled and belonging to an active app).
     *
     * @var array<int, array{id: string, app_id: string, host: ?string, path: string, methods: array, handler_class: string, handler_method: string, enabled: bool, ...}>
     */
    public array $endpoints = [];

    /**
     * Endpoint predicates per endpoint_id.
     *
     * @var array<int, array{endpoint_id: string, in: string, name: string, required: bool, type: ?string, ...}>
     */
    public array $endpoint_predicates = [];

    /**
     * Endpoint validations (content-type, schema) per endpoint_id.
     *
     * @var array<int, array{endpoint_id: string, content_type: string, schema: mixed, ...}>
     */
    public array $endpoint_validations = [];

    /**
     * Load registry from a JSON config file.
     *
     * @param string $configPath Path to endpoints.json (or equivalent).
     * @return self Loaded registry with active apps and endpoints only.
     * @throws \RuntimeException If config file is not found, not readable, or invalid JSON.
     */
    public static function load(string $configPath): self
    {
        $fullPath = realpath($configPath);
        if ($fullPath === false || !is_readable($fullPath)) {
            throw new \RuntimeException("Config not found or not readable: {$configPath}");
        }

        try {
            return self::fromData(RegistryStore::load($fullPath));
        } catch (\Throwable $error) {
            $backupPath = RegistryStore::backupPath($fullPath);
            if (!is_readable($backupPath)) {
                throw new \RuntimeException("Failed to load config: {$configPath}", 0, $error);
            }

            return self::fromData(RegistryStore::load($backupPath));
        }
    }

    /**
     * @param array{apps: array, endpoints: array, endpoint_predicates?: array, endpoint_validations?: array} $data
     */
    private static function fromData(array $data): self
    {
        $activeAppIds = [];
        foreach ($data['apps'] as $app) {
            if (!empty($app['enabled'])) {
                $activeAppIds[$app['id']] = true;
            }
        }

        $registry = new self();
        $registry->apps = array_values(array_filter($data['apps'], fn($a) => !empty($a['enabled'])));

        $registry->endpoints = array_values(array_filter(
            $data['endpoints'] ?? [],
            fn($e) => !empty($e['enabled']) && isset($activeAppIds[$e['app_id']])
        ));

        $activeEndpointIds = array_column($registry->endpoints, 'id');
        $activeEndpointIds = array_fill_keys($activeEndpointIds, true);

        $registry->endpoint_predicates = array_values(array_filter(
            $data['endpoint_predicates'] ?? [],
            fn($p) => isset($activeEndpointIds[$p['endpoint_id']])
        ));

        $registry->endpoint_validations = array_values(array_filter(
            $data['endpoint_validations'] ?? [],
            fn($v) => isset($activeEndpointIds[$v['endpoint_id']])
        ));

        $predicatesByEndpoint = [];
        foreach ($registry->endpoint_predicates as $row) {
            $eid = $row['endpoint_id'] ?? null;
            if (!isset($predicatesByEndpoint[$eid])) {
                $predicatesByEndpoint[$eid] = [];
            }
            $predicatesByEndpoint[$eid][] = $row;
        }

        foreach ($registry->endpoints as $i => $endpoint) {
            $registry->endpoints[$i]['methods'] = self::methodsForEndpoint($endpoint);
            $registry->endpoints[$i]['predicates'] = $predicatesByEndpoint[$endpoint['id']] ?? [];
        }

        return $registry;
    }

    private static function methodsForEndpoint(array $endpoint): array
    {
        if (is_array($endpoint['methods'] ?? null)) {
            return array_values(array_unique(array_map(fn($m) => strtoupper((string)$m), $endpoint['methods'])));
        }
        return [strtoupper((string)($endpoint['method'] ?? 'GET'))];
    }

    /**
     * Get app record by id.
     *
     * @param string $appId Application id.
     * @return array{id: string, slug: string, name: string, ...}|null App record or null.
     */
    public function getAppById(string $appId): ?array
    {
        foreach ($this->apps as $app) {
            if ($app['id'] === $appId) {
                return $app;
            }
        }
        return null;
    }

    /**
     * Get predicate definitions for an endpoint.
     *
     * @param string $endpointId Endpoint id.
     * @return array<int, array<string, mixed>>
     */
    public function getPredicatesForEndpoint(string $endpointId): array
    {
        return array_values(array_filter(
            $this->endpoint_predicates,
            fn($p) => $p['endpoint_id'] === $endpointId
        ));
    }

    /**
     * Get validation definitions (content-type, schema) for an endpoint.
     *
     * @param string $endpointId Endpoint id.
     * @return array<int, array{endpoint_id: string, content_type: string, schema: mixed}>
     */
    public function getValidationsForEndpoint(string $endpointId): array
    {
        return array_values(array_filter(
            $this->endpoint_validations,
            fn($v) => $v['endpoint_id'] === $endpointId
        ));
    }
}
