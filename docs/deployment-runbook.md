# Deployment runbook — PHP router behind Nginx or Apache

This runbook describes how to run the Switchboard PHP runtime behind Nginx (or Apache) so that **a named runtime script is the internal entry point** and **public URLs do not expose `.php`** (e.g. `/sb/minimal/v1/health` instead of `/switchboard.php?...`).

**Full stack:** For a single "how to run Switchboard v1" (router + WebUI, config, env), see the [root README](../README.md#quick-start).

## Prerequisites

- PHP 8.1+ with PHP-FPM (for Nginx) or `mod_php` (for Apache). CI verifies PHP 8.2 and 8.4 compatibility.
- Nginx with `fastcgi_params` or Apache with `mod_rewrite` and `FallbackResource` (Apache 2.4+)

## Environment variables

The router reads these at runtime:

| Variable | Purpose | Default |
|----------|---------|---------|
| `SWITCHBOARD_CONFIG` | Absolute path to the registry JSON (apps, endpoints, predicates, validations). | `../config/endpoints.json` relative to `sb/` |
| `SWITCHBOARD_HANDLERS_PATH` | Directory containing `handlers.php` (callables keyed by handler name). | (none — stub responses if unset) |

Set them in the web server config (see below), in the PHP-FPM pool `env[]`, or in the systemd unit that starts PHP-FPM.

## Management API protection

Switchboard v1 does not include built-in authentication, authorization, sessions, or audit logging. Treat the management UI and `/api/*` routes as protected control-plane surfaces.

- Public runtime traffic may reach `/sb/...` routes.
- Management UI and `/api/*` routes must be protected by reverse-proxy authentication, a private network, VPN, IP allowlist, or equivalent deployment control.
- Do not expose `/api/*` directly to the public internet without an external access-control layer.

This follows [ADR 0004](decisions/0004-use-external-control-plane-auth-boundary.md).

## Registry freshness and recovery

Switchboard v1 uses `config/endpoints.json` as the source of truth. Management API writes are serialized with a file lock, written through a temp file in the same directory, and atomically renamed into place. Before replacing the active file, the previous registry is saved as `endpoints.json.bak`.

Runtime requests load the active registry for the request being served. A valid saved change is therefore visible on the next request that reads the changed file. If the active registry is invalid but the backup is readable, the runtime falls back to the backup rather than dispatching from a broken registry.

Restarting PHP is not required for normal valid registry changes. Restart or reload PHP-FPM only for deployment-level changes such as code updates, PHP configuration changes, environment variable changes, or handler path changes.

## Nginx + PHP-FPM

### Docroot and entry point

- **Document root:** Point `root` at public static files, such as `frontend/dist` for the operator UI. Do not use `sb/` as the public document root in production.
- **Runtime entry script:** Runtime requests should be handled by `switchboard.php` while preserving the original request URI (e.g. `/sb/minimal/v1/health`) so the runtime can match and dispatch. `index.php` exists only as a compatibility shim.
- **Public URL contract:** The public path and the PHP script filename are separate concerns. Nginx exposes stable generic lanes while internally sending runtime traffic to named PHP scripts. No `.php` appears in public URLs.

### Example config

Example snippets are in **sb/nginx.conf.example**. Summary:

1. **root** — Set to public static files such as `frontend/dist`.
2. **Static lanes** — Serve known static files with `try_files $uri =404` so missing assets do not fall through to Switchboard.
3. **Runtime lane** — Send `/sb/*` to PHP-FPM with `SCRIPT_FILENAME` set to `/path/to/switchboard/sb/switchboard.php` and **REQUEST_URI** set to `$request_uri` (the original path + query).
4. **Management API lane** — Send `/api/*` to `/path/to/switchboard/sb/api.php` and protect this lane with reverse-proxy auth, a private network, VPN, or IP allowlist.
5. **Dynamic app lanes** — A single immutable regex handles every app slug. `/<app>` maps to `/srv/switchboard/apps/<app>/dist`; `/<app>/api/*` first checks for a real static file under `dist/api`, then falls through to Switchboard.
6. **Optional env** — Set `SWITCHBOARD_CONFIG`, `SWITCHBOARD_HANDLERS_PATH`, and mounted app FastCGI params in the relevant PHP lanes, or in the PHP-FPM pool.
7. **Block other .php** — Use `location ~ \.php$ { return 404; }` so only the explicit lanes can execute PHP.

### Dynamic app static frontends

The production image treats Nginx as immutable after image creation. Adding an app must not require a container rebuild, an Nginx edit, a generated Nginx snippet, or an Nginx reload.

Dynamic app routing is therefore convention-based:

- app slug `foo` maps to public mount `/foo`
- built frontend files live under `/srv/switchboard/apps/foo/dist`
- public API calls use `/foo/api/*`
- Switchboard endpoint paths remain app-scoped paths such as `/health` or `/products`

With that convention:

- `/foo` serves `/srv/switchboard/apps/foo/dist/index.html`.
- `/foo/assets/app.js` serves `/srv/switchboard/apps/foo/dist/assets/app.js`.
- `/foo/products` falls back to `/srv/switchboard/apps/foo/dist/index.html` for SPA frontends.
- `/foo/api/products` first checks `/srv/switchboard/apps/foo/dist/api/products`; if no static file exists there, Nginx forwards to `sb/switchboard.php` with `SWITCHBOARD_MOUNT_APP=foo`, `SWITCHBOARD_MOUNT_PATH=/foo`, and `SWITCHBOARD_MOUNT_API_PREFIX=/api`.

To add app `foo`, add or update the Switchboard registry for slug `foo`, place the built frontend under `/srv/switchboard/apps/foo/dist`, and place handler code where `SWITCHBOARD_HANDLERS_PATH` can resolve it. Do not change Nginx or restart the container for the mount to exist.

### Steps

1. Copy `sb/nginx.conf.example` into your Nginx config (e.g. `sites-available/switchboard`).
2. Replace `/path/to/switchboard` with the real path to the Switchboard repo.
3. Replace `fastcgi_pass` with your PHP-FPM socket or `127.0.0.1:9000`.
4. Reload Nginx only for base deployment changes such as server name, aliases, sockets, or base paths. Do not reload Nginx to add apps.
5. Smoke test: `curl -s http://switchboard.local/sb/minimal/v1/health` (or your server name).

## Apache (optional)

### Docroot and entry point

- **DocumentRoot:** Set to `sb/`.
- **FallbackResource:** Use `FallbackResource /switchboard.php` so that any non-file request is served by the named runtime script; the original request URI is preserved. No `.php` in public URLs.

### Example config

Example snippets are in **sb/apache.conf.example**. Summary:

1. **DocumentRoot** — Path to `sb/`.
2. **Directory** — `Require all granted`, `DirectoryIndex switchboard.php`, `FallbackResource /switchboard.php`.
3. **Env** — `SetEnv SWITCHBOARD_CONFIG ...` and `SetEnv SWITCHBOARD_HANDLERS_PATH ...` in the VirtualHost or Directory.
4. If `FallbackResource` is not available (e.g. Apache 2.2), use the commented `RewriteRule` in the example.

### Steps

1. Copy the relevant part of `sb/apache.conf.example` into a VirtualHost.
2. Replace paths and ensure `mod_rewrite` (and optionally `mod_env`) are enabled.
3. Restart or reload Apache.
4. Smoke test as above.

## References

- [sb/README.md](../sb/README.md) — Pipeline, path convention, and handler contract.
- [sb/nginx.conf.example](../sb/nginx.conf.example) — Full Nginx server block.
- [sb/apache.conf.example](../sb/apache.conf.example) — Apache VirtualHost snippet.
