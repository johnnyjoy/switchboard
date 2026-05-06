# Switchboard

Switchboard is a self-hosted HTTP endpoint manager and PHP routing runtime. Define apps and endpoints in a WebUI, store them in a JSON registry, and route matching requests to application-owned PHP handlers.

The project is intentionally small: Switchboard owns endpoint definitions, matching, validation, and dispatch. Your apps own business logic.

## What It Includes

- PHP runtime for `/sb/<app>/<version>/...` routes
- React/MUI WebUI for app and endpoint management
- JSON registry at `config/endpoints.json`
- PHP handler dispatch through `handler_class::handler_method`
- Nginx/Apache deployment examples
- Minimal PHP handler example and smoke test

## Requirements

- PHP 8.1+; CI verifies PHP 8.2 and 8.4
- Node.js 20+
- npm or pnpm for frontend development
- Composer only for PHP test tooling

Runtime PHP code must not depend on Composer-installed third-party packages.

## Quick Start

From the repository root, start the PHP runtime:

```bash
php -S localhost:8080 -t . scripts/php-router.php
```

Smoke test the minimal handler:

```bash
curl -s http://localhost:8080/sb/minimal/v1/health
```

Expected response:

```json
{"ok":true,"service":"minimal-handler"}
```

The local router sets `SWITCHBOARD_CONFIG` and `SWITCHBOARD_HANDLERS_PATH` for the bundled example. It routes `/api/*` to the management API and other paths to `sb/switchboard.php`; it does not serve arbitrary static files directly.

## Run The WebUI

Run the PHP backend in one terminal:

```bash
php -S localhost:8080 -t . scripts/php-router.php
```

Run the frontend in another terminal:

```bash
pnpm dev
```

Open `http://localhost:3083`. The Vite dev server proxies `/api` to the PHP backend on port 8080.

If the API runs elsewhere:

```bash
CONTROL_PLANE_URL=http://127.0.0.1:8080 pnpm dev
```

## Test

```bash
# PHP runtime tests without Composer
php tests/php/run-standalone.php

# PHP unit and integration tests
composer install
vendor/bin/phpunit

# PHP built-in server smoke test
./scripts/smoke-php.sh

# Frontend tests and build
cd frontend
npm run test -- --run
npm run build
```

## Configuration

| Variable | Used by | Default | Description |
|----------|---------|---------|-------------|
| `SWITCHBOARD_CONFIG` | Router | `config/endpoints.json` | Registry JSON with apps, endpoints, predicates, and validations. |
| `SWITCHBOARD_HANDLERS_PATH` | Router | unset | Directory containing `handlers.php`. If unset, matched routes return stub responses. |

Example:

```bash
SWITCHBOARD_CONFIG=/path/to/endpoints.json SWITCHBOARD_HANDLERS_PATH=examples/minimal-handler php -S localhost:8080 -t . scripts/php-router.php
```

## Architecture At A Glance

Switchboard has two owned subsystems and one external boundary:

- **Control plane:** React WebUI and `/api/*` management API.
- **Routing runtime:** PHP request pipeline: normalize, match, validate, dispatch, respond.
- **Application code:** PHP handlers and business logic outside Switchboard.

Canonical runtime URLs use:

```text
/sb/<app_slug>/<version>/<endpoint_path>
```

Production deployments can also use mounted app paths such as `/<app_slug>/api/*`. Mounted app routing is convention-based and does not require per-app Nginx config changes.

## Documentation

- [docs/README.md](docs/README.md) - Active documentation index.
- [docs/architecture.md](docs/architecture.md) - System boundaries, concepts, and request pipeline.
- [docs/endpoint-schema.md](docs/endpoint-schema.md) - Registry schema, matching order, predicates, validations, and path params.
- [docs/php-app-contract.md](docs/php-app-contract.md) - PHP handler request/response contract.
- [docs/route-convention.md](docs/route-convention.md) - Canonical routes and mounted app paths.
- [docs/deployment-runbook.md](docs/deployment-runbook.md) - Nginx/Apache deployment and management API protection.
- [docs/decisions](docs/decisions) - Accepted architecture decisions.
- [CONTRIBUTING.md](CONTRIBUTING.md) - Development workflow and standards.

Component docs:

- [sb/README.md](sb/README.md) - PHP runtime details.
- [frontend/README.md](frontend/README.md) - WebUI development.
- [examples/minimal-handler/README.md](examples/minimal-handler/README.md) - Minimal PHP app example.

## Status

Switchboard is a focused v1 project. The current durable contract is PHP runtime dispatch through `handler_class` and `handler_method`, a JSON registry, and reverse-proxy deployment with protected management routes.
