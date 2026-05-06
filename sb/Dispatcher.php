<?php

declare(strict_types=1);

/**
 * Dispatch a matched request to the handler per docs/php-app-contract.md.
 *
 * Resolves handler from handler_class and handler_method; invokes with
 * SwitchboardRequest and optional SwitchboardContext. Supports PHP classes and
 * SwitchboardAppInterface. When no handler is resolvable, returns a stub response.
 *
 * @link docs/php-app-contract.md PHP app contract (SwitchboardRequest, SwitchboardResponse, SwitchboardAppInterface)
 */

namespace Switchboard\Runtime;

final class Dispatcher
{
    /**
     * Path to directory containing handler scripts (e.g. handlers.php or app-specific includes).
     *
     * @var string|null
     */
    private static ?string $handlersPath = null;

    /**
     * Set the base path used to resolve handler scripts.
     *
     * @param string|null $path Absolute or relative path to handlers directory.
     * @return void
     */
    public static function setHandlersPath(?string $path): void
    {
        self::$handlersPath = $path;
    }

    /**
     * Dispatch a matched endpoint to its handler and return a normalized response.
     *
     * @param array<string, mixed> $endpoint Matched endpoint record (id, app_id, handler_class, handler_method, ...).
     * @param SwitchboardRequest   $request Normalized request from router.
     * @param Registry             $registry Loaded registry (for app context).
     * @return array{status: int, headers: array<string, string>, body: string|array<mixed>}
     */
    public static function dispatch(array $endpoint, SwitchboardRequest $request, Registry $registry): array
    {
        $handlerClass = self::handlerClass($endpoint);
        $handlerMethod = self::handlerMethod($endpoint);
        $callable = self::resolveHandler($endpoint, $registry, $handlerClass, $handlerMethod);
        $context = SwitchboardContext::fromEndpoint(
            $endpoint,
            isset($endpoint['app_id']) ? $registry->getAppById((string) $endpoint['app_id']) : null
        );

        if ($callable !== null) {
            $response = self::invokeHandler($callable, $request, $context);
            if ($response === null) {
                return self::stubResponse($endpoint, $request);
            }
            if (is_array($response) && isset($response['status'], $response['headers'], $response['body'])) {
                return $response;
            }
            if ($response instanceof SwitchboardResponse) {
                return [
                    'status' => $response->getStatus(),
                    'headers' => $response->getHeaders(),
                    'body' => $response->getBody(),
                ];
            }
        }

        return self::stubResponse($endpoint, $request);
    }

    /**
     * Invoke handler (callable or SwitchboardAppInterface) with request and optional context.
     *
     * @param callable|object $callable Handler from resolveHandler.
     * @return array{status: int, headers: array<string, string>, body: mixed}|SwitchboardResponse|null
     */
    private static function invokeHandler(
        callable|object $callable,
        SwitchboardRequest $request,
        SwitchboardContext $context
    ): array|SwitchboardResponse|null {
        if ($callable instanceof SwitchboardAppInterface) {
            return $callable->handle($request, $context);
        }
        if (is_callable($callable)) {
            $ref = new \ReflectionFunction(\Closure::fromCallable($callable));
            if ($ref->getNumberOfParameters() >= 2) {
                return $callable($request, $context);
            }
            return $callable($request);
        }
        return null;
    }

    /**
     * Resolve handler class from SWITCHBOARD_HANDLERS_PATH/handlers.php.
     *
     * Loads handlers.php so demo and local apps can declare classes without a
     * runtime Composer dependency. App-owned Composer autoloaders can also make
     * the class available before dispatch.
     *
     * @param array<string, mixed> $endpoint   Endpoint record (for future app-specific resolution).
     * @param Registry             $registry  Loaded registry.
     * @param string               $handlerClass PHP class name.
     * @param string               $handlerMethod PHP method name.
     * @return callable(SwitchboardRequest, ?SwitchboardContext): array|SwitchboardResponse|object|null
     */
    private static function resolveHandler(array $endpoint, Registry $registry, string $handlerClass, string $handlerMethod): callable|object|null
    {
        $path = self::$handlersPath ?? getenv('SWITCHBOARD_HANDLERS_PATH') ?: null;
        if ($path === null || $path === '') {
            return null;
        }

        $base = realpath($path);
        if ($base !== false) {
            $handlersFile = $base . DIRECTORY_SEPARATOR . 'handlers.php';
            if (is_file($handlersFile) && is_readable($handlersFile)) {
                require_once $handlersFile;
            }
        }

        if (!class_exists($handlerClass)) {
            throw new \RuntimeException("Handler class not found: {$handlerClass}");
        }

        $instance = new $handlerClass();
        if (is_callable([$instance, $handlerMethod])) {
            return [$instance, $handlerMethod];
        }
        if ($handlerMethod === 'handle' && $instance instanceof SwitchboardAppInterface) {
            return $instance;
        }
        if ($handlerMethod === '__invoke' && is_callable($instance)) {
            return $instance;
        }
        throw new \RuntimeException("Handler method not found: {$handlerClass}::{$handlerMethod}");
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private static function handlerClass(array $endpoint): string
    {
        return (string)($endpoint['handler_class'] ?? '');
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private static function handlerMethod(array $endpoint): string
    {
        $method = trim((string)($endpoint['handler_method'] ?? ''));
        return $method === '' ? 'handle' : $method;
    }

    /**
     * Build a stub response when no handler is resolvable.
     *
     * @param array<string, mixed> $endpoint Endpoint record (id, handler_class, handler_method, ...).
     * @param SwitchboardRequest   $request Normalized request.
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>}
     */
    private static function stubResponse(array $endpoint, SwitchboardRequest $request): array
    {
        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Handler-Class' => self::handlerClass($endpoint),
                'X-Handler-Method' => self::handlerMethod($endpoint),
            ],
            'body' => [
                'matched' => true,
                'handler_class' => self::handlerClass($endpoint),
                'handler_method' => self::handlerMethod($endpoint),
                'endpoint_id' => $endpoint['id'],
                'path' => $request->getPath(),
                'method' => $request->getMethod(),
            ],
        ];
    }
}
