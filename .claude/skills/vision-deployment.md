# Skill: Wdrożenie Vision na produkcję

Używaj przy każdej zmianie dotykającej `laravel-vision/deploy/`, konfiguracji serwera
albo procedury wypuszczania wersji.

## Model wdrożenia

HestiaCP, jedna domena `vision.banzamel.pl`, bez Dockera. Hybryda nginx + Apache,
PHP-FPM 8.4 na per-domenowym sockecie, Supervisor dla kolejek i Reverba, współdzielony
systemowy MySQL i Redis.

Upload robi deployment PhpStorma (SFTP, serwer `AppVision`), nie skrypt:

```
react-vision/dist/  →  public_html/
laravel-vision/     →  private/          (więc deploy/ ląduje jako private/deploy/)
```

## Zasady

- **Backend jedzie prosto z folderu roboczego.** Nie ma etapu budowania paczki, więc
  wykluczenia w konfiguracji deploymentu są jedyną barierą między lokalnym `.env`
  a produkcyjnym. Muszą obejmować: `.env`, `storage`, `bootstrap/cache`, `vendor`,
  `node_modules`, `.idea`, `.phpunit.result.cache`.
- **`storage/app/private` to zdjęcia z kamer.** Nadpisanie tego katalogu jest
  nieodwracalne — nie ma z czego odtworzyć.
- **Sekrety nigdy nie wchodzą do repo.** `env.production.example` jest szablonem
  z pustymi wartościami; wypełniony plik żyje w `deploy/.env.production` (gitignored)
  albo prosto w `private/.env`. Nie kopiuj tu envów z sąsiednich projektów — zdarzyło
  się już wkleić produkcyjny env Vectora razem z hasłami.
- **Skrypty muszą mieć LF.** Windows ma `core.autocrlf=true`, a PhpStorm wysyła kopię
  roboczą 1:1, więc bez `.gitattributes` shebang `#!/usr/bin/env bash\r` wywala się
  na serwerze z „bad interpreter".
- **Porty są dzielone z innymi projektami.** Reverb Vision to 8080; 9090 należy do
  Vectora na tej samej maszynie. Redis jest wspólny — izolacja stoi na `REDIS_PREFIX`,
  własnych numerach DB i nazwanej kolejce jednocześnie.

## Procedura

Pierwsze uruchomienie: `bash private/deploy/server-init.sh`, potem ręczne kroki, które
skrypt wypisze (fragment nginx, wpisy supervisora, cron przez panel HestiaCP — nie przez
`crontab -e`, bo Hestia odbudowuje crontab ze swojej bazy), na końcu kreator `/install`.

Każdy kolejny deploy:

1. `cd react-vision && npm run build`
2. Upload obu mapowań z PhpStorma
3. `ssh webmaster@vision.banzamel.pl 'bash …/private/deploy/server-update.sh'`

## Co sprawdzać przy zmianie

1. Czy zmiana wymaga edycji `private/.env` — szablon w repo tego nie zrobi za Ciebie.
2. Czy workery muszą wstać na nowo (`queue:restart`) — trzymają stary kod w pamięci.
3. Czy `nginx -t` przechodzi przed reloadem; literówka w `conf_custom` kładzie cały
   serwer, nie samą domenę.
4. Czy front wymaga przebudowania (zmiany w `VITE_*` i w `i18n/` na pewno tak).
