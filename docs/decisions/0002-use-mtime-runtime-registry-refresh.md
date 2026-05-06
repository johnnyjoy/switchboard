# 0002: Use MTime Runtime Registry Refresh

## Status
Accepted

## Context
The README currently says the router must be restarted after config changes. That is simple but weakens the value of a management UI: an operator can save a change but still not know when runtime traffic sees it.

V1 needs an explicit freshness guarantee that works with the current PHP runtime and does not require a background daemon.

## Decision
Use mtime-based runtime registry refresh with last-known-good fallback.

The v1 guarantee is:

> A valid saved registry change becomes visible to runtime requests on the next request after the registry file mtime changes. If reload fails, runtime keeps the last-known-good registry and exposes/logs the reload error.

## Options Considered
### Restart Required
**Pros**: Simple, explicit, low overhead.  
**Cons**: Poor operator experience, easy tester/runtime mismatch, weak management UI story.

### Per-Request File Reload
**Pros**: Always fresh, simple in PHP.  
**Cons**: Repeated file I/O and parsing, weaker cache story.

### MTime-Based Cached Reload
**Pros**: Balanced freshness and performance, clear failure behavior.  
**Cons**: Needs careful last-known-good handling and deployment documentation.

## Consequences
- Runtime no longer depends on a manual restart for normal valid config changes.
- Invalid config must not replace the active runtime registry.
- Tests must cover config update behavior.
- Deployment docs must describe the freshness guarantee and PHP deployment caveats.

## Implementation Plan
- Add cache metadata to the registry loading path or introduce a registry provider.
- Track active registry mtime and last reload error.
- Reload when file mtime changes.
- Keep the previous valid registry if JSON parse or schema validation fails.
- Expose reload/freshness status through `/api/test-request` or a management health response if practical.
- Update README and runbook to replace the blanket restart requirement.

## Verification
- [x] Runtime sees a valid config change without process restart.
- [x] Runtime keeps last-known-good routes after invalid config.
- [x] Tester and runtime use the same refreshed route semantics.
- [x] Runbook states exactly when config changes become active.

## Non-Goals
- Background watcher process.
- Multi-node config propagation.
- Push-based hot reload protocol.
