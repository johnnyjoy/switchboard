# Switchboard Architecture

Switchboard is a self-hosted endpoint management and routing system. It lets operators define apps and HTTP endpoints in a WebUI, stores those definitions in a registry, and routes matching requests to application-owned PHP handlers.

Switchboard is not a general web framework, workflow builder, or place to store business logic. It owns endpoint definitions, matching, validation, and dispatch boundaries; apps own their handlers and dependencies.

## System Parts

| Part | Role | Owned by Switchboard |
|------|------|----------------------|
| Control plane | React WebUI and management API for app and endpoint registry changes | Yes |
| PHP routing runtime | Request path: normalize, match, validate, dispatch, respond | Yes |
| Application code | Handler classes, business logic, app dependencies, app tests | No |

The control plane and runtime use the same file-backed registry, `config/endpoints.json`. Registry writes are atomic, and runtime requests read the active registry on demand.

## Core Concepts

An **app** groups related endpoints and gives the runtime an application scope. Each app has a `slug`, display metadata, enabled state, and optional `app_path` that points to handler code or a deployable unit.

An **endpoint** is a declarative route-to-code contract. It belongs to one app and defines:

- optional `host`
- app-scoped `path`
- one or more HTTP `methods`
- match-time `endpoint_predicates`
- optional post-match `endpoint_validations`
- PHP dispatch target: `handler_class` and `handler_method`
- enabled state

A **handler** is application-owned PHP code. Switchboard stores only the class and method reference, then invokes the resolved callable with a normalized `SwitchboardRequest`. Handlers return a normalized response array or `SwitchboardResponse`.

## Request Pipeline

The routing runtime is the only Switchboard component in the production request path:

1. **Normalize** the incoming HTTP request into a server-agnostic request object.
2. **Scope** by app slug from `/sb/<app_slug>/<version>/...` or mounted app metadata.
3. **Match** enabled endpoints by `methods[]`, path and path params, host, then `endpoint_predicates`.
4. **Validate** matched input with `endpoint_validations`.
5. **Dispatch** to `handler_class::handler_method` in the app context.
6. **Respond** by converting the normalized handler response to HTTP.

```text
Client
  -> Reverse proxy
  -> Switchboard PHP runtime
     -> load registry
     -> normalize request
     -> match endpoint
     -> validate input
     -> dispatch app handler
     -> return response
  -> Reverse proxy
  -> Client
```

## Boundaries

| Switchboard owns | Application code owns |
|------------------|-----------------------|
| App and endpoint registry | Handler implementation |
| Management WebUI and API | Business logic |
| Request normalization and route matching | App dependencies |
| Predicate evaluation and validation | App tests |
| Handler dispatch contract | App-specific configuration |

Switchboard must not embed application code in endpoint definitions, generate a general middleware stack, or require a specific reverse proxy. The runtime should keep public URLs clean and use `sb/switchboard.php` as the internal PHP entrypoint.

## Deployment Model

A reverse proxy such as Nginx or Apache fronts the host. Public runtime traffic goes to the PHP runtime, while management UI and `/api/*` routes remain protected control-plane surfaces.

The canonical portable route is:

```text
/sb/<app_slug>/<version>/<endpoint_path>
```

Production deployments may also use mounted app paths such as `/<app_slug>/api/*`. Mounted app routing is convention-based: app slug maps to public mount `/<slug>`, built frontend files live under `/srv/switchboard/apps/<slug>/dist`, static files win first, and API fallback requests go to Switchboard without per-app Nginx changes.

## In Scope

Switchboard provides:

- app registry
- endpoint registry
- PHP routing runtime
- match-time predicates and post-match validations
- PHP handler dispatch contract
- WebUI for managing and testing apps and endpoints
- reverse-proxy-compatible deployment model

## Out Of Scope

Switchboard does not provide:

- a general application framework
- a workflow automation builder
- visual programming or pipeline tools
- embedded application business logic
- built-in public authentication for the management plane in v1
- per-app Nginx config generation for dynamic app mounts

## References

- [endpoint-schema.md](endpoint-schema.md) - Registry schema, predicates, validations, path params, and matching order.
- [php-app-contract.md](php-app-contract.md) - PHP handler request, response, and dispatch contract.
- [route-convention.md](route-convention.md) - Canonical `/sb/...` URLs and mounted app paths.
- [deployment-runbook.md](deployment-runbook.md) - Nginx/Apache deployment and management API protection.
