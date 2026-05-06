# PHP App Contract

This document defines the contract between the Switchboard PHP routing runtime and application-owned PHP handlers.

## Runtime Contract

The PHP runtime receives HTTP from a reverse proxy or the PHP built-in server, normalizes the request, matches an endpoint, validates input, and dispatches to the endpoint's `handler_class::handler_method`.

Handlers are application-owned code. They do not receive raw `$_GET`, `$_POST`, `$_SERVER`, or framework request objects. They receive a normalized `SwitchboardRequest` and return either a normalized response array or `SwitchboardResponse`.

Execution is in-process for v1. There is no subprocess, RPC, stdin/stdout, or JSON wire format between the runtime and the handler.

## Handler Input

Handlers should type-hint `Switchboard\Runtime\SwitchboardRequest`. The current runtime passes `NormalizedRequest`, which implements that interface.

| Method | Description |
|--------|-------------|
| `getHost()` | Request host. May be empty. |
| `getPath()` | Matched request path after the `/sb/<app>/<version>` prefix or mounted API prefix. |
| `getPathParams()` | Path parameters extracted from placeholders such as `{id}` or `{id:integer}`. |
| `getMethod()` | HTTP method, normalized uppercase. |
| `getQuery()` | Parsed query string values. |
| `getHeaders()` | Request headers normalized by the runtime. |
| `getCookies()` | Request cookies. |
| `getBody()` | Raw body string. |
| `getForm()` | Parsed form fields for form-compatible content types. |
| `getJson()` | Parsed JSON body when parsing succeeds. |
| `getContentType()` | Request `Content-Type` when present. |
| `getRemoteAddr()` | Client IP when available. |
| `getForwardedFor()` | `X-Forwarded-For` when available. |

For JSON bodies, prefer `getJson()` when structured data is needed. Use `getBody()` only when the raw payload matters.

## Handler Output

Handlers return a normalized response array:

| Key | Type | Description |
|-----|------|-------------|
| `status` | int | HTTP status code. |
| `headers` | array | Response headers. Values are strings. |
| `body` | string or array | Response body. Arrays are JSON-encoded by the runtime. |

Handlers may also return `SwitchboardResponse`. If a handler throws, the runtime returns a server error and may log the exception.

## Dispatch Target

Each endpoint stores the PHP target explicitly:

- `handler_class`: namespaced PHP class such as `Minimal\Health`
- `handler_method`: method name such as `handle` or `__invoke`

The runtime resolves the target in the matched app's context. The app's `app_path` or configured handler root tells the runtime where application code lives.

Current local convention: `SWITCHBOARD_HANDLERS_PATH` points to a directory containing `handlers.php`. The runtime includes that file, instantiates the configured class, and invokes the configured method with `SwitchboardRequest` and, when accepted, `SwitchboardContext`.

## PHP Runtime Types

| Type | Description |
|------|-------------|
| `SwitchboardRequest` | Interface for normalized request data. |
| `NormalizedRequest` | Runtime implementation of `SwitchboardRequest`. |
| `SwitchboardResponse` | Interface for normalized responses. |
| `NormalizedResponse` | Runtime implementation of `SwitchboardResponse`. |
| `SwitchboardContext` | Optional context with app slug, app id, endpoint id, handler target, and app path. |
| `SwitchboardAppInterface` | Optional handler class interface: `handle(SwitchboardRequest $request, ?SwitchboardContext $context = null): SwitchboardResponse|array`. |

## Lifecycle

1. Request arrives at the reverse proxy or PHP built-in server.
2. The request reaches `sb/switchboard.php`.
3. Runtime normalizes HTTP into `SwitchboardRequest`.
4. Runtime scopes and matches an endpoint from the registry.
5. Runtime applies post-match validation.
6. Runtime resolves `handler_class::handler_method`.
7. Runtime invokes the handler in-process.
8. Runtime converts the normalized response to HTTP.

## Minimal Example

The minimal app in [examples/minimal-handler](../examples/minimal-handler/) declares `Minimal\Health`. With `SWITCHBOARD_HANDLERS_PATH=examples/minimal-handler`, a request to `/sb/minimal/v1/health` dispatches to `Minimal\Health::handle`.

## References

- [architecture.md](architecture.md) - Runtime boundaries and request pipeline.
- [endpoint-schema.md](endpoint-schema.md) - Registry schema and dispatch target fields.
- [route-convention.md](route-convention.md) - Canonical and mounted route shapes.
- [../sb/README.md](../sb/README.md) - PHP runtime details and local commands.
