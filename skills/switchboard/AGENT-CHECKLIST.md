# Agent Checklist

Use this checklist before and after modifying Switchboard.

## Add A PHP Handler

- Confirm endpoint has `handler_class` and `handler_method`.
- Put handler code where the runtime can load it.
- Use `SwitchboardRequest`, not raw PHP globals.
- Return a normalized response array or `SwitchboardResponse`.
- Verify with `php tests/php/run-standalone.php`.

## Add Or Edit An Endpoint

- Keep endpoint `path` app-scoped, e.g. `/health`.
- Use `methods[]`, not scalar `method`.
- Use `endpoint_predicates` for match-time gates.
- Use `endpoint_validations` for post-match validation.
- Preserve route uniqueness: app, host, normalized path, overlapping methods.

## Debug A Route Miss

- Check app slug and enabled state.
- Check whether the request is canonical `/sb/<app>/<version>/...` or mounted `/<app>/api/...`.
- Confirm Nginx mount metadata reaches PHP for mounted API paths.
- Check method, path pattern, host, and predicates in that order.
- Check whether a static file under `dist/api` is intentionally winning before PHP.

## Change Runtime Or Dispatcher Code

- Keep runtime Composer dependency-free.
- Keep `sb/switchboard.php` as the named runtime entrypoint.
- Preserve `handler_class::handler_method`.
- Keep canonical `/sb/*` behavior working.
- Keep mounted API behavior convention-based.

## Change Nginx Or Deployment Docs

- Treat Nginx as immutable after image creation.
- Do not describe per-app generated snippets for dynamic app mounts.
- Use the convention `/srv/switchboard/apps/<slug>/dist`.
- State that app slug maps to public mount `/<slug>`.
- State that `/<slug>/api/*` checks static files first, then falls back to Switchboard.
- Do not say adding apps requires container rebuilds, Nginx edits, or Nginx reloads.

## Verification Matrix

- PHP runtime or registry behavior:
  - `php tests/php/run-standalone.php`
  - `vendor/bin/phpunit`
- PHP syntax when editing PHP:
  - `php -l <file>`
- Frontend changes:
  - `npm run test -- --run`
  - `npm run build`
- Docs or skills:
  - read lints for touched Markdown
  - search for stale terms listed below

## Stale Terms To Reject

- `handler_ref`
- `minimal-handler-php`
- `handlers.js`
- `index.php` as the routing model
- per-app Nginx snippets for dynamic app mounts
