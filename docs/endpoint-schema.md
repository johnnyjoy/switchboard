# Endpoint Registry Schema

This document defines the breaking full endpoint model for Switchboard v1. An endpoint is the final route-to-code contract: app, host, path pattern, one or more HTTP methods, request predicates, optional body validation, and a PHP dispatch target.

## Overview

The registry stores:

| Entity | Purpose |
|--------|---------|
| `apps` | Top-level grouping and handler-code location |
| `endpoints` | Route identity: host, path pattern, `methods[]`, PHP class/method dispatch target, enabled state |
| `endpoint_predicates` | Match-time gates over path, query, form, JSON, headers, and cookies |
| `endpoint_validations` | Optional post-match body validation; not used to choose a route |

Composer runtime dependencies remain banned. Runtime parsing, matching, predicate evaluation, and validation must use PHP built-ins or project code only.

## `apps`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | no | Primary key |
| `slug` | string | no | URL-safe app identifier used in `/sb/<app_slug>/<version>/...` |
| `name` | string | no | Human-readable name |
| `description` | string | yes | Optional description |
| `app_path` | string | yes | Handler code location |
| `enabled` | boolean | no | Disabled apps remove all child endpoints from matching |
| `created_at` | timestamp | no | Creation time |
| `updated_at` | timestamp | no | Last update time |

## `endpoints`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | no | Primary key |
| `app_id` | UUID | no | Foreign key to `apps.id` |
| `name` | string | no | Human-readable route name |
| `host` | string | yes | Optional host match; null means any host |
| `path` | string | no | Path pattern relative to `/sb/<app_slug>/<version>` |
| `methods` | string[] | no | Non-empty HTTP method list, normalized uppercase |
| `handler_class` | string | no | PHP class to instantiate, e.g. `News\CreateArticle` |
| `handler_method` | string | no | PHP method to invoke; defaults to `handle`, may be `__invoke` for invokable classes |
| `enabled` | boolean | no | Disabled endpoints are excluded from matching |
| `created_at` | timestamp | no | Creation time |
| `updated_at` | timestamp | no | Last update time |

Route uniqueness is `(app_id, host, path, overlapping methods)`. Two endpoints for the same app/host/path may coexist only when their `methods[]` sets do not overlap.

## `endpoint_predicates`

Predicates are match-time gates. If any predicate fails, the endpoint candidate does not dispatch.

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | UUID | no | Primary key |
| `endpoint_id` | UUID | no | Foreign key to `endpoints.id` |
| `source` | enum | no | `path`, `query`, `form`, `json`, `header`, or `cookie` |
| `name` | string | no | Field name. For JSON, use dot paths such as `user.id` |
| `op` | enum | no | `present`, `absent`, `equals`, `in`, `regex`, or `type` |
| `value` | string or string[] | yes | Required for `equals`, `in`, and `regex` |
| `value_type` | enum | yes | Required for `type`: `string`, `integer`, `number`, `boolean`, `date`, `datetime`, or `uuid` |
| `created_at` | timestamp | no | Creation time |
| `updated_at` | timestamp | no | Last update time |

POST form predicates and JSON predicates are in scope:

- `form` reads `application/x-www-form-urlencoded` fields and multipart form fields exposed by PHP.
- `json` reads parsed JSON request bodies and supports a deliberately small dot-path syntax.
- Body predicates select the route. Body schema validation, when present, happens after a route has matched.

All predicates attached to an endpoint are combined with AND. Predicate failure means "no match"; the handler is not invoked.

Supported predicate operators:

| Operator | Meaning |
|----------|---------|
| `present` | Source has a value for `name` |
| `absent` | Source has no value for `name` |
| `equals` | Actual value equals `value` exactly |
| `in` | Actual value is one of the `value` list entries |
| `regex` | Actual value matches `value` as a PHP regex |
| `type` | Actual value satisfies `value_type` |

JSON paths use a deliberately small dot-path syntax such as `user.id`, `payload.event`, or `metadata.source`. Array traversal and arbitrary JSONPath expressions are out of scope until there is a concrete need.

## `endpoint_validations`

Validations run only after an endpoint has matched. They are not route-selection gates and must not be used to choose between endpoints.

Use validations for post-match input checks such as required request fields or body shape checks. If validation fails, the runtime returns a validation error and does not dispatch to the handler.

## Path Variables

Endpoint paths may use typed placeholders:

- `/articles/{id:integer}`
- `/reports/{date:date}`
- `/users/{userId:uuid}/sessions/{sessionId}`

The untyped form `{name}` captures a string. Typed placeholders produce derived `path` predicates with `op: "type"` and the declared `value_type`. Captured values are passed to handlers as `pathParams`.

Placeholders consume exactly one path segment; they do not match slashes or empty segments. Literal routes win over parameterized routes when both could match. When multiple candidates remain equivalent, endpoint id provides deterministic ordering.

Handlers receive path params as strings even when the values pass numeric or date type checks; handlers can cast them if needed.

## Runtime Consumption

The runtime loads enabled apps and enabled endpoints, attaches each endpoint's predicates, and evaluates requests in this order:

1. App slug from the `/sb/<app_slug>/<version>` prefix.
2. Method membership in `methods[]`.
3. Path pattern match and path parameter extraction.
4. Host match, preferring exact host over any-host.
5. Predicate evaluation across path, query, form, JSON, header, and cookie sources.
6. Optional post-match body validation.
7. Handler dispatch through `handler_class::handler_method`.

Tester traces should report path params and predicate failures so operators can see why a candidate did not dispatch.

## Minimal Example

```json
{
  "apps": [
    { "id": "minimal", "slug": "minimal", "name": "Minimal", "app_path": null, "enabled": true }
  ],
  "endpoints": [
    {
      "id": "health",
      "app_id": "minimal",
      "name": "Health",
      "host": null,
      "path": "/health",
      "methods": ["GET", "HEAD"],
      "handler_class": "Minimal\\Health",
      "handler_method": "handle",
      "enabled": true
    }
  ],
  "endpoint_predicates": [],
  "endpoint_validations": []
}
```

The full local sample registry lives in `config/endpoints.json`.
