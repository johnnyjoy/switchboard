<?php

declare(strict_types=1);

/**
 * Switchboard PHP Runtime entrypoint.
 *
 * Pipeline: load config → normalize request → parse /sb/<app>/<version>/ →
 * match → validate → dispatch → respond. Web servers can route any public
 * path to this script while preserving REQUEST_URI. No .php in public URLs
 * is required.
 *
 * @link https://www.php.net/manual/en/language.types.declarations.php PHP strict type declarations
 */

namespace Switchboard\Runtime;

require_once __DIR__ . '/Registry.php';
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

$configPath = getenv('SWITCHBOARD_CONFIG') ?: (__DIR__ . '/../config/endpoints.json');
$handlersPath = getenv('SWITCHBOARD_HANDLERS_PATH') ?: null;
if ($handlersPath !== null) {
    Dispatcher::setHandlersPath($handlersPath);
}

try {
    $registry = Registry::load($configPath);
} catch (\Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(503);
    echo json_encode(['error' => 'config_error', 'message' => $e->getMessage()]);
    exit;
}

$request = NormalizedRequest::fromGlobals();
$serverParam = static function (string $name): ?string {
    $value = $_SERVER[$name] ?? getenv($name);
    return is_string($value) && $value !== '' ? $value : null;
};

$pathPrefix = PathParser::parsePrefix($request->path)
    ?? PathParser::parseMount(
        $request->path,
        $serverParam('SWITCHBOARD_MOUNT_APP'),
        $serverParam('SWITCHBOARD_MOUNT_PATH'),
        $serverParam('SWITCHBOARD_MOUNT_API_PREFIX') ?? '/api'
    );

$endpoint = Matcher::match(
    $request,
    $registry->endpoints,
    $registry->apps,
    $pathPrefix
);

if ($endpoint === null) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'error' => 'no_match',
        'message' => 'No endpoint matched this request',
        'path' => $request->path,
        'method' => $request->method,
    ]);
    exit;
}

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

if (!$validation['valid']) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'error' => 'validation_failed',
        'message' => 'Request validation failed',
        'details' => $validation['errors'],
    ]);
    exit;
}

try {
    $result = Dispatcher::dispatch($endpoint, $request, $registry);
} catch (\Throwable $e) {
    error_log('[router] Dispatch error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'dispatch_error', 'message' => 'Handler invocation failed']);
    exit;
}

$status = $result['status'];
$headers = $result['headers'] ?? [];
$body = $result['body'] ?? '';

if (is_array($body)) {
    $bodyOut = json_encode($body);
    if (!isset($headers['Content-Type'])) {
        $headers['Content-Type'] = 'application/json';
    }
} else {
    $bodyOut = (string) $body;
}

http_response_code($status);
foreach ($headers as $name => $value) {
    header("{$name}: {$value}", true);
}
echo $bodyOut;
