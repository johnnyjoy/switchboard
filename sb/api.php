<?php

declare(strict_types=1);

/**
 * Switchboard management API — PHP-only backend for apps, endpoints, parameters,
 * validations, conditions, and test-request. Reads/writes config/endpoints.json.
 * Served when the router receives requests under /api.
 *
 * @link docs/endpoint-schema.md
 */

namespace Switchboard\Runtime;

require_once __DIR__ . '/Registry.php';
require_once __DIR__ . '/RegistryStore.php';
require_once __DIR__ . '/ApiValidation.php';
require_once __DIR__ . '/SwitchboardRequest.php';
require_once __DIR__ . '/SwitchboardResponse.php';
require_once __DIR__ . '/SwitchboardContext.php';
require_once __DIR__ . '/SwitchboardAppInterface.php';
require_once __DIR__ . '/NormalizedRequest.php';
require_once __DIR__ . '/NormalizedResponse.php';
require_once __DIR__ . '/PathParser.php';
require_once __DIR__ . '/EndpointPredicateEvaluator.php';
require_once __DIR__ . '/Matcher.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Dispatcher.php';

header('Content-Type: application/json; charset=utf-8');

$configPath = getenv('SWITCHBOARD_CONFIG') ?: (__DIR__ . '/../config/endpoints.json');
$basePath = '/api';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ($path !== null && $path !== false) ? $path : '/';
$path = $path === '' ? '/' : $path;

if (strpos($path, $basePath) !== 0) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'message' => 'API path must start with /api']);
    exit;
}

$path = substr($path, strlen($basePath));
$path = $path === '' ? '/' : $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/** @return array<string, mixed> */
function loadConfig(string $configPath): array
{
    return RegistryStore::load($configPath);
}

/** @param array<string, mixed> $data */
function saveConfig(string $configPath, array $data): void
{
    try {
        RegistryStore::save($configPath, $data);
    } catch (\Throwable $error) {
        sendJson(500, [
            'error' => 'registry_write_failed',
            'message' => 'Failed to persist registry configuration',
            'detail' => $error->getMessage(),
        ]);
    }
}

function uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function now(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function sendJson(int $status, mixed $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Decode an API JSON request body.
 *
 * @return array<string, mixed>|list<mixed>|null
 */
function decodeRequestBody(string|false $body): array|null
{
    if ($body === false || $body === '') {
        return null;
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $error) {
        sendJson(400, [
            'error' => 'invalid_json',
            'message' => 'Request body must be valid JSON',
            'detail' => $error->getMessage(),
        ]);
    }

    if (!is_array($decoded) || array_is_list($decoded)) {
        sendJson(400, [
            'error' => 'invalid_json',
            'message' => 'Request body must be a JSON object',
        ]);
    }

    return $decoded;
}

/** @param array<string, string> $errors */
function sendValidationErrors(array $errors): void
{
    sendJson(400, [
        'error' => 'validation_failed',
        'message' => 'Validation failed',
        'errors' => $errors,
    ]);
}

$body = file_get_contents('php://input');
$bodyDecoded = decodeRequestBody($body);

function findById(array $list, string $id): ?array
{
    foreach ($list as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }
    return null;
}

function uniqueCopiedSlug(string $slug, array $apps): string
{
    $base = $slug === '' ? 'app-copy' : $slug . '-copy';
    $existing = array_map(fn($app) => (string)($app['slug'] ?? ''), $apps);
    $candidate = $base;
    $suffix = 2;
    while (in_array($candidate, $existing, true)) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
    return $candidate;
}

function cascadeDeleteApp(array $config, string $appId): array
{
    $endpointIds = [];
    foreach ($config['endpoints'] ?? [] as $endpoint) {
        if (($endpoint['app_id'] ?? null) === $appId) {
            $endpointIds[] = $endpoint['id'] ?? null;
        }
    }
    $endpointIds = array_values(array_filter($endpointIds, fn($id) => is_string($id) && $id !== ''));

    $config['apps'] = array_values(array_filter($config['apps'] ?? [], fn($app) => ($app['id'] ?? null) !== $appId));
    $config['endpoints'] = array_values(array_filter($config['endpoints'] ?? [], fn($endpoint) => ($endpoint['app_id'] ?? null) !== $appId));
    $config['endpoint_predicates'] = array_values(array_filter($config['endpoint_predicates'] ?? [], fn($predicate) => !in_array($predicate['endpoint_id'] ?? null, $endpointIds, true)));
    $config['endpoint_validations'] = array_values(array_filter($config['endpoint_validations'] ?? [], fn($validation) => !in_array($validation['endpoint_id'] ?? null, $endpointIds, true)));

    return $config;
}

function uniqueCopiedEndpointPath(array $endpoint, array $endpoints): string
{
    $path = (string)($endpoint['path'] ?? '/endpoint');
    $methods = ApiValidation::normalizeMethods($endpoint['methods'] ?? ($endpoint['method'] ?? 'GET'));
    $appId = (string)($endpoint['app_id'] ?? '');
    $host = ApiValidation::normalizeRouteHost($endpoint['host'] ?? null);
    $trimmedPath = rtrim($path, '/');
    $base = $trimmedPath === '' ? '/copy' : $trimmedPath . '-copy';
    $candidate = $base;
    $suffix = 2;

    $exists = function (string $candidatePath) use ($endpoints, $appId, $methods, $host): bool {
        foreach ($endpoints as $existing) {
            if (($existing['app_id'] ?? null) !== $appId) {
                continue;
            }
            if (ApiValidation::normalizeRouteHost($existing['host'] ?? null) !== $host) {
                continue;
            }
            $existingMethods = ApiValidation::normalizeMethods($existing['methods'] ?? ($existing['method'] ?? []));
            if (array_intersect($existingMethods, $methods) === []) {
                continue;
            }
            if (($existing['path'] ?? null) === $candidatePath) {
                return true;
            }
        }
        return false;
    };

    while ($exists($candidate)) {
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }

    return $candidate;
}

// Route: GET /apps, POST /apps, PATCH /apps/:id, DELETE /apps/:id, POST /apps/:id/copy
if (preg_match('#^/apps$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        sendJson(200, $config['apps']);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $name = trim((string)($in['name'] ?? ''));
        $slug = array_key_exists('slug', $in) ? trim((string)$in['slug']) : ApiValidation::defaultSlugFromName($name);
        $id = uuid();
        $ts = now();
        $app = [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'description' => $in['description'] ?? null,
            'app_path' => $in['app_path'] ?? null,
            'enabled' => ($in['enabled'] ?? true) !== false,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $errors = ApiValidation::appErrors($app, $config);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $config['apps'][] = $app;
        saveConfig($configPath, $config);
        sendJson(201, $app);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/apps/([^/]+)/copy$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $app = findById($config['apps'], $id);
    if ($app === null) {
        sendJson(404, ['error' => 'not_found', 'message' => 'App not found']);
    }
    if ($method !== 'POST') {
        sendJson(405, ['error' => 'method_not_allowed']);
    }

    $ts = now();
    $newAppId = uuid();
    $copiedApp = $app;
    $copiedApp['id'] = $newAppId;
    $copiedApp['slug'] = uniqueCopiedSlug((string)($app['slug'] ?? ''), $config['apps']);
    $copiedApp['name'] = trim((string)($app['name'] ?? 'App')) . ' Copy';
    $copiedApp['created_at'] = $ts;
    $copiedApp['updated_at'] = $ts;

    $endpointIdMap = [];
    $copiedEndpoints = [];
    foreach ($config['endpoints'] ?? [] as $endpoint) {
        if (($endpoint['app_id'] ?? null) !== $id) {
            continue;
        }
        $newEndpointId = uuid();
        $endpointIdMap[$endpoint['id']] = $newEndpointId;
        $copiedEndpoint = $endpoint;
        $copiedEndpoint['id'] = $newEndpointId;
        $copiedEndpoint['app_id'] = $newAppId;
        $copiedEndpoint['created_at'] = $ts;
        $copiedEndpoint['updated_at'] = $ts;
        $copiedEndpoints[] = $copiedEndpoint;
    }

    $copyRelated = function (array $items) use ($endpointIdMap, $ts): array {
        $copied = [];
        foreach ($items as $item) {
            $oldEndpointId = $item['endpoint_id'] ?? null;
            if (!is_string($oldEndpointId) || !isset($endpointIdMap[$oldEndpointId])) {
                continue;
            }
            $copy = $item;
            $copy['id'] = uuid();
            $copy['endpoint_id'] = $endpointIdMap[$oldEndpointId];
            $copy['created_at'] = $ts;
            $copy['updated_at'] = $ts;
            $copied[] = $copy;
        }
        return $copied;
    };

    $config['apps'][] = $copiedApp;
    $config['endpoints'] = array_merge($config['endpoints'] ?? [], $copiedEndpoints);
    $config['endpoint_predicates'] = array_merge($config['endpoint_predicates'] ?? [], $copyRelated($config['endpoint_predicates'] ?? []));
    $config['endpoint_validations'] = array_merge($config['endpoint_validations'] ?? [], $copyRelated($config['endpoint_validations'] ?? []));

    $errors = ApiValidation::appErrors($copiedApp, $config, $newAppId);
    if ($errors !== []) {
        sendValidationErrors($errors);
    }

    saveConfig($configPath, $config);
    sendJson(201, $copiedApp);
}

if (preg_match('#^/apps/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $app = findById($config['apps'], $id);
    if ($app === null) {
        sendJson(404, ['error' => 'not_found', 'message' => 'App not found']);
    }
    if ($method === 'PATCH') {
        $in = $bodyDecoded ?? [];
        $idx = null;
        foreach ($config['apps'] as $i => $a) {
            if (($a['id'] ?? null) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            sendJson(404, ['error' => 'not_found']);
        }
        $cur = $config['apps'][$idx];
        if (array_key_exists('name', $in)) {
            $cur['name'] = (string) $in['name'];
        }
        if (array_key_exists('slug', $in)) {
            $cur['slug'] = (string) $in['slug'];
        }
        if (array_key_exists('description', $in)) {
            $cur['description'] = $in['description'];
        }
        if (array_key_exists('app_path', $in)) {
            $cur['app_path'] = $in['app_path'];
        }
        if (array_key_exists('enabled', $in)) {
            $cur['enabled'] = (bool) $in['enabled'];
        }
        $errors = ApiValidation::appErrors($cur, $config, $id);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $cur['updated_at'] = now();
        $config['apps'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config = cascadeDeleteApp($config, $id);
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoints/([^/]+)/copy$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $endpoint = findById($config['endpoints'], $id);
    if ($endpoint === null) {
        sendJson(404, ['error' => 'not_found', 'message' => 'Endpoint not found']);
    }
    if ($method !== 'POST') {
        sendJson(405, ['error' => 'method_not_allowed']);
    }

    $ts = now();
    $newEndpointId = uuid();
    $copiedEndpoint = $endpoint;
    $copiedEndpoint['id'] = $newEndpointId;
    $copiedEndpoint['name'] = trim((string)($endpoint['name'] ?? 'Endpoint')) . ' Copy';
    $copiedEndpoint['path'] = uniqueCopiedEndpointPath($endpoint, $config['endpoints'] ?? []);
    $copiedEndpoint['enabled'] = false;
    $copiedEndpoint['created_at'] = $ts;
    $copiedEndpoint['updated_at'] = $ts;

    $copyRelated = function (array $items) use ($id, $newEndpointId, $ts): array {
        $copied = [];
        foreach ($items as $item) {
            if (($item['endpoint_id'] ?? null) !== $id) {
                continue;
            }
            $copy = $item;
            $copy['id'] = uuid();
            $copy['endpoint_id'] = $newEndpointId;
            $copy['created_at'] = $ts;
            $copy['updated_at'] = $ts;
            $copied[] = $copy;
        }
        return $copied;
    };

    $config['endpoints'][] = $copiedEndpoint;
    $config['endpoint_predicates'] = array_merge($config['endpoint_predicates'] ?? [], $copyRelated($config['endpoint_predicates'] ?? []));
    $config['endpoint_validations'] = array_merge($config['endpoint_validations'] ?? [], $copyRelated($config['endpoint_validations'] ?? []));

    $errors = ApiValidation::endpointErrors($copiedEndpoint, $config, $newEndpointId);
    if ($errors !== []) {
        sendValidationErrors($errors);
    }

    saveConfig($configPath, $config);
    sendJson(201, $copiedEndpoint);
}

// GET /endpoints?app_id=, POST /endpoints, PATCH /endpoints/:id, DELETE /endpoints/:id
if (preg_match('#^/endpoints$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        $apps = $config['apps'];
        $list = $config['endpoints'];
        $appId = $_GET['app_id'] ?? null;
        if ($appId !== null && $appId !== '') {
            $list = array_values(array_filter($list, fn($e) => ($e['app_id'] ?? null) === $appId));
        }
        sendJson(200, $list);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $appId = $in['app_id'] ?? null;
        $id = uuid();
        $ts = now();
        $ep = [
            'id' => $id,
            'app_id' => $appId,
            'name' => $in['name'] ?? '',
            'host' => ApiValidation::normalizeRouteHost($in['host'] ?? null),
            'path' => $in['path'] ?? '',
            'methods' => ApiValidation::normalizeMethods($in['methods'] ?? ($in['method'] ?? [])),
            'handler_class' => $in['handler_class'] ?? '',
            'handler_method' => $in['handler_method'] ?? 'handle',
            'enabled' => ($in['enabled'] ?? true) !== false,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $errors = ApiValidation::endpointErrors($ep, $config);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $ep['path'] = ApiValidation::normalizeRoutePath($ep['path']);
        $config['endpoints'][] = $ep;
        saveConfig($configPath, $config);
        sendJson(201, $ep);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoints/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $ep = findById($config['endpoints'], $id);
    if ($ep === null) {
        sendJson(404, ['error' => 'not_found', 'message' => 'Endpoint not found']);
    }
    if ($method === 'PATCH') {
        $in = $bodyDecoded ?? [];
        $idx = null;
        foreach ($config['endpoints'] as $i => $e) {
            if (($e['id'] ?? null) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            sendJson(404, ['error' => 'not_found']);
        }
        $cur = $config['endpoints'][$idx];
        foreach (['name', 'host', 'path', 'methods', 'handler_class', 'handler_method', 'enabled'] as $key) {
            if (array_key_exists($key, $in)) {
                if ($key === 'enabled') {
                    $cur[$key] = (bool) $in[$key];
                } elseif ($key === 'host' && $in[$key] === '') {
                    $cur[$key] = null;
                } elseif ($key === 'host') {
                    $cur[$key] = ApiValidation::normalizeRouteHost($in[$key]);
                } elseif ($key === 'methods') {
                    $cur[$key] = ApiValidation::normalizeMethods($in[$key]);
                } else {
                    $cur[$key] = (string) $in[$key];
                }
            }
        }
        if (array_key_exists('method', $in) && !array_key_exists('methods', $in)) {
            $cur['methods'] = ApiValidation::normalizeMethods($in['method']);
            unset($cur['method']);
        }
        $errors = ApiValidation::endpointErrors($cur, $config, $id);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $cur['path'] = ApiValidation::normalizeRoutePath($cur['path'] ?? '');
        $cur['updated_at'] = now();
        $config['endpoints'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config['endpoints'] = array_values(array_filter($config['endpoints'], fn($e) => ($e['id'] ?? null) !== $id));
        $config['endpoint_predicates'] = array_values(array_filter($config['endpoint_predicates'] ?? [], fn($p) => ($p['endpoint_id'] ?? null) !== $id));
        $config['endpoint_validations'] = array_values(array_filter($config['endpoint_validations'] ?? [], fn($v) => ($v['endpoint_id'] ?? null) !== $id));
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

// endpoint-predicates
if (preg_match('#^/endpoint-predicates$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        $endpointId = $_GET['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $list = array_values(array_filter($config['endpoint_predicates'] ?? [], fn($p) => ($p['endpoint_id'] ?? null) === $endpointId));
        sendJson(200, $list);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $id = uuid();
        $ts = now();
        $row = [
            'id' => $id,
            'endpoint_id' => $in['endpoint_id'] ?? null,
            'source' => $in['source'] ?? 'query',
            'name' => $in['name'] ?? '',
            'op' => $in['op'] ?? 'present',
            'value' => $in['value'] ?? null,
            'value_type' => $in['value_type'] ?? null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $errors = ApiValidation::predicateErrors($row, $config);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $config['endpoint_predicates'][] = $row;
        saveConfig($configPath, $config);
        sendJson(201, $row);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoint-predicates/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $idx = null;
    foreach ($config['endpoint_predicates'] ?? [] as $i => $predicate) {
        if (($predicate['id'] ?? null) === $id) {
            $idx = $i;
            break;
        }
    }
    if ($idx === null) {
        sendJson(404, ['error' => 'not_found']);
    }
    if ($method === 'PATCH') {
        $cur = $config['endpoint_predicates'][$idx];
        foreach (['source', 'name', 'op', 'value', 'value_type'] as $key) {
            if (array_key_exists($key, $bodyDecoded ?? [])) {
                $cur[$key] = $bodyDecoded[$key];
            }
        }
        $errors = ApiValidation::predicateErrors($cur, $config, $id);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $cur['updated_at'] = now();
        $config['endpoint_predicates'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config['endpoint_predicates'] = array_values(array_filter($config['endpoint_predicates'] ?? [], fn($p) => ($p['id'] ?? null) !== $id));
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

// endpoint-parameters
if (preg_match('#^/endpoint-parameters$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        $endpointId = $_GET['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $list = array_values(array_filter($config['endpoint_parameters'], fn($p) => ($p['endpoint_id'] ?? null) === $endpointId));
        sendJson(200, $list);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $endpointId = $in['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $id = uuid();
        $ts = now();
        $row = [
            'id' => $id,
            'endpoint_id' => $endpointId,
            'in' => $in['in'] ?? 'query',
            'name' => $in['name'] ?? '',
            'required' => ($in['required'] ?? false) === true,
            'type' => $in['type'] ?? null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $config['endpoint_parameters'][] = $row;
        saveConfig($configPath, $config);
        sendJson(201, $row);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoint-parameters/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $row = findById($config['endpoint_parameters'], $id);
    if ($row === null) {
        sendJson(404, ['error' => 'not_found']);
    }
    if ($method === 'PATCH') {
        $in = $bodyDecoded ?? [];
        $idx = null;
        foreach ($config['endpoint_parameters'] as $i => $p) {
            if (($p['id'] ?? null) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            sendJson(404, ['error' => 'not_found']);
        }
        $cur = $config['endpoint_parameters'][$idx];
        foreach (['in', 'name', 'required', 'type'] as $key) {
            if (array_key_exists($key, $in)) {
                if ($key === 'required') {
                    $cur[$key] = (bool) $in[$key];
                } elseif ($key === 'type' && $in[$key] === '') {
                    $cur[$key] = null;
                } else {
                    $cur[$key] = $in[$key];
                }
            }
        }
        $cur['updated_at'] = now();
        $config['endpoint_parameters'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config['endpoint_parameters'] = array_values(array_filter($config['endpoint_parameters'], fn($p) => ($p['id'] ?? null) !== $id));
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

// endpoint-validations
if (preg_match('#^/endpoint-validations$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        $endpointId = $_GET['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $list = array_values(array_filter($config['endpoint_validations'], fn($v) => ($v['endpoint_id'] ?? null) === $endpointId));
        sendJson(200, $list);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $endpointId = $in['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $id = uuid();
        $ts = now();
        $row = [
            'id' => $id,
            'endpoint_id' => $endpointId,
            'content_type' => $in['content_type'] ?? 'application/json',
            'schema' => $in['schema'] ?? null,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $config['endpoint_validations'][] = $row;
        saveConfig($configPath, $config);
        sendJson(201, $row);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoint-validations/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $row = findById($config['endpoint_validations'], $id);
    if ($row === null) {
        sendJson(404, ['error' => 'not_found']);
    }
    if ($method === 'PATCH') {
        $in = $bodyDecoded ?? [];
        $idx = null;
        foreach ($config['endpoint_validations'] as $i => $v) {
            if (($v['id'] ?? null) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            sendJson(404, ['error' => 'not_found']);
        }
        $cur = $config['endpoint_validations'][$idx];
        if (array_key_exists('content_type', $in)) {
            $cur['content_type'] = (string) $in['content_type'];
        }
        if (array_key_exists('schema', $in)) {
            $cur['schema'] = $in['schema'];
        }
        $cur['updated_at'] = now();
        $config['endpoint_validations'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config['endpoint_validations'] = array_values(array_filter($config['endpoint_validations'], fn($v) => ($v['id'] ?? null) !== $id));
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

// endpoint-conditions
if (preg_match('#^/endpoint-conditions$#', $path)) {
    $config = loadConfig($configPath);
    if ($method === 'GET') {
        $endpointId = $_GET['endpoint_id'] ?? null;
        if ($endpointId === null || $endpointId === '') {
            sendJson(400, ['error' => 'validation_failed', 'message' => 'endpoint_id required']);
        }
        $list = array_values(array_filter($config['endpoint_conditions'], fn($c) => ($c['endpoint_id'] ?? null) === $endpointId));
        sendJson(200, $list);
    }
    if ($method === 'POST') {
        $in = $bodyDecoded ?? [];
        $endpointId = $in['endpoint_id'] ?? null;
        $id = uuid();
        $ts = now();
        $kind = $in['kind'] ?? $in['source'] ?? 'query';
        $op = $in['op'] ?? $in['operator'] ?? null;
        $op = $op === null ? null : ApiValidation::normalizeOp((string)$op);
        $row = [
            'id' => $id,
            'endpoint_id' => $endpointId,
            'kind' => $kind,
            'key' => $in['key'] ?? null,
            'value' => isset($in['value']) ? (string) $in['value'] : '',
            'op' => $op,
            'created_at' => $ts,
            'updated_at' => $ts,
        ];
        $errors = ApiValidation::conditionErrors($row, $config);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $config['endpoint_conditions'][] = $row;
        saveConfig($configPath, $config);
        sendJson(201, $row);
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

if (preg_match('#^/endpoint-conditions/([^/]+)$#', $path, $m)) {
    $id = $m[1];
    $config = loadConfig($configPath);
    $row = findById($config['endpoint_conditions'], $id);
    if ($row === null) {
        sendJson(404, ['error' => 'not_found']);
    }
    if ($method === 'PATCH') {
        $in = $bodyDecoded ?? [];
        $idx = null;
        foreach ($config['endpoint_conditions'] as $i => $c) {
            if (($c['id'] ?? null) === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            sendJson(404, ['error' => 'not_found']);
        }
        $cur = $config['endpoint_conditions'][$idx];
        foreach (['kind', 'key', 'value', 'op'] as $key) {
            if (array_key_exists($key, $in)) {
                $cur[$key] = $in[$key];
            }
        }
        if (array_key_exists('source', $in)) {
            $cur['kind'] = $in['source'];
        }
        if (array_key_exists('operator', $in)) {
            $cur['op'] = $in['operator'];
        }
        if (array_key_exists('op', $cur) && $cur['op'] !== null && $cur['op'] !== '') {
            $cur['op'] = ApiValidation::normalizeOp((string)$cur['op']);
        }
        $errors = ApiValidation::conditionErrors($cur, $config, $id);
        if ($errors !== []) {
            sendValidationErrors($errors);
        }
        $cur['updated_at'] = now();
        $config['endpoint_conditions'][$idx] = $cur;
        saveConfig($configPath, $config);
        sendJson(200, $cur);
    }
    if ($method === 'DELETE') {
        $config['endpoint_conditions'] = array_values(array_filter($config['endpoint_conditions'], fn($c) => ($c['id'] ?? null) !== $id));
        saveConfig($configPath, $config);
        http_response_code(204);
        exit;
    }
    sendJson(405, ['error' => 'method_not_allowed']);
}

// POST /test-request
if (preg_match('#^/test-request$#', $path) && $method === 'POST') {
    $in = $bodyDecoded ?? [];
    $host = $in['host'] ?? 'localhost';
    $pathInput = (string)($in['path'] ?? '/');
    $pathOnly = parse_url($pathInput, PHP_URL_PATH);
    $pathReq = is_string($pathOnly) && $pathOnly !== '' ? $pathOnly : '/';
    $pathReq = '/' . trim($pathReq, '/');
    if ($pathReq === '/') {
        $pathReq = '/';
    }
    $queryString = parse_url($pathInput, PHP_URL_QUERY);
    $queryParams = [];
    if (is_string($queryString) && $queryString !== '') {
        parse_str($queryString, $queryParams);
    }
    foreach (($in['query'] ?? []) as $key => $value) {
        $queryParams[$key] = $value;
    }
    $methodReq = strtoupper($in['method'] ?? 'GET');
    $bodyReq = isset($in['body']) ? (is_string($in['body']) ? $in['body'] : json_encode($in['body'])) : null;
    $contentType = (string)($in['content_type'] ?? $in['contentType'] ?? 'application/json');
    $headersReq = [];
    foreach (($in['headers'] ?? []) as $key => $value) {
        $headersReq[strtolower((string)$key)] = (string)$value;
    }
    if ($contentType !== '') {
        $headersReq['content-type'] = $contentType;
    }
    $cookiesReq = [];
    foreach (($in['cookies'] ?? []) as $key => $value) {
        $cookiesReq[(string)$key] = (string)$value;
    }
    $formReq = NormalizedRequest::parseForm($bodyReq, $contentType);
    if (is_array($in['form'] ?? null)) {
        foreach ($in['form'] as $key => $value) {
            $formReq[(string)$key] = is_array($value) ? array_map('strval', $value) : (string)$value;
        }
    }
    $jsonReq = NormalizedRequest::parseJson($bodyReq, $contentType);
    if (array_key_exists('json', $in)) {
        $jsonReq = $in['json'];
    }

    $registry = null;
    try {
        $registry = Registry::load($configPath);
    } catch (\Throwable $e) {
        sendJson(503, ['error' => 'config_error', 'message' => $e->getMessage()]);
    }
    if ($registry === null) {
        sendJson(503, ['error' => 'config_error', 'message' => 'Registry unavailable']);
    }

    $request = new NormalizedRequest(
        $host,
        $pathReq,
        $methodReq,
        $queryParams,
        $headersReq,
        $cookiesReq,
        $bodyReq,
        $contentType,
        null,
        null,
        [],
        $formReq,
        $jsonReq
    );
    $pathPrefix = PathParser::parsePrefix($pathReq);
    $endpoint = Matcher::match($request, $registry->endpoints, $registry->apps, $pathPrefix);

    $app = $endpoint !== null ? $registry->getAppById($endpoint['app_id']) : null;
    $validation = ['valid' => false, 'errors' => ['No endpoint matched this request']];
    $dispatch = null;
    $runtimeResponse = null;

    if ($endpoint !== null) {
        if (!empty($endpoint['path_params']) && method_exists($request, 'withPathParams')) {
            $request = $request->withPathParams($endpoint['path_params']);
        }
        $validations = $registry->getValidationsForEndpoint($endpoint['id']);
        $validation = Validator::validate(
            $endpoint,
            $request->query,
            $request->body,
            $request->contentType,
            [],
            $validations
        );

        if ($validation['valid']) {
            try {
                $runtimeResponse = Dispatcher::dispatch($endpoint, $request, $registry);
                $dispatch = [
                    'status' => 'passed',
                    'response_status' => $runtimeResponse['status'] ?? null,
                ];
            } catch (\Throwable $e) {
                $dispatch = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $runtimeResponse = null;
            }
        } else {
            $dispatch = [
                'status' => 'skipped',
                'reason' => 'validation_failed',
            ];
        }
    }

    $result = [
        'matched' => $endpoint !== null,
        'endpoint' => $endpoint !== null ? [
            'id' => $endpoint['id'],
            'name' => $endpoint['name'] ?? '',
            'path' => $endpoint['path'],
            'methods' => $endpoint['methods'] ?? [],
            'handler_class' => $endpoint['handler_class'],
            'handler_method' => $endpoint['handler_method'] ?? 'handle',
        ] : null,
        'app' => $app !== null ? [
            'id' => $app['id'],
            'name' => $app['name'] ?? '',
            'slug' => $app['slug'] ?? '',
        ] : null,
        'trace' => [
            'match' => [
                'status' => $endpoint !== null ? 'passed' : 'failed',
                'pathPrefix' => $pathPrefix,
                'pathParams' => $endpoint['path_params'] ?? [],
            ],
            'validation' => [
                'status' => $validation['valid'] ? 'passed' : 'failed',
                'errors' => $validation['errors'],
            ],
            'dispatch' => $dispatch,
        ],
        'runtimeAvailable' => $runtimeResponse !== null,
        'runtimeResponse' => $runtimeResponse,
    ];
    sendJson(200, $result);
}

http_response_code(404);
echo json_encode(['error' => 'not_found', 'message' => 'Unknown API route']);
