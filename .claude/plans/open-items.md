# Otwarte punkty

Projekt jest funkcjonalnie skończony i działa na produkcji. To nie jest roadmapa, tylko
lista rzeczy znanych i świadomie niezamkniętych — żeby nie odkrywać ich drugi raz.

Stan na 2026-08-08.

## Do zrobienia poza repo

- **Wykluczenia w deploymencie PhpStorma.** `.idea/deployment.xml` nie ma
  `<excludedPaths>`, więc mapowanie `laravel-vision/ → private/` wyśle lokalny `.env`
  i `storage/` na produkcję. Lista w [`vision-deployment`](../skills/vision-deployment.md).
  Pliku nie da się naprawić z repo — `.idea/` jest gitignorowane.
- **Token GitHuba w URL-u origina.** `git remote -v` pokazuje `ghp_…` wklejony w adres.
  Nie jest w repo (siedzi w `.git/config`), ale wart rotacji i przepięcia na credential
  helper.
- **Sekrety Vectora przewinęły się przez sesję.** Produkcyjny `.env` Vectora został raz
  wklejony do `env.production.example` Vision — `APP_KEY`, hasło MySQL, współdzielone
  hasło Redisa, secret Google OAuth, hasło panelu admina. Do gita nie trafiły
  (zweryfikowane `git log -S` po każdej wartości), ale hasło Redisa dotyczy wszystkich
  projektów na tej maszynie.

## Decyzje do podjęcia

- **Utwardzenie współdzielonego Redisa.** Szablon `env.production.example` przewiduje
  `REDIS_PREFIX`, własne `REDIS_DB`/`REDIS_CACHE_DB` i `REDIS_QUEUE=vision-default`,
  ale serwerowy `private/.env` ich nie ma — wszystko stoi na `default` i DB 0/1.
  Blokada techniczna zniknęła (listenery nie zaszywają już nazwy kolejki), więc to
  wyłącznie decyzja. Przy zmianie `REDIS_QUEUE` na żywym serwerze najpierw opróżnić
  starą kolejkę.

## Znane usterki

- **Dwa stare listenery piszą do usuniętych kont.** `BroadcastUserLoginListener`
  i `BroadcastAlbumCreatedListener` pytają `DB::table('sec_users')` bez
  `whereNull('deleted_at')`. Trzy listenery usunięć dodane później mają ten warunek.
- **`VisionObjectService::delete()` woła `event()` wewnątrz `DB::transaction()`.**
  Broadcast trafia do Redisa przed commitem, więc rollback zostawia klienta
  z informacją o usunięciu, do którego nie doszło. To samo warto przejrzeć
  w pozostałych serwisach.
- **Lista Users nie odświeża się w czasie rzeczywistym.** `UserCreated/Updated/Deleted`
  rozgłaszają się, ale front nie subskrybuje żadnego z nich — realtime konsumują
  wyłącznie Dashboard i `NotificationsContext`.

## Dług w testach i dokumentacji

- **Suite `tests/Feature` (13 plików) nie był uruchamiany.** Wymaga `RefreshDatabase`,
  czyli działającego `docker compose up`. `tests/Unit` przechodzi 158/159, gdzie jedyny
  błąd to `InstallerServiceTest` nieumiejący dosięgnąć kontenera MySQL.
- **`laravel-vision/README.md` twierdzi „145/145 green" i milczy o `tests/Feature`.**
  Nieaktualne na obu punktach.
- **Komentarze wycięte z `nginx-vision.conf`.** Zniknęło m.in. wyjaśnienie, po co jest
  `proxy_pass_header Upgrade` (nadpisuje `proxy_hide_header Upgrade` z template'u
  Hestii — bez tego handshake WS pada). Wiedza została w
  [`vision-realtime`](../skills/vision-realtime.md).
- **`vite.config.ts` ma `preserveSymlinks` i `dedupe` z komentarzami o linku `file:`
  do lokalnego repo mineralui.** Linku nie ma od czasu przejścia na rejestr
  `api.mineralui.io`; konfiguracja jest nieszkodliwa, ale opis wprowadza w błąd.
