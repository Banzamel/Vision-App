# Vision — wdrożenie produkcyjne

Produkcja stoi na HestiaCP (`vision.banzamel.pl`), bez Dockera: hybryda nginx + Apache,
PHP-FPM 8.4 na per-domenowym sockecie, Supervisor dla kolejek i Reverba, systemowy MySQL + Redis.

## Układ na serwerze

```
/home/webmaster/web/vision.banzamel.pl/
├── public_html/      ← react-vision/dist/  (statyczny build SPA + .htaccess z fallbackiem)
│   └── storage       → symlink do private/storage/app/public (tworzy server-init.sh)
└── private/          ← laravel-vision/     (cały backend; docroot PHP-FPM to private/public)
    └── deploy/       ← ten folder
```

Wysyłką zajmuje się **deployment PhpStorma** (SFTP, serwer `AppVision`) — nie ma już
skryptu `upload.sh` ani generowanego folderu `prod/`. Mapowania siedzą w `.idea/deployment.xml`:

| Lokalnie              | Zdalnie       |
|-----------------------|---------------|
| `laravel-vision/`     | `private/`    |
| `react-vision/dist/`  | `public_html/`|

### Wykluczenia z uploadu (Deployment → Excluded Paths)

Backend leci prosto z folderu roboczego, więc bez wykluczeń nadpisalibyśmy produkcję plikami
lokalnymi. W konfiguracji deploymentu **muszą** być wykluczone:

- `laravel-vision/.env` — lokalny env (bazy dockerowe); nadpisanie zabija produkcję
- `laravel-vision/storage` — logi i zdjęcia z kamer (`storage/app/private`) żyją na serwerze
- `laravel-vision/bootstrap/cache` — cache generowany po stronie serwera
- `laravel-vision/vendor` — instaluje `server-update.sh` przez `composer install --no-dev`
- `laravel-vision/node_modules`, `laravel-vision/.idea`, `laravel-vision/.phpunit.result.cache`

## Pierwsze uruchomienie

1. Zbuduj front: `cd react-vision && npm run build`. Wymaga `react-vision/.env.production`,
   a w nim **`VITE_REVERB_PATH=/ws`** — bez tego pusher-js łączy się w `wss://host/app/{key}`,
   czego nginx już nie obsługuje (WebSocket wpada w SPA fallback i realtime milczy).
2. Wyślij oba mapowania z PhpStorma.
3. Na serwerze:
   ```bash
   bash /home/webmaster/web/vision.banzamel.pl/private/deploy/server-init.sh
   ```
   Pierwszy przebieg tworzy `.env` z `deploy/env.production.example` i kończy się —
   uzupełnij DB/Redis/VAPID/Reverb i odpal ponownie.
4. Ręczne kroki, które wypisze skrypt na końcu:
   - `nginx-vision.conf` → `/home/webmaster/conf/web/vision.banzamel.pl/nginx.ssl.conf_custom`
   - `supervisor-vision.conf` → `/etc/supervisor/conf.d/vision.conf`
   - `cron.txt` → zadanie CRON przez panel HestiaCP (nie `crontab -e`)
5. Otwórz `https://vision.banzamel.pl/install` i przejdź kreator.

## Każdy kolejny deploy

1. `npm run build` w `react-vision/`.
2. Upload z PhpStorma (oba mapowania).
3. ```bash
   ssh webmaster@vision.banzamel.pl \
       'bash /home/webmaster/web/vision.banzamel.pl/private/deploy/server-update.sh'
   ```
   Skrypt robi `composer install --no-dev`, migracje, przebudowę cache'y,
   `queue:restart` i restart Reverba.

## Pliki

| Plik                      | Co robi                                                              |
|---------------------------|----------------------------------------------------------------------|
| `server-init.sh`          | Jednorazowy setup: extensions, `.env`, composer, APP_KEY, klucze Passport, symlink storage, cache. |
| `server-update.sh`        | Po każdym uploadzie: composer, migracje, cache, `queue:restart`, restart Reverba. |
| `nginx-vision.conf`       | Fragment do `nginx.ssl.conf_custom`: `/api`, `/oauth`, `/broadcasting`, `/up` → PHP-FPM; `/ws/` → Reverb; no-cache dla `sw.js`/`index.html`/manifestu. |
| `supervisor-vision.conf`  | `vision-queue` (2 workery) + `vision-reverb` (127.0.0.1:8080).       |
| `cron.txt`                | Instrukcja dodania `schedule:run` przez panel HestiaCP.              |
| `env.production.example`  | Szablon produkcyjnego `.env`.                                        |

Sekrety (`deploy.env`, `.env.production`) są gitignorowane — dane SSH trzyma konfiguracja
deploymentu PhpStorma, nie repo.
