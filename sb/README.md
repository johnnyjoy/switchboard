# Switchboard PHP Router

Part of Switchboard v1. For full-stack run instructions (router + WebUI, config, env), see the [root README](../README.md#quick-start).

Minimal PHP runtime that loads endpoint definitions, normalizes the request, parses the `/sb/<app>/<version>/` path convention, matches requests, validates input, and dispatches to PHP handlers. Compatible with Apache and Nginx (and other reverse proxies).

## Pipeline

1. **Load** — Read full registry from JSON (apps, endpoints, endpoint predicates, endpoint validations). Only enabled app/endpoint are active.
2. **Normalize** — Build normalized request from `$_SERVER` and `php://input` (server-agnostic): host, path, method, query, headers, body, contentType, remoteAddr, forwardedFor.
3. **Path parsing** — If path starts with `/sb/<app>/<version>/`, extract app slug, version, and inner path for matching. Otherwise use full path.
4. **Match** — Scope by app, check request method against `methods[]`, match path and path params, match host, then evaluate `endpoint_predicates`. When prefix is used, only endpoints for that app are considered; path is the inner path (segment after `/sb/<app>/<version>/`). Conflict resolution is deterministic by endpoint id.
5. **Validate** — Apply post-match endpoint validations; 400 on invalid.
6. **Dispatch** — Resolve `handler_class::handler_method` via `SWITCHBOARD_HANDLERS_PATH`/`handlers.php`; invoke with normalized request; return normalized response. Stub only when no handlers path is configured.
7. **Respond** — Send status, headers, body (array body serialized as JSON).

## Configuration

- **SWITCHBOARD_CONFIG** — Path to registry JSON (default: `../config/endpoints.json` relative to this directory).
- **SWITCHBOARD_HANDLERS_PATH** — Directory containing `handlers.php`. The file is included so app-owned classes such as `Minimal\Health` can be declared without adding production Composer dependencies. Handlers receive a `SwitchboardRequest` (runtime passes `NormalizedRequest`) and optionally `SwitchboardContext`; return an array `['status' => int, 'headers' => array, 'body' => string|array]` or a `SwitchboardResponse` (e.g. `NormalizedResponse`). Handlers may implement `SwitchboardAppInterface::handle()`. See [docs/php-app-contract.md](../docs/php-app-contract.md) §7.

Config format is documented in [docs/endpoint-schema.md](../docs/endpoint-schema.md): `apps`, `endpoints`, `endpoint_predicates`, and `endpoint_validations`.

## Path convention `/sb/<app>/<version>/`

- **Prefixed requests** — e.g. `/sb/news/v1/articles`. App slug `news`, version `v1`, inner path `/articles`. Only endpoints for app with slug `news` are considered; endpoint `path` is matched against the inner path (`/articles`). So for this URL the registry should have an endpoint with `path: "/articles"` for the news app.
- **Non-prefixed requests** — e.g. `/news/articles`. Full path is used for matching; endpoint `path` is the full path (`/news/articles`).

## Handlers

Create a `handlers.php` in the directory pointed to by `SWITCHBOARD_HANDLERS_PATH`. Define the PHP classes referenced by endpoint `handler_class` values. Type-hint `SwitchboardRequest` (or `NormalizedRequest`); optionally accept `SwitchboardContext` as second argument:

```php
<?php

namespace News;

use Switchboard\Runtime\SwitchboardRequest;
use Switchboard\Runtime\NormalizedResponse;

final class ListArticles
{
    public function handle(SwitchboardRequest $req): array
    {
        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => ['articles' => []],
        ];
    }
}

final class CreateArticle
{
    public function handle(SwitchboardRequest $req): NormalizedResponse
    {
        return new NormalizedResponse(201, ['Content-Type' => 'application/json'], ['id' => '1']);
    }
}
```

Request and response shape: see [docs/php-app-contract.md](../docs/php-app-contract.md). Response arrays use `status`, `headers`, and `body` (string or array; array is JSON-encoded by the runtime).

## Minimal runnable (PHP built-in server)

From the repo root, run:

```bash
php -S localhost:8080 -t . scripts/php-router.php
```

Then smoke test: `curl -s http://localhost:8080/sb/minimal/v1/health` — expect 200 and JSON `{"ok":true,"service":"minimal-handler"}`. Env vars are set by the router so no `.env` is required. See [scripts/smoke-php.sh](../scripts/smoke-php.sh) for an automated smoke test.

**Tests:** From repo root: (1) `php tests/php/run-standalone.php` — PathParser, Matcher, Validator, Dispatcher (no Composer); (2) `composer install && vendor/bin/phpunit` — unit + integration (requires PHP ext-dom); (3) `./scripts/smoke-php.sh` — PHP built-in server + curl. CI: [.github/workflows/ci.yml](../.github/workflows/ci.yml) runs the above on push/PR.

## Deployment (no .php in URLs)

The runtime is the **single entry point** for routed traffic: requests are sent internally to `switchboard.php`; public URLs never expose `.php` (e.g. `/sb/minimal/v1/health`). `index.php` remains only as a compatibility shim for older deployments.

- **Nginx** — Use explicit lanes so static files stay in Nginx and only selected dynamic paths reach PHP. Example: [nginx.conf.example](nginx.conf.example). Serve public static files from a public docroot, route `/sb/*` to `switchboard.php`, route `/api/*` to `api.php`, and reserve mounted app API lanes such as `/foo/api/*` for Switchboard.
- **Apache** — `DocumentRoot` → `sb/`, `FallbackResource /switchboard.php`. Example: [apache.conf.example](apache.conf.example).

**Env vars** (see [docs/deployment-runbook.md](../docs/deployment-runbook.md)): `SWITCHBOARD_CONFIG` (path to registry JSON), `SWITCHBOARD_HANDLERS_PATH` (directory containing `handlers.php`). Set in the server config, PHP-FPM pool, or systemd.

## Example requests

```bash
# Match GET /news/articles (non-prefixed)
curl -s http://localhost/news/articles

# Match POST /news/articles
curl -s -X POST http://localhost/news/articles -H "Content-Type: application/json" -d '{}'

# Prefixed: match GET /sb/news/v1/articles (endpoint path /articles for app news)
curl -s http://localhost/sb/news/v1/articles

# No match → 404
curl -s http://localhost/unknown
```

## References

- [docs/deployment-runbook.md](../docs/deployment-runbook.md) — Nginx/Apache setup, env vars, docroot, and example configs.
- [docs/architecture.md](../docs/architecture.md) — Request pipeline and boundaries.
- [docs/endpoint-schema.md](../docs/endpoint-schema.md) — Registry schema.
- [docs/php-app-contract.md](../docs/php-app-contract.md) — Normalized request/response and PHP handler resolution.
