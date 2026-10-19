# Trailcast — Excursions API

REST API for an agency that organises excursions. Customers list the bookable excursions, each with the weather forecast for its date and city, and book seats. Staff create new excursions.

**Stack:** PHP 8.4, Symfony 7.4, Doctrine ORM, MySQL 8.4, Valkey (Redis-compatible) for caching, JWT authentication (LexikJWTAuthenticationBundle). Weather and geocoding data from [Open-Meteo](https://open-meteo.com/).
 
---

## Getting started

Requirements: Docker with Compose v2.

```bash
docker compose build --no-cache
```

```bash
docker compose up
```

On every start the app container:

1. installs the Composer dependencies;
2. creates the `trailcast` and `trailcast_test` databases (if missing) and runs the migrations;
3. generates the JWT key pair (if missing);
4. creates the demo users (if missing).

The first start takes a few minutes because of `composer install`. The API is ready when the Symfony server reports it is listening on port 8080.

| Service               | Address                         |
|-----------------------|---------------------------------|
| API                   | http://localhost:8080           |
| API docs (Swagger UI) | http://localhost:8080/api/doc   |
| MySQL                 | `localhost:3311`                |

If port 3311 is already in use on your machine, set `DOCKER_DB_PORT` in `.env`.

### Running the tests

```bash
docker compose exec trailcast_app composer test
```

The command runs the migrations on the test database, then PHPUnit. Each test runs inside a transaction that is rolled back at the end (DAMA DoctrineTestBundle). The Integration tests call the real Open-Meteo, so the suite needs network access (see [Tests](#tests)).

### Running the tests from PhpStorm

PhpStorm can run the tests inside the `trailcast_app` container, with the same PHP, extensions and test database as `composer test`. Start the stack first with `docker compose up`.

1. **Interpreter:** *Settings → PHP → CLI Interpreter → … → + → From Docker, Vagrant, VM, WSL, Remote…*
    - choose **Docker Compose**, configuration file `./docker-compose.yml`, service `trailcast_app`;
    - in the interpreter settings set *Lifecycle* to **Connect to existing container (`docker-compose exec`)**, so the running container is reused.
2. **Path mapping:** project root → `/var/www` (PhpStorm usually detects it from the volume in `docker-compose.yml`).
3. **PHPUnit:** *Settings → PHP → Test Frameworks → + → PHPUnit by Remote Interpreter*, pick the interpreter above, then:
    - *Use Composer autoloader*: `/var/www/vendor/autoload.php`;
    - *Default configuration file*: `/var/www/trailcast/phpunit.dist.xml`.
4. **Run:** right-click the `tests` folder (or `tests/Unit`, `tests/Application`, `tests/Integration`) and choose *Run*, or use the green arrow next to a test class or method.

The test database is created and migrated by the container at every start. After adding a migration, run `docker compose exec trailcast_app composer db:setup-test` once, because PhpStorm calls PHPUnit directly and skips that step.

### Test coverage

```bash
docker compose exec trailcast_app composer test:coverage
```

Prints the coverage summary per class and writes the HTML report to `var/coverage/index.html`.

Coverage is **targeted**: each test declares what it tests with `#[CoversClass]` / `#[CoversMethod]`, and PHPUnit counts only that code. Lines run incidentally (an entity getter during a controller test) are not counted, so the figures show what is tested on purpose.

### Demo credentials

| Email                  | Password   | Role         |
|------------------------|------------|--------------|
| `staff@trailcast.test` | `staff123` | `ROLE_STAFF` |
| `user@trailcast.test`  | `user123`  | none (customer) |

The users are created by `bin/console app:generate-users`, which runs automatically at startup and is idempotent.
 
---

## Authentication

Only `POST /excursions` requires authentication, and only staff can use it. Listing and booking are public.

Get a token:

```bash
curl -s -X POST http://localhost:8080/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "staff@trailcast.test", "password": "staff123"}'
```

```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

Then send it as `Authorization: Bearer <token>`.

- No token or invalid token → `401`.
- Authenticated user without `ROLE_STAFF` → `403`.
- The role always comes from the authenticated user stored in the database, never from the request body or from a client-supplied header.

---

## Endpoints

All examples send `Accept: application/json`, so that error responses are rendered as JSON.

Every endpoint is rate limited per client IP: over the limit it responds `429` (see [Rate limiting](#rate-limiting)).

### Create an excursion (staff)

`POST /excursions` → `201`

```bash
curl -s -X POST http://localhost:8080/excursions \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "Sunrise hike on Etna",
    "description": "Guided ascent with a stop at the Valle del Bove",
    "city": "Catania",
    "country": "IT",
    "date": "2026-11-20",
    "capacity": 20
  }'
```

```json
{
  "id": 1,
  "title": "Sunrise hike on Etna",
  "description": "Guided ascent with a stop at the Valle del Bove",
  "city": "Catania",
  "country": "IT",
  "date": "2026-11-20",
  "capacity": 20,
  "seats_available": 20,
  "latitude": 37.49223,
  "longitude": 15.07041
}
```

| Field         | Rules                                     |
|---------------|-------------------------------------------|
| `title`       | required, max 255 characters              |
| `description` | required, max 500 characters              |
| `city`        | required, max 255 characters (see [Geocoding](#geocoding)) |
| `country`     | required, ISO 3166-1 alpha-2 code         |
| `date`        | required, `YYYY-MM-DD`, in the future     |
| `capacity`    | required, greater than 0                  |

| Status | When                                                  |
|--------|-------------------------------------------------------|
| `201`  | Excursion created                                     |
| `401`  | Missing or invalid token                              |
| `403`  | Authenticated user is not staff                       |
| `404`  | No location matches the city in the given country     |
| `422`  | Invalid payload                                       |
| `503`  | Geocoding provider unavailable                        |

### List bookable excursions

`GET /excursions` → `200`

An excursion is **bookable** when its date is today or later and it has at least one seat available. Past and sold-out excursions are never returned. Results are ordered by date, then id.

| Query parameter | Description                                   | Default |
|-----------------|-----------------------------------------------|---------|
| `city`          | Exact city name                               |         |
| `country`       | ISO 3166-1 alpha-2 code                       |         |
| `date_from`     | Excursions on or after this date (`YYYY-MM-DD`)  |         |
| `date_to`       | Excursions on or before this date (`YYYY-MM-DD`) |         |
| `seats`         | Minimum number of available seats             |         |
| `page`          | Page number                                   | `1`     |
| `per_page`      | Items per page (max 50)                       | `20`    |

```bash
curl -s 'http://localhost:8080/excursions?country=IT&seats=2&per_page=10' \
  -H 'Accept: application/json'
```

```json
{
  "data": [
    {
      "id": 2,
      "title": "Lake Como by kayak",
      "description": "Half-day paddle from Lecco to Abbadia Lariana",
      "city": "Lecco",
      "country": "IT",
      "date": "2026-10-20",
      "capacity": 12,
      "seats_available": 7,
      "forecast": {
        "status": "available",
        "available_from": null,
        "data": {
          "weather_code": 61,
          "temperature_max_celsius": 16.4,
          "temperature_min_celsius": 9.8,
          "precipitation_probability_percent": 70
        }
      }
    },
    {
      "id": 1,
      "title": "Sunrise hike on Etna",
      "description": "Guided ascent with a stop at the Valle del Bove",
      "city": "Catania",
      "country": "IT",
      "date": "2026-11-20",
      "capacity": 20,
      "seats_available": 20,
      "forecast": {
        "status": "not_yet_available",
        "available_from": "2026-11-05",
        "data": null
      }
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 10,
    "total": 2,
    "total_pages": 1
  }
}
```

`weather_code` follows the [WMO weather interpretation codes](https://open-meteo.com/en/docs#weather_variable_documentation). See [Weather forecast](#weather-forecast) for the meaning of `status`.

| Status | When                     |
|--------|--------------------------|
| `200`  | Always, even when the weather provider is down |
| `422`  | Invalid query parameters |

### Book an excursion

`POST /excursions/{id}/bookings` → `201`

```bash
curl -s -X POST http://localhost:8080/excursions/1/bookings \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "customer_name": "John Doe",
    "customer_email": "john.doe@example.com",
    "seats": 3
  }'
```

```json
{
  "id": 1,
  "excursion_id": 1,
  "customer_name": "John Doe",
  "customer_email": "john.doe@example.com",
  "seats": 3
}
```

| Field            | Rules                        |
|------------------|------------------------------|
| `customer_name`  | required, max 255 characters |
| `customer_email` | required, valid email        |
| `seats`          | required, at least 1         |

| Status | When                                            |
|--------|-------------------------------------------------|
| `201`  | Booking created                                 |
| `404`  | Excursion does not exist                        |
| `409`  | Not enough seats left for the requested amount  |
| `422`  | Invalid payload                                 |
| `503`  | The excursion is busy with other bookings; nothing was booked |

Example `409`:

```json
{
  "title": "Errore nella richiesta",
  "status": 409,
  "detail": "Not enough seats available for excursion with ID 1",
  "params": {
    "excursion_id": 1,
    "seats": 30
  }
}
```

### Error format

Domain errors (location not found, not enough seats, excursion busy, provider unavailable) are returned as `application/problem+json` with `title`, `status`, `detail` and an optional `params` object with the values involved.

Validation errors (`422`) and missing excursions (`404`) use Symfony's standard problem+json format; a `422` lists each invalid field under `violations`.

---

## Design decisions

### Concurrent bookings

An excursion stores a `seats_available` counter, initialised to `capacity` and decremented by every booking. The list query filters on it directly, without aggregating bookings.

A booking runs inside a database transaction:

1. the excursion row is re-read with a pessimistic write lock (`SELECT ... FOR UPDATE`);
2. availability is checked on that fresh value;
3. the counter is decremented and the booking is inserted;
4. the transaction commits and releases the lock.

Concurrent bookings on the same excursion are therefore serialised, and each one sees the seats left by the previous ones: an excursion can never sell more seats than its capacity. Bookings on different excursions do not block each other.

A booking waits at most 5 seconds for the lock (`innodb_lock_wait_timeout`, set for the booking's session). If the lock cannot be acquired in time, or MySQL detects a deadlock, the transaction is rolled back and the API responds `503`: nothing was booked, so the client can retry.

**Manual check of the lock timeout.** Hold the lock from a MySQL client:

```bash
docker compose exec trailcast_db mysql -u usertrailcast -ptrailcast trailcast
```

```sql
BEGIN;
SELECT * FROM excursion WHERE id = 1 FOR UPDATE;
```

Then book excursion `1` through the API: after 5 seconds it responds `503`. End with `ROLLBACK;`.

**Deployment assumptions:** a single MySQL primary with InnoDB. The lock lives in the database, so any number of application instances can run in parallel. The approach would not hold if writes could go to asynchronous replicas or to a multi-primary setup.

**Alternatives considered:**

- *Atomic conditional update* (`UPDATE excursion SET seats_available = seats_available - :n WHERE id = :id AND seats_available >= :n`, then check the affected rows): one round trip and no lock held while application code runs, but the business rule moves into SQL.
- *Optimistic locking* (version column): no waiting, but under contention most requests fail and must be retried.

The pessimistic lock keeps the check and the update in one place, in the domain code, at the cost of short waits on a very popular excursion.

**Manual check.** Create an excursion with capacity 5 (here id `1`), then send 20 bookings of one seat in parallel:

```bash
seq 20 | xargs -P 20 -I{} curl -s -o /dev/null -w '%{http_code}\n' \
  -X POST http://localhost:8080/excursions/1/bookings \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"customer_name": "Load test", "customer_email": "load@test.dev", "seats": 1}' \
  | sort | uniq -c
```

Expected output: 5 × `201` and 15 × `409`. If you run it several times within a minute, some requests get `429` from the [rate limiter](#rate-limiting).

### Weather forecast

Open-Meteo provides forecasts up to **16 days** ahead, today included.

- **Dates are not validated against the forecast window.** Planning an excursion months in advance is a legitimate use case.
- **The list never fails because of the weather provider.** Every bookable excursion is returned. The `forecast` object is always present, with the same shape, and its `status` says what happened:
  | `status`            | Meaning                                                   | `data`   | `available_from`                          |
  |---------------------|-----------------------------------------------------------|----------|-------------------------------------------|
  | `available`         | Forecast available for the excursion date                 | forecast | `null`                                    |
  | `not_yet_available` | Date beyond the 16-day window                             | `null`   | First day the forecast will be available  |
  | `unavailable`       | Open-Meteo unreachable or returned an unexpected response | `null`   | `null`                                    |

`available_from` is the excursion date minus 15 days. Whether a date is beyond the window is decided from the series actually returned by Open-Meteo, which is computed in the location's own timezone (`timezone=auto`), so the server never does timezone arithmetic.

Exposed values: WMO weather code, maximum and minimum temperature, maximum precipitation probability. They are what someone needs to decide whether to join an outdoor excursion.

### Caching and Open-Meteo integration

- The HTTP clients towards Open-Meteo have an inactivity timeout and a maximum duration (2 and 5 seconds for geocoding, 4 and 7 for the forecast, whose 16-day response is slower), so a slow provider cannot stall the list.
- The whole 16-day series of a location is fetched with a single request and cached in Valkey for 1 hour.
- The cache key contains today's date and the coordinates rounded to 2 decimals (about 1 km, finer than the resolution of the weather models), so nearby excursions share one entry whatever their date.
- Within a single list request, excursions in the same rounded location trigger one lookup only.
- Failed calls are never cached as forecasts.
- When a forecast call fails, Open-Meteo is marked as down for 30 seconds (a flag in Valkey): until it expires, uncached locations get an `unavailable` forecast at once instead of waiting for the timeout, and forecasts already cached are still served. When the flag expires, the next request calls Open-Meteo again.

### Geocoding

Cities are resolved through the Open-Meteo Geocoding API **once, when the excursion is created**. The coordinates are stored with the excursion, so the list never calls the geocoder.

- Results are filtered by `country`.
- Open-Meteo matches names **by prefix** (`Calolzio` returns `Calolziocorte`). For this reason up to 10 results are requested, and only a result whose name is **identical** to the requested city is accepted.
- The comparison ignores case but not accents (`Forli` does not match `Forlì`), and names are compared in **Italian** (`Parigi`, not `Paris`).
- **Homonyms:** when several places in the country share the same name, the first one returned by Open-Meteo is used. The request contract does not let the client disambiguate.
- **No match:** the excursion is not created and the API responds `404`.

### Rate limiting

Every client IP can make 60 requests per minute, across all routes (sliding window, Symfony RateLimiter).

- Over the limit the API responds `429 Too Many Requests` with a `Retry-After` header, in seconds.
- The check runs in `RateLimitListener` on `kernel.request`, after the router and before the firewall, so `POST /login` is limited too and a rejected request never reaches the database.
- The counter is stored in Valkey, so it is shared by every application instance.
- Symfony internal routes (profiler, toolbar) are not limited, and the limiter is disabled in the test environment.

### Architecture

```
Controller ──► ExcursionService ──► Doctrine repositories (MySQL)
                     │
                     ├──► CachedForecastService ──► OpenMeteoClient ──► Open-Meteo Forecast API
                     │          (Valkey)
                     └──► OpenMeteoClient ──► Open-Meteo Geocoding API
```

- PHP is camelCase, the JSON of the API (bodies, query strings, `params` of the errors) is snake_case: the
  serializer name converter `camel_case_to_snake_case` (`config/packages/framework.yaml`) does the translation.
- Request bodies and query strings are mapped to DTOs and validated with `#[MapRequestPayload]` and `#[MapQueryString]` (`src/Http/Requests`).
- Responses are view objects (`src/Http/Resources`) serialised to JSON by a `kernel.view` listener.
- Open-Meteo is accessed through `OpenMeteoClientInterface`, so tests can replace it.
- Domain errors are exceptions annotated with `#[WithHttpStatus]` and rendered by `ApiExceptionSubscriber`.

### Tests

Tests are split in three folders, by what they need to run. The test namespace mirrors the folders
(`App\Tests\Application\Services\ExcursionService\…`) and each test class is named `<Class><Method>Test`
(e.g. `ExcursionControllerCreateExcursionTest`).

| Folder | What it contains | Database | Open-Meteo |
|--------|------------------|----------|------------|
| `tests/Unit` | One class at a time, plain `TestCase`, no kernel. | no | no |
| `tests/Integration` | Only tests that call a real external service. | no | **real** |
| `tests/Application` | Everything else, on the booted application. Controllers use `WebTestCase`; services and Doctrine repositories use `KernelTestCase`. | real test DB | **always fake** |

```
tests/
├── Unit/Listeners/                      RateLimitListenerTest, KernelViewJsonResponseListenerTest
├── Unit/EventSubscriber/                ApiExceptionSubscriberTest
├── Unit/Service/                        ExcursionServiceBookExcursionLockTest (lock timeout and deadlock → 503)
├── Integration/Client/OpenMeteoClient/
│   ├── OpenMeteoClientGeocodeTest
│   └── OpenMeteoClientFetchDailyForecastTest
├── Application/
│   ├── ApplicationTestCase              base class: fake Open-Meteo, test data and HTTP helpers (not a test)
│   ├── Controller/ExcursionController/  ExcursionController{CreateExcursion,BookExcursion,SearchExcursions}Test
│   ├── Services/ExcursionService/       ExcursionService{CreateExcursion,BookExcursion,SearchExcursions}Test
│   ├── Repository/ExcursionRepository/  ExcursionRepositoryFindBookableTest
│   └── Security/LoginTest
├── Support/                             FakeOpenMeteoClient (not a test)
└── Fixtures/                            JSON files used by the tests
```

What each folder checks:

- **Controller tests** check the HTTP contract: status code, body, headers and the error statuses
  `401` / `403` / `404` / `409` / `422` / `503`.
- **Service tests** check the business rules: seats can never be oversold, the forecast statuses
  (`available`, `not_yet_available`, `unavailable`), and the forecast cache, i.e. one Open-Meteo call for two
  searches of the same place, and the circuit breaker (no calls while Open-Meteo is marked as down, a new
  call once the flag expires).
- **Repository and Security tests** check the bookable-excursions query (filters, ordering, pagination) and the
  login / JWT flow.
- **Integration tests** call the real Open-Meteo and assert on the **shape** of the data (coordinates in a valid
  range, one forecast per day of the window, plausible values), never on the weather itself.

How the tests are set up:

- Open-Meteo is replaced in the Application tests by `tests/Support/FakeOpenMeteoClient`, which never touches
  the network and records its calls.
- Every Application test extends `ApplicationTestCase`, which holds the shared helpers (`createExcursion()`,
  `createUser()`, `callApi()`, …).
- The test database has no users: each test creates the ones it needs (`ApplicationTestCase::createUser()`).
- Run a single folder with `docker compose exec trailcast_app vendor/bin/phpunit tests/Application` (or
  `tests/Unit`, `tests/Integration`). Run only the Application tests when offline.

---

## Known limitations and future work

- **Cache expiry at day change:** the cache key contains today's date, so all entries are refreshed at the same time after midnight.
- **Hamlets and colloquial names** (many *frazioni*, `Calolzio`) are not indexed by Open-Meteo and cannot be resolved. A fallback geocoder (e.g. Nominatim / OpenStreetMap) could cover them.
- **Homonyms** are resolved with the first Open-Meteo result. Accepting a region qualifier (`Castro, Puglia`), which Open-Meteo already supports, would let clients choose.
- **Search by area** (radius around a point) is not supported: search is by exact city and country.
