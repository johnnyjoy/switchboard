<?php

declare(strict_types=1);

/**
 * Standalone test runner (no PHPUnit). Run: php tests/php/run-standalone.php
 * Requires only the PHP runtime files (no composer/vendor).
 */

$base = dirname(__DIR__, 2);
require_once $base . '/sb/Registry.php';
require_once $base . '/sb/SwitchboardRequest.php';
require_once $base . '/sb/SwitchboardResponse.php';
require_once $base . '/sb/SwitchboardContext.php';
require_once $base . '/sb/SwitchboardAppInterface.php';
require_once $base . '/sb/NormalizedRequest.php';
require_once $base . '/sb/NormalizedResponse.php';
require_once $base . '/sb/PathParser.php';
require_once $base . '/sb/EndpointPredicateEvaluator.php';
require_once $base . '/sb/Matcher.php';
require_once $base . '/sb/Validator.php';
require_once $base . '/sb/RouteConditionEvaluator.php';
require_once $base . '/sb/Dispatcher.php';
require_once $base . '/sb/ApiValidation.php';
require_once $base . '/sb/RegistryStore.php';

use Switchboard\Runtime\PathParser;
use Switchboard\Runtime\ApiValidation;
use Switchboard\Runtime\Matcher;
use Switchboard\Runtime\Validator;
use Switchboard\Runtime\Dispatcher;
use Switchboard\Runtime\NormalizedRequest;
use Switchboard\Runtime\Registry;
use Switchboard\Runtime\RegistryStore;
use Switchboard\Runtime\RouteConditionEvaluator;

$fail = 0;

function ok(string $name, bool $cond): void
{
    global $fail;
    if ($cond) {
        echo "  OK {$name}\n";
    } else {
        echo "  FAIL {$name}\n";
        $fail++;
    }
}

echo "PathParser\n";
$pre = PathParser::parsePrefix('/sb/minimal/v1/health');
ok('parsePrefix returns array', $pre !== null && isset($pre['app_slug']));
ok('parsePrefix app_slug minimal', $pre['app_slug'] === 'minimal');
ok('parsePrefix path /health', $pre['path'] === '/health');
ok('parsePrefix no prefix returns null', PathParser::parsePrefix('/other') === null);
ok('normalizePath', PathParser::normalizePath('health') === '/health');
$mountPre = PathParser::parseMount('/minimal/api/health', 'minimal', '/minimal', '/api');
ok('parseMount returns array', $mountPre !== null && isset($mountPre['app_slug']));
ok('parseMount app_slug minimal', $mountPre['app_slug'] === 'minimal');
ok('parseMount path /health', $mountPre['path'] === '/health');
ok('parseMount ignores frontend route', PathParser::parseMount('/minimal/products', 'minimal', '/minimal', '/api') === null);

