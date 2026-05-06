<?php

declare(strict_types=1);

/**
 * PHP built-in server router: send all requests to Switchboard entry scripts.
 *
 * Usage (from repo root):
 *   php -S localhost:8080 -t . scripts/php-router.php
 *
 * - /api/* → management API (apps, endpoints, parameters, validations, conditions, test-request).
 * - All other paths → sb/switchboard.php (match → validate → dispatch).
 *
 * The built-in server may pass the requested URI as first argument; we ensure
 * REQUEST_URI and env are set so sb/switchboard.php or sb/api.php can run.
 */

$base = dirname(__DIR__);
if (isset($argv[1])) {
    $uri = $argv[1];
    $_SERVER['REQUEST_URI'] = $uri;
    if (strpos($uri, '?') !== false) {
        [, $qs] = explode('?', $uri, 2);
        $_SERVER['QUERY_STRING'] = $qs;
    } else {
        $_SERVER['QUERY_STRING'] = '';
    }
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = $path !== null && $path !== false ? $path : '/';

if (strpos($path, '/api') === 0) {
    require $base . '/sb/api.php';
    return true;
}

putenv('SWITCHBOARD_CONFIG=' . $base . '/config/endpoints.json');
putenv('SWITCHBOARD_HANDLERS_PATH=' . $base . '/examples/minimal-handler');

require $base . '/sb/switchboard.php';
return true;
