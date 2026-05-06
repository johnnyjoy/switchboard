# 0003: Standardize V1 Routing Semantics

## Status
Accepted; updated for the breaking full endpoint model.

## Context
The original v1 model split route behavior across scalar `method`, `endpoint_parameters`, `endpoint_conditions`, and body validations. That made a full definition of "endpoint" hard to reason about and left POST form and JSON body fields outside route matching.

## Decision
Switchboard v1 uses a breaking endpoint contract:

- `endpoints.method` is replaced by non-empty `endpoints.methods[]`.
- `endpoints.handler_ref` is replaced by explicit `endpoints.handler_class` and `endpoints.handler_method`.
- `endpoint_parameters` and `endpoint_conditions` are superseded by `endpoint_predicates`.
- Path placeholders support `{name}` and `{name:type}`.
- Typed path placeholders produce path predicates and captured `pathParams`.
- Predicate sources are `path`, `query`, `form`, `json`, `header`, and `cookie`.
- Predicate operators are `present`, `absent`, `equals`, `in`, `regex`, and `type`.
- Predicate types are `string`, `integer`, `number`, `boolean`, `date`, `datetime`, and `uuid`.

POST form predicates and JSON predicates are in scope for route matching. Body schema validation remains separate and occurs after an endpoint has matched.

Composer runtime dependencies remain banned. Runtime parsing and evaluation must use PHP built-ins and project-owned code.

## Match Order
1. Enabled apps only.
2. App slug from `/sb/<app_slug>/<version>`.
3. Request method must be in `methods[]`.
4. Path pattern must match and extract path params.
5. Host must match, preferring exact host over any-host.
6. All endpoint predicates must pass.
7. Optional post-match body validation runs.
8. Handler dispatch uses `handler_class::handler_method`.

## Consequences
- API validation rejects invalid method arrays, unsupported path placeholder syntax, unsupported predicates, invalid JSON path names, and duplicate routes with overlapping method sets.
- Runtime and tester must share predicate evaluation so traces match production behavior.
- The UI must expose multi-method endpoints and all predicate sources without returning to the old single-method/query-path-only model.
- Existing local registry data must be migrated or refreshed to use `methods[]`, `endpoint_predicates`, and class/method dispatch targets.

## Non-Goals
- Full route DSL.
- File upload predicates.
- Arbitrary JSONPath expressions.
- Runtime Composer packages.
- IP/User-Agent shortcut predicates in the full endpoint model.
