---
name: switchboard
description: Build, review, and debug Switchboard PHP runtime, endpoint registry, handler, and immutable Nginx dynamic-app routing work.
---

# Switchboard

Use this skill when working on Switchboard runtime code, PHP handlers, endpoint registry data, route matching, deployment docs, or agent guidance for this project.

## Quick Start

1. Read `README.md`, `sb/README.md`, and the most relevant doc:
   - handlers: `docs/php-app-contract.md`
   - endpoint model: `docs/endpoint-schema.md`, `docs/route-convention.md`
   - deployment/static routing: `docs/deployment-runbook.md`, `sb/nginx.conf.example`
2. Identify the surface: runtime, management API, registry, frontend, handler app code, deployment docs, or skill/docs.
3. Preserve current contracts before editing.
4. Run focused verification from `AGENT-CHECKLIST.md`.

## Hard Rules

- Do not treat `index.php` as the routing model. `sb/switchboard.php` is the named runtime entrypoint; public URLs stay path-only.
- Do not reintroduce `handler_ref`. Runtime dispatch uses `handler_class` and `handler_method`.
- Do not use JavaScript handler examples as current behavior. The canonical example is PHP under `examples/minimal-handler/handlers.php`.
- Do not add runtime Composer dependencies. Composer packages are allowed for tests/tools only.
- Do not make agents or docs generate per-app Nginx snippets for dynamic apps.
- Do not put PHP before flat files except as fallback inside the reserved app API lane.
- Do not claim dynamic app mounting works unless app slug, URL mount, filesystem layout, and registry entries line up.

## Immutable Dynamic App Routing

Nginx is immutable after image creation. Adding an app must not require container rebuilds, Nginx edits, generated snippets, or Nginx reloads.

Convention:

- app slug `foo` maps to public mount `/foo`
- frontend files live at `/srv/switchboard/apps/foo/dist`
- frontend routes use `/foo/*`
- API routes use `/foo/api/*`
- endpoint paths stored in Switchboard stay app-scoped, e.g. `/health` or `/products`

Request ownership:

- `/foo` -> `/srv/switchboard/apps/foo/dist/index.html`
- `/foo/assets/app.js` -> static file from `dist/assets`
- `/foo/products` -> SPA fallback to `dist/index.html`
- `/foo/api/products` -> static `dist/api/products` if present, otherwise Switchboard app `foo`, endpoint path `/products`

## Supporting Guides

- `PHP-HANDLER-GUIDE.md`: PHP handler implementation.
- `ENDPOINT-MODEL.md`: registry, matching, predicates, and mounted API paths.
- `AGENT-CHECKLIST.md`: task checklists and verification commands.
