<?php

declare(strict_types=1);

/**
 * Optional context the runtime may pass to handlers (app slug, endpoint id, dispatch target).
 *
 * Handlers that need to know which app/endpoint they are serving can receive
 * this in addition to the request. See docs/php-app-contract.md.
 *
 * @link docs/php-app-contract.md PHP app contract
 */

namespace Switchboard\Runtime;

final class SwitchboardContext
{
    /**
     * @param array<string, mixed>|null $routeConditions Matched endpoint's conditions config (query, headers, cookies, ip_allow, ip_deny, user_agent) for handler use.
     */
    public function __construct(
        public readonly ?string $appSlug = null,
        public readonly ?string $appId = null,
        public readonly ?string $endpointId = null,
        public readonly ?string $handlerClass = null,
        public readonly ?string $handlerMethod = null,
        public readonly ?string $appPath = null,
        public readonly ?array $routeConditions = null,
    ) {
    }

    /**
     * Build context from a matched endpoint and app record.
     *
     * @param array<string, mixed>      $endpoint Matched endpoint (id, handler_class, handler_method, app_id, conditions, ...).
     * @param array<string, mixed>|null  $app      App record (id, slug, app_path, ...) or null.
     */
    public static function fromEndpoint(array $endpoint, ?array $app = null): self
    {
        $appId = isset($endpoint['app_id']) ? (string) $endpoint['app_id'] : null;
        $endpointId = isset($endpoint['id']) ? (string) $endpoint['id'] : null;
        $handlerClass = isset($endpoint['handler_class']) ? (string) $endpoint['handler_class'] : null;
        $handlerMethod = isset($endpoint['handler_method']) ? (string) $endpoint['handler_method'] : null;
        $routeConditions = isset($endpoint['conditions']) && is_array($endpoint['conditions']) ? $endpoint['conditions'] : null;
        $appSlug = null;
        $appPath = null;
        if (is_array($app)) {
            $appSlug = isset($app['slug']) ? (string) $app['slug'] : null;
            $appPath = isset($app['app_path']) ? (string) $app['app_path'] : null;
        }
        return new self($appSlug, $appId, $endpointId, $handlerClass, $handlerMethod, $appPath, $routeConditions);
    }
}
