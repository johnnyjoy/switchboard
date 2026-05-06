# Minimal PHP Example App

One app in `examples/` implementing the [PHP app contract](../../docs/php-app-contract.md): **single endpoint**, class handler declared so the Switchboard PHP router can resolve and invoke it.

## Contract summary

- **Single endpoint:** `GET /health` → `Minimal\Health::handle`.
- **Request:** The runtime passes a `SwitchboardRequest` — host, path, method, query, headers, body, parsed form/JSON, and path params. No `$_GET`/`$_POST`/`$_SERVER` in the handler.
- **Response:** Handler returns an array with `status`, `headers`, and `body` (string or array; array is JSON-encoded by the runtime).
- **Resolution:** The router loads `handlers.php` from the directory set in `SWITCHBOARD_HANDLERS_PATH`, instantiates `Minimal\Health`, and invokes `handle`.

## Layout

```
examples/minimal-handler/
├── README.md
└── handlers.php   # Declares Minimal\Health
```

## How to run

1. **Config:** Ensure the endpoint registry has an app and one endpoint for this handler. Example (see `config/endpoints.json`): app with `slug: "minimal"`, one endpoint with `path: "/health"`, `methods: ["GET"]`, `handler_class: "Minimal\\Health"`, and `handler_method: "handle"`.

2. **Handlers path:** Point the PHP runtime at this directory:
   ```bash
   export SWITCHBOARD_HANDLERS_PATH=/path/to/switchboard/examples/minimal-handler
   ```

3. **Request:** With the route convention `/sb/<app>/<version>/<path>`, call:
   ```bash
   curl http://localhost/sb/minimal/v1/health
   ```
   The router strips `/sb/minimal/v1/`, matches path `/health`, resolves `Minimal\Health::handle` from `handlers.php`, invokes it with the normalized request, and returns the response.

## References

- [PHP app contract](../../docs/php-app-contract.md) — request/response shape, class/method resolution, lifecycle.
- [Route convention](../../docs/route-convention.md) — URL prefix `/sb/<app>/<version>/`.