echo "Matcher\n";
$apps = [['id' => 'minimal-php-app', 'slug' => 'minimal', 'name' => 'Minimal', 'enabled' => true]];
$endpoints = [
    ['id' => 'e-minimal-health', 'app_id' => 'minimal-php-app', 'host' => null, 'path' => '/health', 'methods' => ['GET', 'HEAD'], 'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle', 'enabled' => true],
];
$prefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/health'];
$reqMatch = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', [], [], [], null, null, null, null);
$m = Matcher::match($reqMatch, $endpoints, $apps, $prefix);
ok('match returns endpoint', $m !== null && $m['id'] === 'e-minimal-health');
$mountReq = new NormalizedRequest('localhost', '/minimal/api/health', 'GET', [], [], [], null, null, null, null);
ok('match supports mounted API context', Matcher::match($mountReq, $endpoints, $apps, $mountPre) !== null);
$reqHeadMatch = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'HEAD', [], [], [], null, null, null, null);
ok('match supports methods array', Matcher::match($reqHeadMatch, $endpoints, $apps, $prefix) !== null);
$prefixWrongPath = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/other'];
$reqWrongPath = new NormalizedRequest('localhost', '/sb/minimal/v1/other', 'GET', [], [], [], null, null, null, null);
ok('match wrong path null', Matcher::match($reqWrongPath, $endpoints, $apps, $prefixWrongPath) === null);
$reqWrongMethod = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'POST', [], [], [], null, null, null, null);
ok('match wrong method null', Matcher::match($reqWrongMethod, $endpoints, $apps, $prefix) === null);
$paramEndpoints = [
    ['id' => 'e-minimal-user', 'app_id' => 'minimal-php-app', 'host' => null, 'path' => '/users/{id:integer}', 'methods' => ['GET'], 'handler_class' => 'Minimal\\User', 'handler_method' => 'handle', 'enabled' => true],
];
$paramPrefix = ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/users/42'];
$paramReq = new NormalizedRequest('localhost', '/sb/minimal/v1/users/42', 'GET', [], [], [], null, null, null, null);
$paramMatch = Matcher::match($paramReq, $paramEndpoints, $apps, $paramPrefix);
ok('match captures path parameter', $paramMatch !== null && ($paramMatch['path_params']['id'] ?? null) === '42');
ok('normalized request carries path parameters', $paramReq->withPathParams($paramMatch['path_params'] ?? [])->getPathParams()['id'] === '42');
$badParamReq = new NormalizedRequest('localhost', '/sb/minimal/v1/users/abc', 'GET', [], [], [], null, null, null, null);
ok('typed path parameter rejects invalid value', Matcher::match($badParamReq, $paramEndpoints, $apps, ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/users/abc']) === null);
$precedenceEndpoints = [
    ['id' => 'a-param', 'app_id' => 'minimal-php-app', 'host' => null, 'path' => '/users/{id}', 'methods' => ['GET'], 'handler_class' => 'Minimal\\User', 'handler_method' => 'handle', 'enabled' => true],
    ['id' => 'z-literal', 'app_id' => 'minimal-php-app', 'host' => null, 'path' => '/users/new', 'methods' => ['GET'], 'handler_class' => 'Minimal\\NewUser', 'handler_method' => 'handle', 'enabled' => true],
];
$precedenceReq = new NormalizedRequest('localhost', '/sb/minimal/v1/users/new', 'GET', [], [], [], null, null, null, null);
$precedenceMatch = Matcher::match($precedenceReq, $precedenceEndpoints, $apps, ['app_slug' => 'minimal', 'version' => 'v1', 'path' => '/users/new']);
ok('literal route wins over parameterized route', $precedenceMatch !== null && $precedenceMatch['id'] === 'z-literal');

echo "Validator\n";
$ep = ['id' => 'e1', 'path' => '/health', 'method' => 'GET'];
$r = Validator::validate($ep, [], null, null, [], []);
ok('validate no params valid', $r['valid'] === true);
$params = [['endpoint_id' => 'e1', 'in' => 'query', 'name' => 'limit', 'required' => true, 'type' => 'integer']];
$r2 = Validator::validate($ep, [], null, null, $params, []);
ok('validate required missing invalid', $r2['valid'] === false);
$r3 = Validator::validate($ep, ['limit' => '10'], null, null, $params, []);
ok('validate required present valid', $r3['valid'] === true);
$pathParamValidation = Validator::validate(
    ['id' => 'e2', 'path' => '/users/{id}', 'method' => 'GET', 'path_params' => ['id' => 'abc']],
    [],
    null,
    null,
    [['endpoint_id' => 'e2', 'in' => 'path', 'name' => 'id', 'required' => true, 'type' => 'integer']],
    []
);
ok('validate path parameter type invalid', $pathParamValidation['valid'] === false);

echo "ApiValidation\n";
$configForValidation = [
    'apps' => [
        ['id' => 'app-1', 'slug' => 'minimal', 'name' => 'Minimal', 'enabled' => true],
    ],
    'endpoints' => [
        ['id' => 'ep-1', 'app_id' => 'app-1', 'host' => null, 'path' => '/health', 'methods' => ['GET'], 'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle', 'enabled' => true],
    ],
];
$appErrors = ApiValidation::appErrors(['name' => 'Other', 'slug' => 'minimal'], $configForValidation);
ok('app validation rejects duplicate slug', isset($appErrors['slug']));
$appEditErrors = ApiValidation::appErrors(['name' => 'Minimal', 'slug' => 'minimal'], $configForValidation, 'app-1');
ok('app validation allows unchanged slug on edit', $appEditErrors === []);
$routeErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Dupe', 'host' => null, 'path' => '/health', 'methods' => ['GET', 'HEAD'], 'handler_class' => 'Minimal\\Other', 'handler_method' => 'handle'],
    $configForValidation
);
ok('endpoint validation rejects duplicate route', isset($routeErrors['route']));
$normalizedPathErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Dupe normalized path', 'host' => null, 'path' => '/health/', 'methods' => ['GET'], 'handler_class' => 'Minimal\\Other', 'handler_method' => 'handle'],
    $configForValidation
);
ok('endpoint validation rejects duplicate normalized route path', isset($normalizedPathErrors['route']));
$pathErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Bad path', 'host' => null, 'path' => 'health', 'methods' => ['GET'], 'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle'],
    $configForValidation
);
ok('endpoint validation rejects path without leading slash', isset($pathErrors['path']));
$patternErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Bad pattern', 'host' => null, 'path' => '/users/{bad-name}', 'methods' => ['GET'], 'handler_class' => 'Minimal\\User', 'handler_method' => 'handle'],
    $configForValidation
);
ok('endpoint validation rejects unsupported path parameter syntax', isset($patternErrors['path']));
$classErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Bad handler', 'host' => null, 'path' => '/bad-handler', 'methods' => ['GET'], 'handler_class' => 'Bad-Handler', 'handler_method' => 'handle'],
    $configForValidation
);
ok('endpoint validation rejects invalid handler class', isset($classErrors['handler_class']));
$methodErrors = ApiValidation::endpointErrors(
    ['app_id' => 'app-1', 'name' => 'Bad method', 'host' => null, 'path' => '/bad-method', 'methods' => ['GET'], 'handler_class' => 'Minimal\\Health', 'handler_method' => 'bad-method'],
    $configForValidation
);
ok('endpoint validation rejects invalid handler method', isset($methodErrors['handler_method']));
$sourceErrors = ApiValidation::predicateErrors(
    ['endpoint_id' => 'ep-1', 'source' => 'body', 'name' => 'id', 'value' => '123', 'op' => 'equals'],
    $configForValidation
);
$opErrors = ApiValidation::predicateErrors(
    ['endpoint_id' => 'ep-1', 'source' => 'query', 'name' => 'token', 'value' => 'secret', 'op' => 'starts_with'],
    $configForValidation
);
$nameErrors = ApiValidation::predicateErrors(
    ['endpoint_id' => 'ep-1', 'source' => 'header', 'name' => '', 'value' => 'abc', 'op' => 'equals'],
    $configForValidation
);
ok('predicate validation rejects unsupported source', isset($sourceErrors['source']));
ok('predicate validation rejects unsupported operator', isset($opErrors['op']));
ok('predicate validation requires name', isset($nameErrors['name']));
$regexErrors = ApiValidation::predicateErrors(
    ['endpoint_id' => 'ep-1', 'source' => 'query', 'name' => 'token', 'value' => '/[invalid/', 'op' => 'regex'],
    $configForValidation
);
ok('predicate validation rejects invalid regex', isset($regexErrors['value']));
$conditionRegexErrors = ApiValidation::conditionErrors(
    ['endpoint_id' => 'ep-1', 'kind' => 'query', 'key' => 'token', 'value' => '/[invalid/', 'op' => 'regex'],
    $configForValidation
);
ok('condition validation rejects invalid regex', isset($conditionRegexErrors['value']));

