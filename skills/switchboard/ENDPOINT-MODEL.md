# Endpoint Model

Use this guide when adding endpoints, editing registry data, debugging route misses, or changing matcher behavior.

## Core Registry Entities

- `apps`: app identity, slug, handler code location, enabled state
- `endpoints`: route path, methods, host, enabled state, handler target
- `endpoint_predicates`: match-time gates over path, query, form, JSON, header, and cookie data
- `endpoint_validations`: post-match validation

The canonical endpoint dispatch target is:

- `handler_class`
- `handler_method`

## Matching Order

Runtime matching follows the current PHP implementation:

1. Resolve app scope from `/sb/<app>/<version>/...` or mounted API metadata.
2. Filter by HTTP method membership in `methods[]`.
3. Match endpoint path and capture typed path parameters.
4. Match host, preferring exact host over any-host.
5. Evaluate endpoint predicates.
6. Run post-match validations.
7. Dispatch through `handler_class::handler_method`.

## Canonical URL Shape

Portable runtime route:

```text
/sb/<app_slug>/<version>/<endpoint_path>
```

Example:

```text
/sb/news/v1/articles -> app news, endpoint path /articles
```

Endpoint `path` values do not include `/sb`, app slug, or version.

## Immutable Dynamic App Routing

Production dynamic app mounting is convention-based because Nginx is immutable after image creation.

Rules:

- app slug `foo` maps to URL mount `/foo`
- built frontend lives at `/srv/switchboard/apps/foo/dist`
- app API lane is `/foo/api/*`
- static files under `dist/api` win before PHP
- Switchboard receives only API fallback requests from the reserved API lane

Examples:

```text
/foo                    -> static dist/index.html
/foo/assets/app.js      -> static dist/assets/app.js
/foo/products           -> SPA fallback dist/index.html
/foo/api/products       -> static dist/api/products if present, otherwise endpoint /products for app foo
```

Nginx passes URL-derived metadata:

```nginx
fastcgi_param SWITCHBOARD_MOUNT_APP $app;
fastcgi_param SWITCHBOARD_MOUNT_PATH /$app;
fastcgi_param SWITCHBOARD_MOUNT_API_PREFIX /api;
```

`PathParser::parseMount()` strips `/<app_slug>/api` before matching endpoint paths.

## Anti-Patterns

- Do not create per-app Nginx config for dynamic app mounts.
- Do not assume a public mount can differ from app slug unless a new mapping mechanism is designed.
- Do not make `/foo/products` both a frontend route and a direct API endpoint.
- Do not route missing static assets to PHP outside the reserved API lane.

## References

- `docs/endpoint-schema.md`
- `docs/route-convention.md`
- `docs/deployment-runbook.md`
- `sb/PathParser.php`
- `sb/Matcher.php`
