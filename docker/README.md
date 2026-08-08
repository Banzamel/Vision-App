# Docker stack

Five containers wired together by `docker-compose.yml` (one level up):

| Container        | Image / build                  | Port   | Role                                            |
|------------------|--------------------------------|--------|-------------------------------------------------|
| `mysql-vision`   | `mysql:8.3`                    | `3306` | Primary database.                               |
| `redis-vision`   | `redis:7-alpine`               | `6379` | Cache, queues, sessions, Reverb pub/sub.        |
| `laravel-vision` | `docker/php/Dockerfile`        | `8000` | API + queue worker + scheduler (supervisord).   |
| `reverb-vision`  | reuses `laravel-vision` image  | `8080` | WebSocket broadcaster (`artisan reverb:start`). |
| `react-vision`   | `node:22-bookworm-slim`        | `5173` | Vite dev server with HMR.                       |

## Files in this folder

- `php/Dockerfile` — PHP 8.4-cli + Composer + Supervisor. GD is rebuilt with `--with-jpeg --with-webp` so Intervention Image can decode camera photos and produce gallery thumbnails.
- `php/conf.d/uploads.ini` — copied into the image as `zz-uploads.ini` (loads last, overrides defaults). Bumps `upload_max_filesize=32M`, `post_max_size=40M`, `memory_limit=256M` — the stock 2 MB upload cap silently drops every camera photo.
- `php/supervisord.conf` — runs `artisan serve --no-reload`, `queue:listen`, `schedule:work` in one container.
- `entrypoint/laravel-init.sh` — bootstraps the backend on every start: composer install, wait for MySQL, run migrations + seed on first boot (driven by `APP_INSTALLED` in `.env`), generate Passport keys, lock RSA key permissions to 600, run `storage:link`, then exec the supervisord command.

## Boot flow

0. Before the first `up`, make sure `react-vision/.npmrc` exists (gitignored — it carries the
   `api.mineralui.io` auth token). `@banzamel/mineralui-pro` installs from that private registry;
   without the token the `react-vision` container's `npm install` fails with 401.
1. `docker compose up --build` builds the PHP image and pulls the rest.
2. `mysql-vision` becomes healthy → `laravel-vision` starts.
3. `laravel-init.sh` runs: composer install if needed, `db:monitor` until ready, `migrate:fresh --seed` (first boot) or `migrate` (subsequent), Passport keys + `Vision Web` public client.
4. Supervisord launches `serve` + `queue:listen` + `schedule:work`.
5. `reverb-vision` and `react-vision` start in parallel; the React entrypoint runs `npm install` once, then `npm run dev`.

## Endpoints

| What         | URL                       |
|--------------|---------------------------|
| Frontend     | http://localhost:5173     |
| Backend API  | http://localhost:8000/api |
| Reverb WS    | ws://localhost:8080       |
| MySQL        | localhost:3306            |
| Redis        | localhost:6379            |

## Useful commands

```bash
# follow backend bootstrap + supervisord output
docker compose logs -f laravel-vision

# tail individual processes inside the container
docker compose exec laravel-vision tail -f /var/log/supervisor/serve.log
docker compose exec laravel-vision tail -f /var/log/supervisor/queue.log

# enter the containers
docker compose exec laravel-vision bash
docker compose exec react-vision sh
docker compose exec mysql-vision mysql -uvision -pvision_password vision

# tinker
docker compose exec laravel-vision php artisan tinker
```

## After changing dependencies

- `composer.json`: `docker compose restart laravel-vision` — `laravel-init.sh` always runs `composer dump-autoload` and reinstalls when `vendor/` is missing.
- `package.json`: `docker compose exec react-vision npm install <pkg>` (the entrypoint only runs `npm install` if the install stamp is missing — delete `react-vision/node_modules/.install.stamp` to force a fresh install).

## Reset

```bash
docker compose down -v
```

Drops MySQL + Redis volumes. Also remember to flip `APP_INSTALLED=false` in `laravel-vision/.env` so the installer wizard reopens at `/install`.