echo "EndpointPredicateEvaluator\n";
$conditionReq = new NormalizedRequest(
    'localhost',
    '/health',
    'GET',
    ['mode' => 'fast', 'token' => 'abc123', 'role' => 'admin'],
    ['user-agent' => 'SwitchboardBot/1.0'],
    [],
    null,
    null,
    '2001:db8::42',
    null
);
ok('predicate regex passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'token', 'op' => 'regex', 'value' => '/^abc\\d+$/']])['passed']);
ok('predicate invalid regex fails closed', !\Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'token', 'op' => 'regex', 'value' => '/[invalid/']])['passed']);
ok('predicate present passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'mode', 'op' => 'present']])['passed']);
ok('predicate absent passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'missing', 'op' => 'absent']])['passed']);
ok('predicate in passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'role', 'op' => 'in', 'value' => 'admin,operator']])['passed']);
ok('predicate absent fails when present', !\Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $conditionReq, [['source' => 'query', 'name' => 'mode', 'op' => 'absent']])['passed']);
$bodyReq = new NormalizedRequest(
    'localhost',
    '/submit',
    'POST',
    [],
    ['content-type' => 'application/json', 'x-mode' => 'live'],
    ['session' => 'abc'],
    '{"user":{"id":"11111111-1111-4111-8111-111111111111"}}',
    'application/json',
    null,
    null,
    [],
    ['title' => 'Hello'],
    ['user' => ['id' => '11111111-1111-4111-8111-111111111111']]
);
ok('predicate form passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $bodyReq, [['source' => 'form', 'name' => 'title', 'op' => 'equals', 'value' => 'Hello']])['passed']);
ok('predicate json type passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $bodyReq, [['source' => 'json', 'name' => 'user.id', 'op' => 'type', 'value_type' => 'uuid']])['passed']);
ok('predicate header passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $bodyReq, [['source' => 'header', 'name' => 'x-mode', 'op' => 'equals', 'value' => 'live']])['passed']);
ok('predicate cookie passes', \Switchboard\Runtime\EndpointPredicateEvaluator::evaluate([], $bodyReq, [['source' => 'cookie', 'name' => 'session', 'op' => 'present']])['passed']);
ok('request invalid json parses as null', NormalizedRequest::parseJson('{invalid-json', 'application/json') === null);
ok('route condition invalid regex fails closed', !RouteConditionEvaluator::evaluate(['query' => [['name' => 'mode', 'op' => 'regex', 'value' => '/[invalid/']]], $conditionReq));

echo "RegistryStore\n";
$tmpDir = sys_get_temp_dir() . '/switchboard-registry-test-' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);
$tmpConfigPath = $tmpDir . '/endpoints.json';
$initialConfig = [
    'apps' => [['id' => 'app-old', 'slug' => 'old', 'name' => 'Old', 'enabled' => true]],
    'endpoints' => [],
    'endpoint_predicates' => [],
    'endpoint_validations' => [],
];
file_put_contents($tmpConfigPath, json_encode($initialConfig));
$updatedConfig = [
    'apps' => [['id' => 'app-new', 'slug' => 'new', 'name' => 'New', 'enabled' => true]],
    'endpoints' => [],
    'endpoint_predicates' => [],
    'endpoint_validations' => [],
];
RegistryStore::save($tmpConfigPath, $updatedConfig);
$loadedUpdated = RegistryStore::load($tmpConfigPath);
ok('registry store saves updated config', ($loadedUpdated['apps'][0]['slug'] ?? null) === 'new');
ok('registry store creates backup', is_file(RegistryStore::backupPath($tmpConfigPath)));
file_put_contents($tmpConfigPath, '{invalid-json');
$fallbackRegistry = Registry::load($tmpConfigPath);
ok('registry load falls back to backup after invalid active config', ($fallbackRegistry->apps[0]['slug'] ?? null) === 'old');
array_map('unlink', glob($tmpDir . '/*') ?: []);
rmdir($tmpDir);

echo "Dispatcher\n";
Dispatcher::setHandlersPath(null);
$req = new NormalizedRequest('localhost', '/sb/minimal/v1/health', 'GET', [], [], [], null, null, null, null);
$registry = Registry::load($base . '/config/endpoints.json');
$stub = Dispatcher::dispatch(
    ['id' => 'e-minimal-health', 'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle', 'app_id' => 'minimal-php-app', 'path' => '/health', 'method' => 'GET'],
    $req,
    $registry
);
ok('dispatch stub when no path', $stub['status'] === 200 && ($stub['body']['handler_class'] ?? null) === 'Minimal\\Health');
$handlersPath = $base . '/examples/minimal-handler';
Dispatcher::setHandlersPath($handlersPath);
$live = Dispatcher::dispatch(
    ['id' => 'e-minimal-health', 'handler_class' => 'Minimal\\Health', 'handler_method' => 'handle', 'app_id' => 'minimal-php-app', 'path' => '/health', 'method' => 'GET'],
    $req,
    $registry
);
ok('dispatch invokes handler', $live['status'] === 200 && isset($live['body']['ok']) && $live['body']['service'] === 'minimal-handler');

echo "\n";
if ($fail > 0) {
    echo "FAILED: {$fail} assertion(s)\n";
    exit(1);
}
echo "All assertions passed.\n";
exit(0);
