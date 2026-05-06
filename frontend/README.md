# Switchboard Management UI (React + MUI)

Part of Switchboard v1. For full-stack run instructions (router + WebUI, config, env), see the [root README](../README.md#quick-start).

React (TypeScript, Vite, MUI) frontend for Switchboard. **Backend:** PHP only. The UI expects a REST API at `/api` for apps, endpoints, predicates, validations, and test requests. The backend must implement that API and read/write `config/endpoints.json`; it may also serve the built static files from `frontend/dist/`.

## Requirements

- Node 20+ (for Vite dev and build)
- npm or pnpm

## Run (development)

Run a PHP backend that exposes the management API (e.g. on port 3081), then start the frontend dev server; Vite can proxy `/api` to that backend.

```bash
# Terminal 1: your PHP API (must serve /api/* and use config/endpoints.json)
# Terminal 2: React dev server (set Vite proxy or CONTROL_PLANE_URL to your API origin)
cd frontend && npm install && npm run dev
```

Open **http://localhost:3083** (or the port Vite prints). Set `CONTROL_PLANE_URL` to your API origin if it is not the default.

## Build (production)

```bash
cd frontend && npm install && npm run build
```

Output is in `frontend/dist/`. Serve the static files from the same origin as your API so the React app can call `/api`. If your backend is PHP (or any non-Node stack), configure it to serve the contents of `frontend/dist/` for the app routes and your existing `/api` for the REST API.

## Stack

- **React 18** + **TypeScript**
- **MUI (Material UI) 5** — components and dark theme
- **React Router 6** — apps, app detail, endpoints, tester
- **Vite** — dev server and build

## Routes

| Path | Description |
|------|-------------|
| `/` | Apps list |
| `/apps/new` | Create app |
| `/apps/:id` | App detail + endpoints |
| `/apps/:id/edit` | Edit app |
| `/apps/:id/endpoints/new` | Create endpoint |
| `/endpoints/:id` | Endpoint detail (predicates, validations) |
| `/endpoints/:id/edit` | Edit endpoint |
| `/tester` | Endpoint tester |
