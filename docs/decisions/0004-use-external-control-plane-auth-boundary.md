# 0004: Use External Control Plane Auth Boundary

## Status
Accepted

## Context
Switchboard's management API can modify app and endpoint routing behavior. Exposing the management UI or `/api/*` routes without protection would allow unauthenticated configuration changes.

V1 needs a clear deployment boundary without expanding scope into a full identity, session, role, and audit system.

## Decision
V1 relies on external reverse-proxy, private-network, or equivalent deployment controls to protect the management UI and `/api/*` routes.

Switchboard v1 does not include built-in authentication, authorization, roles, sessions, or audit logging.

Public runtime routes and protected management routes are separate deployment concerns.

## Options Considered
### Built-In Auth And Audit
**Pros**: Strong self-contained production story, accountability.  
**Cons**: Large security/product scope, risk of weak homegrown auth, distracts from routing workflow.

### Reverse-Proxy Auth Boundary
**Pros**: Common self-hosted pattern, focused v1 scope, avoids weak built-in auth.  
**Cons**: Requires operator discipline and clear docs.

### Local-Only Control Plane
**Pros**: Very safe default.  
**Cons**: Inconvenient for shared operations and not enough for all deployments.

## Consequences
- Docs must warn against exposing management UI/API unauthenticated.
- Deployment examples should separate public runtime routes from protected control-plane routes.
- Built-in auth/audit remains future work.
- Tests should avoid implying auth exists in v1.

## Implementation Plan
- Update README and deployment runbook with explicit management API exposure warnings.
- Document reverse-proxy/private-network protection expectations.
- Keep `/api/*` handling clearly separated in `scripts/php-router.php`.
- If practical, add UI/runbook copy that identifies v1's external security boundary.
- List built-in auth and audit as future scope.

## Verification
- [x] README states management UI/API must be protected externally.
- [x] Deployment runbook distinguishes public runtime traffic from protected control-plane traffic.
- [x] No v1 doc claims built-in auth or audit.
- [x] Future auth/audit work is explicitly deferred.

## Non-Goals
- User accounts.
- Sessions.
- Role-based access control.
- Built-in audit log.
