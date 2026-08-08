# react-vision

React 19 + TypeScript SPA for Vision. Built on top of [MineralUI Pro](https://mineralui.io); the project does not own a separate component library — every piece of UI composes Pro primitives (`MAppShell`, `MCardGrid`, `MTreeView`, `MTabs`, `MModal`, `MInputPassword`, …).

## Stack

- React 19, TypeScript 5
- Vite 8 (dev server + build)
- React Router v6
- `@banzamel/mineralui-pro` (UI components, theming, i18n, icons, illustrations, toasts, modals)
- Laravel Echo + pusher-js (realtime via Reverb)
- Service Worker + Web Push API (PWA, push notifications)

## Layout

```
src/
├── App.tsx                # routes + global providers
├── main.tsx               # createRoot + MThemeProvider/MI18nProvider/MToastProvider/AuthProvider
├── components/            # cross-feature UI (PwaInstallButton, ViewModeToggle, AppFooter…)
├── layouts/               # ProtectedLayout, PublicLayout — wrap routes with MAppShell + MBody
├── pages/                 # one file per route (DashboardPage, UsersPage, ObjectsPage, …)
├── features/              # feature modules — auth, objects, cameras, albums, users, installer, notifications, dashboard, support
├── helpers/               # api wrapper, errorToast, format, useAsync, useViewMode, socket
├── i18n/                  # pl.json + en.json + interpolate helper
└── theme/                 # template.css, theme.css overrides
```

Each feature folder follows the same shape: `api.ts` (typed wrappers around `helpers/api`), `types.ts`, view components, optional `index.ts` re-exports.

## Request flow

```
Page  ->  feature/api.ts  ->  helpers/api  ->  fetch  ->  Laravel
   ↘                                              ↗
    AuthContext (token + refresh on 401)
```

- `helpers/api.ts` handles base URL, bearer token, automatic refresh on 401, and tags non-`/api` prefixes (`/oauth/`, `/broadcasting/`).
- `useErrorToast` (in `helpers/errorToast.ts`) extracts the most useful message out of Laravel's response (`errors → message → error → exception → fallback`) and shows it via `MToast`.
- Realtime: `socket.ts` subscribes to private channels through Echo + Reverb, dispatching events into the same setters used by REST loaders (single source of truth in feature contexts).

### Notifications i18n

`features/notifications/renderNotification.ts` translates the backend's structured `data` payload (`{actor_name}`, `{date, camera_name, album_id}`, …) through `i18n/{pl,en}.json` keys (`notifications_center.types.{type}.{title|message}`) using the `interpolate()` helper. The backend persists EN fallback `title`/`message` so the OS-level Web Push notification stays readable even before the SPA mounts.

### Photos

The album view loads two URLs per photo:

- `thumbnail_url` — small (400×300, ~30–50 KB) JPEG used by the gallery grid. Backend generates it lazily on first request and async via a queued listener (`PhotoAddedEvent` → `GenerateThumbnailListener`); `AlbumGallery` falls back to the full stream when the thumb hasn't been produced yet, and shows `MSkeleton` placeholders during the first network round-trip.
- `stream_url` — full-resolution photo loaded by the lightbox.

Both URLs are short-lived signed paths (`signed:relative` middleware on the backend) so plain `<img>` can fetch them without a Bearer token. Object/camera main photos go to the public disk and come back as plain `main_photo_url` (no signing — straight `/storage/...` URL).

## Routing

- `PublicLayout` — login, install wizard, public marketing.
- `ProtectedLayout` — wraps every authed page in `MAppShell` (navbar, sidebar) + `MBody`. **Every page must live under one of these layouts** — a bare route causes overflow.

## Dev

Inside Docker (recommended):

```bash
docker compose up
# http://localhost:5173
```

On the host (for editor IntelliSense / typecheck):

```bash
npm install
npm run dev
npm run build
```

## Conventions

- **MineralUI Pro only.** No custom components for things the Pro catalog already covers; if Pro is missing a prop, ask before patching the framework.
- **No `spacing` on `MInline`/`MStack`/`MGrid`.** Those layouts already ship flex `gap`; the `spacing` utility prop is outer margin and overflows the parent when paired with `fullWidth`.
- **Files capped at ~300 lines.** Split large pages into view + hook + helper modules before they grow past that.
- **Pages must live under `ProtectedLayout` or `PublicLayout`** — never plain routes.
- **Mobile-first responsive columns.** `MCardGrid` accepts `columns={{ base, sm, md, lg, xl, xxl }}` — bump up at each breakpoint, not down.
