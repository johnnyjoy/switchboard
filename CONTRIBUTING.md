# Contributing

This project favors small, explicit changes that keep Switchboard easy to run and reason about.

## Development Workflow

1. Start from the root [README.md](README.md) and the relevant component README.
2. Keep changes scoped to one subsystem when possible: frontend, management API, PHP runtime, docs, or examples.
3. Update docs when behavior, setup, deployment, or public contracts change.
4. Run the most focused useful checks before handing off work.

## Validation Commands

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

## PHP Standards

- Use `declare(strict_types=1);` in first-party PHP files.
- Prefer explicit parameter and return types.
- Use PHPDoc for array shapes and contracts that native PHP cannot express.
- Decode JSON with explicit error handling.
- Do not suppress runtime warnings with `@` unless the surrounding code explains the operator-facing behavior and fails closed.
- Runtime PHP must not add Composer-installed third-party dependencies. Composer packages are allowed for tests and tools only.

## Runtime And Routing Rules

- `sb/switchboard.php` is the named runtime entrypoint.
- Public runtime URLs must stay path-only; do not expose `.php` routes as the public contract.
- Endpoint dispatch uses `handler_class` and `handler_method`.
- Endpoint methods are stored as `methods[]`, not scalar `method`.
- Match-time gates belong in `endpoint_predicates`.
- Post-match checks belong in `endpoint_validations`.
- Keep canonical `/sb/<app>/<version>/...` behavior working.

## Dynamic App Mounting

Production dynamic app mounting is convention-based:

- App slug `foo` maps to public mount `/foo`.
- Built frontend files live under `/srv/switchboard/apps/foo/dist`.
- `/<app_slug>/api/*` checks static files under `dist/api` first, then falls back to Switchboard.
- Adding an app must not require a container rebuild, Nginx edit, generated per-app snippet, or Nginx reload.

## Frontend Standards

- Use React with TypeScript.
- Use MUI components consistently.
- Keep component structure predictable.
- Avoid ad-hoc styling when an existing design-system pattern is available.

## Documentation Standards

- Describe the current product and runtime contracts.
- Do not link to deleted plans, obsolete status docs, or project memory files.
- Prefer README, CONTRIBUTING, component READMEs, and durable reference docs over one-off planning documents.
- When docs disagree with code, update the docs or record the code gap before continuing.
