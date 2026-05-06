# 0001: Use JSON Registry With Atomic Writes For V1

## Status
Accepted

## Context
Switchboard currently stores app and endpoint definitions in `config/endpoints.json`. The current file-backed approach is easy to inspect and works for local/self-hosted deployments, but v1 completion needs safer persistence behavior before it can claim operator readiness.

The registry must support the v1 operator workflow without introducing a storage migration that would delay basic endpoint trust.

## Decision
Keep `config/endpoints.json` as the v1 source of truth and harden writes with:

- exclusive file locking
- temp-file writes in the same directory
- validation before commit
- atomic rename into place
- preservation of the previous last-known-good file as backup
- structured API errors when writes fail

SQLite and PostgreSQL are deferred.

## Options Considered
### JSON With Atomic Writes And File Locking
**Pros**: Fits current implementation, no new runtime dependency, inspectable, easy recovery.  
**Cons**: Limited concurrent multi-user behavior, no query model, no audit history.

### SQLite Registry
**Pros**: Transactions, schema, better future migration path.  
**Cons**: Larger refactor, migration/versioning decisions before workflow completion.

### PostgreSQL Registry
**Pros**: Strongest multi-user production foundation.  
**Cons**: Adds infrastructure and deployment burden outside v1 scope.

## Consequences
- v1 remains a single-node/self-hosted registry model.
- Concurrent writes are serialized at the file level.
- Rich audit history and multi-user collaboration remain future work.
- Registry implementation should move behind a helper/module instead of staying embedded in `sb/api.php`.

## Implementation Plan
- Add a registry persistence helper for loading, validating, locking, writing, backing up, and committing registry files.
- Update `sb/api.php` write paths to use the helper.
- Validate duplicate app slugs, duplicate endpoint route identities, required fields, path format, HTTP method, and route condition vocabulary before committing.
- Preserve `config/endpoints.json` if validation or write fails.
- Add tests for valid writes, validation failures, duplicate rejection, backup creation, and failed-write safety.

## Verification
- [x] Invalid registry input never replaces `config/endpoints.json`.
- [x] Duplicate app slugs and endpoint routes are rejected.
- [x] Successful writes are committed atomically.
- [x] Previous registry content is recoverable from backup.
- [x] PHP tests and smoke checks pass.

## Non-Goals
- Built-in audit log.
- Database migration.
- Multi-node registry synchronization.
