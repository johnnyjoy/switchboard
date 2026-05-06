# PHP Handler Guide

Use this guide when creating or reviewing application-owned PHP handlers for Switchboard.

## Handler Target

Each endpoint dispatches to:

- `handler_class`: PHP class name, e.g. `News\ListArticles`
- `handler_method`: PHP method name, usually `handle`

The displayed form is `handler_class::handler_method`, for example `Minimal\Health::handle`.

Do not use `handler_ref`; it is stale.

## Handler Location

Current local convention:

- `SWITCHBOARD_HANDLERS_PATH` points to a directory containing `handlers.php`.
- `examples/minimal-handler/handlers.php` is the canonical example.
- Runtime PHP must not depend on Composer-installed third-party packages.

Production loading can evolve, but the contract stays the same: Switchboard resolves the handler in the context of the matched app and invokes the configured method.

## Request Contract

Handlers receive a normalized request, not raw PHP globals.

Use:

- `getHost()`
- `getPath()`
- `getMethod()`
- `getQuery()`
- `getHeaders()`
- `getCookies()`
- `getBody()`
- `getForm()`
- `getJson()`
- `getPathParams()`

Avoid:

- `$_GET`
- `$_POST`
- `$_SERVER`
- `php://input`
- framework-specific request objects

## Response Contract

Handlers return either a normalized response array or `SwitchboardResponse`:

```php
return [
    'status' => 200,
    'headers' => ['Content-Type' => 'application/json'],
    'body' => ['ok' => true],
];
```

If `body` is an array, the runtime JSON-encodes it and sets `Content-Type: application/json` when missing.

## Minimal Shape

```php
namespace Minimal;

use Switchboard\Runtime\SwitchboardRequest;

final class Health
{
    public function handle(SwitchboardRequest $request): array
    {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['ok' => true],
        ];
    }
}
```

## References

- `docs/php-app-contract.md`
- `docs/php-app-contract.md`
- `examples/minimal-handler/handlers.php`
- `sb/Dispatcher.php`
- `sb/SwitchboardContext.php`
