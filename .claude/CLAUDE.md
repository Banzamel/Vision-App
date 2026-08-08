# Vision — indeks pracy

Multi-tenant SaaS do zarządzania budynkami, kamerami i dziennymi albumami zdjęć.
Monorepo: Laravel 12 API + React 19 SPA + lokalny stack dockerowy.

## Od czego zaczynać

1. [`README.md`](../README.md) — quick start, układ repo.
2. [`laravel-vision/README.md`](../laravel-vision/README.md) — kontrakt domen, request flow, auth, storage.
3. [`react-vision/README.md`](../react-vision/README.md) — struktura frontu, konwencje, dev tips.
4. [`docker/README.md`](../docker/README.md) — kontenery, porty, komendy.
5. `laravel-vision/deploy/` — skrypty i configi serwerowe; `env.production.example`
   ma w nagłówku instrukcję uzupełniania zmiennych, `server-init.sh` wypisuje ręczne kroki.

## Skille robocze

Wiedza, której nie widać w kodzie — pułapki, które już raz kosztowały czas.

- [Wdrożenie na produkcję](skills/vision-deployment.md) — HestiaCP, upload przez PhpStorma,
  wykluczenia, sekrety, dzielone porty i Redis.
- [Realtime: Reverb, broadcast, kolejki](skills/vision-realtime.md) — jak to jest spięte,
  czym broadcast różni się od powiadomienia, diagnostyka od najtańszej.

## Stan projektu

Funkcjonalnie skończony, działa na produkcji. Rzeczy świadomie niezamknięte —
usterki, decyzje do podjęcia, dług w testach — zebrane w
[otwartych punktach](plans/open-items.md). Zajrzyj tam, zanim uznasz coś za nowe odkrycie.

## Podprojekty

| Ścieżka           | Co to                                                                     |
|-------------------|---------------------------------------------------------------------------|
| `laravel-vision/` | Laravel 12, modularny monolit. Passport, Spatie Permission (teams), Reverb.|
| `react-vision/`   | React 19 + TS + Vite. PWA, Web Push, Laravel Echo.                         |
| `docker/`         | Dockerfile PHP 8.4, supervisord, `laravel-init.sh` — tylko dla dev.        |

## Zasady nadrzędne

### Backend (`laravel-vision/`)

- Każda domena w `src/Domains/{Domain}` trzyma **te same 10 folderów**
  (`Dtos Enums Events Factories Models Observers Repositories Requests Resources Services`),
  puste dopełniamy `.gitkeep`. `src/Shared/` to nie domena.
- Przepływ: `Route → Middleware → FormRequest → Controller (__invoke) → Service → Repository → Model`.
  Kontrolery są single-action i cienkie, serwisy nie dotykają Eloquenta, repozytoria trzymają całe query.
- Kolekcje zwracamy przez `Resource::collection($x)->response()` — koperta `{data: [...]}` musi zostać.
- Bindingi interface → konkret idą centralnie do `src/App/Providers/RegisterServiceProvider.php`.
- Multi-tenancy: global scope `BelongsToCompany` po `company_id`; każda akcja gatowana
  przez `Request::authorize()` (`$this->user()?->can('x') === true`).
- Stan instalacji: **wyłącznie** `config('app.installed')` / `APP_INSTALLED` w `.env`. Bez plików JSON.

### Frontend (`react-vision/`)

- **Tylko MineralUI Pro.** Nie piszemy własnych komponentów na to, co Pro już ma;
  brakującego propa nie dorabiamy w locie — pytamy.
- Bez `spacing` na `MInline`/`MStack`/`MGrid` (mają własny `gap`; `spacing` to margines zewnętrzny
  i rozpycha rodzica przy `fullWidth`).
- Pliki do ~300 linii — większe strony dzielimy na view + hook + helper.
- Każda strona pod `ProtectedLayout` albo `PublicLayout`. Goły route = overflow.
- Kolumny mobile-first: `MCardGrid columns={{base, sm, md, lg, xl, xxl}}` — w górę, nie w dół.

### Wspólne

- Zmiany kontraktu API muszą lecieć równolegle w backendzie, froncie i README — dokumentacja
  nie może się rozjeżdżać z kodem.
- Sekrety (`.env`, `.env.*`, `.npmrc`, klucze) nie wchodzą do repo i nie są czytane przez agenta.
- Commity bez trailerów współautorstwa — wymusza to `.claude/settings.json` (`attribution`).

## Codzienne komendy

```bash
docker compose up --build                    # cały stack; front na :5173, API na :8000
docker compose exec laravel-vision bash      # wejście do backendu
docker compose exec laravel-vision composer test
cd react-vision && npx tsc --noEmit          # typecheck bez builda
cd react-vision && npm run build             # dist/ = to, co leci na public_html
docker compose down -v                       # reset baz (potem APP_INSTALLED=false w .env)
```

## Produkcja w skrócie

HestiaCP, jedna domena, bez Dockera. Upload przez deployment PhpStorma:
`react-vision/dist/ → public_html/`, `laravel-vision/ → private/`.
Po każdym uploadzie `deploy/server-update.sh` na serwerze.
Backend leci prosto z folderu roboczego, więc deployment MUSI wykluczać `.env`, `storage`,
`bootstrap/cache`, `vendor`, `node_modules`, `.idea` — inaczej upload nadpisze produkcyjny env
i zdjęcia z kamer. Szczegóły w [`README.md`](../README.md#production).
