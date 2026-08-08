# Vision

Multi-tenant SaaS for managing buildings, cameras and daily photo albums.

- **Backend:** Laravel 12 modular monolith (`laravel-vision/`) with Passport OAuth, Spatie Permission (teams mode keyed on `company_id`), Reverb broadcasting.
- **Frontend:** React 19 + TypeScript + Vite (`react-vision/`), built on top of [MineralUI Pro](https://mineralui.io) — every page composes Pro components (`MAppShell`, `MCardGrid`, `MTreeView`, `MTabs`, `MModal`, …). MineralUI Pro is a hard dependency; the project does not ship its own design system.
- **Realtime:** Laravel Reverb (WebSocket).
- **Storage:** MySQL 8 + Redis 7 (cache, queues, sessions).

## Quick start

```bash
docker compose up --build
```

First boot takes ~2 min (Composer install, migrations, Passport keys, Vite first compile). Then open <http://localhost:5173> — the app routes you to `/install` and the wizard provisions the first company, admin and root object.

## Layout

| Path                 | Purpose                                                                        |
|----------------------|--------------------------------------------------------------------------------|
| `laravel-vision/`         | Laravel 12 API. 10-folder domain contract, single-action controllers.          |
| `laravel-vision/deploy/`  | Production scripts + server configs (nginx, supervisor, cron).                |
| `react-vision/`           | React 19 SPA. Feature-first folders, MineralUI Pro components.                |
| `docker/`                 | Dockerfiles, supervisord config, `laravel-init.sh` bootstrap script.          |
| `docker-compose.yml`      | Five-service stack: mysql, redis, laravel-vision, reverb-vision, react-vision.|

## Production

HestiaCP, single domain, no Docker. `react-vision/dist/` is uploaded to `public_html/` and
`laravel-vision/` straight to `private/` via PhpStorm's SFTP deployment — see
[`laravel-vision/deploy/README.md`](laravel-vision/deploy/README.md) for the layout, the upload
exclusions and the init/update procedure.

## Reset everything

```bash
docker compose down -v
```

Drops volumes and lets `laravel-init.sh` rebuild the database from scratch on the next `up`. After the reset you also need to flip `APP_INSTALLED=false` in `laravel-vision/.env` so the installer reopens.

## Where to read next

- [`docker/README.md`](docker/README.md) — container layout, ports, common exec commands.
- [`laravel-vision/README.md`](laravel-vision/README.md) — domain contract, request flow, auth.
- [`react-vision/README.md`](react-vision/README.md) — frontend structure, conventions, dev tips.
