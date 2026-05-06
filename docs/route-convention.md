# Route Convention: `/sb/<app>/{v1,v2,...}/`

This document defines the standard URL shape for Switchboard-routed requests. It aligns with the existing [architecture](architecture.md) and [endpoint schema](endpoint-schema.md).

**Audience:** Engineers implementing the PHP routing runtime and operators configuring reverse proxies.

---

## 1. URL Shape

All requests that Switchboard routes **must** use the following prefix:

```
/sb/<app_slug>/<version>/<path>
```

Where:

| Segment   | Meaning |
|----------|---------|
| `sb`     | Fixed literal. Identifies Switchboard-managed traffic so the reverse proxy can send it to the runtime. |
| `<app_slug>` | **App slug** from the registry. One-to-one with `apps.slug`. Identifies which app (and thus which app codebase) owns the route. |
| `<version>`  | **API version** path segment. Typically `v1`, `v2`, `v3`, etc. Used for versioning; see [§3](#3-version-semantics). |
| `<path>`     | **Remaining path** after the prefix. Matched against endpoint definitions; see [§2](#2-mapping-app_slug-to-the-registry). |

**Examples:**

- `https://example.com/sb/news/v1/articles` → app `news`, version `v1`, path `/articles`
- `https://example.com/sb/webhook-gateway/v2/receive` → app `webhook-gateway`, version `v2`, path `/receive`

---

## 2. Mapping `<app_slug>` to the Registry

- **`<app_slug>`** is the first path segment after `/sb/`.
- It **must** equal the **`apps.slug`** of an enabled app in the endpoint registry.
- The runtime uses it to:
  1. **Scope matching:** Only endpoints belonging to that app are considered.
  2. **Resolve handler location:** The app’s `app_path` (directory or deployable unit) identifies where handler code lives.
- If the segment does not match any enabled app slug, the runtime returns **404** (or equivalent “no app” response). It does not fall through to other apps.

**Constraint:** `app_slug` must be URL-safe and match the same rules as `apps.slug` (e.g. lowercase, hyphens allowed, no slashes). The registry already enforces uniqueness of `slug`.

---

## 3. Version Semantics

- **`<version>`** is the second path segment after `/sb/` (the segment immediately after `<app_slug>`).
- It is **required** in the URL. Requests to `/sb/<app_slug>/` with no version segment are invalid; the runtime may respond with **400** or **404**.
- **Current use:** The version segment is used primarily for **API versioning** and **routing clarity**. It is part of the public URL contract.
- **Matching:** For v1, the runtime **strips** the prefix `/sb/<app_slug>/<version>` and matches the **remaining path** against the endpoint registry’s `path` field. The registry does **not** store the version in the URL; endpoint `path` is stored **without** the `/sb/.../v1` prefix (e.g. `/articles`, `/webhooks/receive`).
- **Future:** A later iteration may add an optional `version` (or similar) field to the endpoint or app schema so that only endpoints for a given version are considered when `<version>` is `v2`, etc. Until then, all endpoints of the app are eligible regardless of the version segment; the version in the URL is for client/organizational clarity.

---

## 4. Matching After the Prefix

After normalizing and stripping the prefix:

1. **Normalize request path** to the part after `/sb/<app_slug>/<version>` (e.g. `/articles` or `/articles/123`). Leading slash is preserved for matching.
2. **Load endpoints** for the app identified by `<app_slug>` (where `app.enabled = true` and `endpoint.enabled = true`).
3. **Match** using the current endpoint model: request method in `methods[]`, path pattern and path params, host, then `endpoint_predicates`. Path is the **remaining** path; endpoint definitions use paths **without** the `/sb/...` prefix.
4. **Validate** and **dispatch** per [architecture](architecture.md).

So an endpoint with `path = "/articles"` and `methods = ["GET"]` matches a GET request to `/sb/news/v1/articles` for app `news`.

---

## 5. Registry Path Rules (Summary)

- **Stored path:** Endpoint `path` in the registry is **relative to the app+version prefix**. It must start with `/` and must **not** include `/sb/`, `<app_slug>`, or `<version>`.
- **Examples of valid stored paths:** `/articles`, `/webhooks/receive`, `/users/{id}`.
- This keeps the registry server-agnostic and avoids duplicating the prefix in every endpoint.

---

## 6. Reverse Proxy and Deployment

- The reverse proxy (e.g. Nginx, Apache) should send to the Switchboard runtime only requests whose path starts with `/sb/`.
- No `.php` or other technology suffix is required in the public URL; the convention is path-only.
- The internal PHP entry script is a deployment detail. Current examples route runtime traffic to `sb/switchboard.php` while preserving the original `REQUEST_URI`; `sb/index.php` remains only as a compatibility shim.
- The runtime is responsible for parsing `/sb/<app_slug>/<version>/<path>` and for returning appropriate status codes when the prefix is missing or malformed (e.g. 404 for unknown app, 400 for missing version segment if enforced).
- Catch-all full-path routing also works today for non-prefixed public paths such as `/news/articles`: the runtime matches the full request path against stored endpoint paths.

## 7. Mounted App Paths

Mounted app paths are an operator-friendly deployment layer over the canonical `/sb/...` convention. They are useful when an app has a static frontend under a public mount and API endpoints under the same app.

The production model assumes immutable Nginx after image creation. Mounted app paths are therefore derived by convention, not by rendering one Nginx snippet per app:

- app slug `foo` maps to public mount `/foo`
- built frontend files live under `/srv/switchboard/apps/foo/dist`
- dynamic API calls use `/foo/api/*`
- endpoint paths stored in Switchboard remain app-scoped paths such as `/health` or `/products`

The route ownership convention is:

| Public path | Owner | Behavior |
|-------------|-------|----------|
| `/<app_slug>` | Nginx | Serves `/srv/switchboard/apps/<app_slug>/dist/index.html`. |
| `/<app_slug>/assets/*` | Nginx | Serves static assets from `/srv/switchboard/apps/<app_slug>/dist/assets/`. |
| `/<app_slug>/api/*` | Nginx, then Switchboard | Static files under `dist/api` win; otherwise Nginx forwards to `sb/switchboard.php`. |
| `/<app_slug>/*` | Frontend SPA | If configured as an SPA, falls back to `dist/index.html`. |
| `/sb/<app_slug>/v1/*` | Switchboard | Canonical portable Switchboard route. |

Switchboard does not modify live Nginx config. Operators add apps by updating registry data and mounted app files under the fixed app root, not by rebuilding containers or changing Nginx.

The immutable Nginx app API lane passes mount metadata derived from the URL:

```nginx
fastcgi_param SWITCHBOARD_MOUNT_APP $app;
fastcgi_param SWITCHBOARD_MOUNT_PATH /$app;
fastcgi_param SWITCHBOARD_MOUNT_API_PREFIX /api;
```

The runtime uses that metadata to scope matching to the app slug and strip `/<app_slug>/api` before matching endpoint paths. A request to `/foo/api/health` therefore matches app `foo` endpoint path `/health`.

Avoid ambiguous paths. If `/foo/products` is a frontend route, the direct API route should be `/foo/api/products`, not also `/foo/products`.

---

## 8. References

- [architecture.md](architecture.md) — Request pipeline (normalize → match → validate → dispatch).
- [endpoint-schema.md](endpoint-schema.md) — `apps.slug`, `endpoints.path`, and registry structure.
- [architecture.md](architecture.md) — App, endpoint, handler, and runtime boundaries.
