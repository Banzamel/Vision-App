# Skill: Realtime — Reverb, broadcast, kolejki

Używaj przy pracy nad WebSocketami, powiadomieniami i wszystkim, co ma dojechać
do przeglądarki bez odświeżania.

## Jak to jest spięte

```
Serwis domenowy → event(ShouldBroadcast) → KOLEJKA → Reverb :8080
                                                        ↓
przeglądarka ← nginx /ws/ (trailing slash ucina prefiks) ← wss://host/ws/app/{key}
```

Front subskrybuje dwa kanały prywatne, oba zakładane przez `RealtimeProvider`
w momencie pojawienia się zalogowanego usera:

- `vision.company.{company_id}` — zdarzenia domenowe (obiekty, kamery, albumy)
- `vision.user.{id}` — wyłącznie `notifications.created`, czyli dzwonek

## Zasady

- **Nazwa kolejki nigdy nie jest zaszyta w listenerze.** Pochodzi z konfiguracji
  połączenia (`REDIS_QUEUE`), żeby jedna zmienna przestawiała producentów i workera
  naraz. Zaszycie `public $queue = 'default'` rozjeżdża je i joby giną po cichu —
  bez błędu, bez wpisu w logu.
- **`ShouldBroadcast` to nie `ShouldBroadcastNow`.** Wszystko idzie przez kolejkę, więc
  martwy worker wygląda identycznie jak sprawny system: handshake 101, subskrypcje na
  `true`, i cisza. To pierwsza rzecz do sprawdzenia.
- **Broadcast ≠ powiadomienie.** Event z `ShouldBroadcast` rozgłasza się sam, bez
  listenera. Wpis w dzwonku powstaje dopiero, gdy jakiś listener zawoła
  `NotificationService::create()`. Brak powiadomienia przy działającym broadcaście
  to luka w `EventServiceProvider`, nie awaria transportu.
- **`DB::table()` nie zna global scope'ów.** Przy fan-oucie po `sec_users` trzeba ręcznie
  dodać `whereNull('deleted_at')`, inaczej piszemy do skrzynek usuniętych kont.
- **`wsPath` musi być konfigurowalny.** Lokalnie przeglądarka puka wprost w kontener
  Reverba i prefiks musi być pusty; produkcja używa `/ws`. Stąd `VITE_REVERB_PATH`.
- **`REVERB_APP_KEY` = `VITE_REVERB_APP_KEY`.** Rozjazd kończy się zerwaniem połączenia
  kodem 4001 już po udanym upgrade, więc status 101 tego nie wykryje.

## Diagnostyka, od najtańszej

```js
// konsola przeglądarki — działa wstecz, nie trzeba łapać ramek
Pusher.instances[0].connection.state
Object.entries(Pusher.instances[0].channels.channels).map(([n, c]) => [n, c.subscribed])
```

Oba kanały na `true` = transport i autoryzacja działają. Dalej:

```bash
sudo supervisorctl status                   # workery żyją?
php8.4 artisan queue:monitor redis:default  # ile jobów czeka
php8.4 artisan queue:failed                 # ciche awarie wypływają tutaj
```

W Redisie klucze są prefiksowane (`vision_database_queues:default`), więc szukaj przez
`redis-cli --scan --pattern '*queues*'` zamiast zgadywać nazwę.

Zakładka Messages w DevTools pokazuje ramki tylko od momentu otwarcia — jeśli lista jest
pusta mimo działającego połączenia, przeładuj stronę przy otwartych DevTools.

## Co sprawdzać przy zmianie

1. Czy nowy event ma listenera, jeśli ma się pojawić w dzwonku.
2. Czy typ powiadomienia ma klucze `notifications_center.types.{type}` w `pl.json` i
   `en.json` — bez nich front cofa się do angielskiego fallbacku z listenera.
3. Czy front w ogóle subskrybuje to zdarzenie (dziś robią to tylko Dashboard
   i `NotificationsContext`).
4. Czy `event()` nie stoi wewnątrz `DB::transaction()` — przy rollbacku klient dostanie
   informację o zmianie, do której nie doszło.
