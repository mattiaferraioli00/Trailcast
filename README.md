# Trailcast — Excursions API

REST API for an agency that organises excursions. Customers list the bookable excursions, each with the weather forecast for its date and city, and book seats. Staff create new excursions.

**Stack:** PHP 8.4, Symfony 7.4, Doctrine ORM, MySQL 8.4, Valkey (Redis-compatible) for caching, JWT authentication (LexikJWTAuthenticationBundle). Weather and geocoding data from [Open-Meteo](https://open-meteo.com/).
 
---

## Getting started

Requirements: Docker with Compose v2.

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
| Valkey                | `localhost:6379`                |

If a port is already in use on your machine, change `DOCKER_DB_PORT` or `DOCKER_VALKEY_PORT` in `.env`.

### Running the tests

```bash
docker compose exec trailcast_app composer test
```

The command runs the migrations on the test database, then PHPUnit. Each test runs inside a transaction that is rolled back at the end (DAMA DoctrineTestBundle), and calls to Open-Meteo are mocked, so the suite does not need network access.

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
curl -s -X POST http://localhost:8080/api/login \
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
| `503`  | Geocoding provider unavailable (`Retry-After: 10`)    |

### List bookable excursions

`GET /excursions` → `200`

An excursion is **bookable** when its date is in the future and it has at least one seat available. Past and sold-out excursions are never returned. Results are ordered by date, then id.

| Query parameter | Description                                   | Default |
|-----------------|-----------------------------------------------|---------|
| `city`          | Exact city name                               |         |
| `country`       | ISO 3166-1 alpha-2 code                       |         |
| `dateFrom`      | Excursions on or after this date (`YYYY-MM-DD`)  |         |
| `dateTo`        | Excursions on or before this date (`YYYY-MM-DD`) |         |
| `seats`         | Minimum number of available seats             |         |
| `page`          | Page number                                   | `1`     |
| `perPage`       | Items per page (max 50)                       | `20`    |

```bash
curl -s 'http://localhost:8080/excursions?country=IT&seats=2&perPage=10' \
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
  "name": "John Doe",
  "email": "john.doe@example.com",
  "seats": 3,
  "excursion_id": 1
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

Example `409`:

```json
{
  "title": "Conflict",
  "status": 409,
  "detail": "Not enough seats available for excursion with ID 1",
  "params": {
    "excursionId": 1,
    "seats": 30
  }
}
```

### Error format

Domain errors (location not found, not enough seats, provider unavailable) are returned as `application/problem+json` with `title`, `status`, `detail` and an optional `params` object with the values involved.

<!-- TODO: add an example of the 422 validation body and of the 404 for a missing excursion -->
 
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

Expected output: 5 × `201` and 15 × `409`.

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

- The HTTP clients towards Open-Meteo have a 2-second inactivity timeout and a 5-second maximum duration, so a slow provider cannot stall the list.
- The whole 16-day series of a location is fetched with a single request and cached in Valkey for 1 hour.
- The cache key contains today's date and the coordinates rounded to 2 decimals (about 1 km, finer than the resolution of the weather models), so nearby excursions share one entry whatever their date.
- Within a single list request, excursions in the same rounded location trigger one lookup only.
- Failed calls are never cached, so the next request retries.
### Geocoding

Cities are resolved through the Open-Meteo Geocoding API **once, when the excursion is created**. The coordinates are stored with the excursion, so the list never calls the geocoder.

- Results are filtered by `country`.
- Open-Meteo matches names **by prefix** (`Calolzio` returns `Calolziocorte`). For this reason up to 10 results are requested, and only a result whose name is **identical** to the requested city is accepted.
- The comparison ignores case but not accents (`Forli` does not match `Forlì`), and names are compared in **Italian** (`Parigi`, not `Paris`).
- **Homonyms:** when several places in the country share the same name, the first one returned by Open-Meteo is used. The request contract does not let the client disambiguate.
- **No match:** the excursion is not created and the API responds `404`.
### Architecture

```
Controller ──► ExcursionService ──► Doctrine repositories (MySQL)
                     │
                     ├──► CachedForecastService ──► OpenMeteoRepository ──► Open-Meteo Forecast API
                     │          (Valkey)
                     └──► OpenMeteoRepository ──► Open-Meteo Geocoding API
```

- Request bodies and query strings are mapped to DTOs and validated with `#[MapRequestPayload]` and `#[MapQueryString]` (`src/Http/Requests`).
- Responses are view objects (`src/Http/Resources`) serialised to JSON by a `kernel.view` listener.
- Open-Meteo is accessed through `OpenMeteoRepositoryInterface`, so tests can replace it.
- Domain errors are exceptions annotated with `#[WithHttpStatus]` and rendered by `ApiExceptionSubscriber`.
### Tests

<!-- TODO: list the tests and why they were chosen -->
 
---

## Known limitations and future work

- **Retries and rate limiting** towards Open-Meteo are not implemented.
- **Circuit breaker:** while Open-Meteo is down, every list request retries the call for each uncached location, waiting up to the timeout. A short-lived "provider down" flag in the cache would make these calls fail fast.
- **Cache expiry at day change:** the cache key contains today's date, so all entries are refreshed at the same time after midnight.
- **Lock wait timeout:** if the lock on an excursion cannot be acquired in time, the booking currently fails with `500`.
- **Hamlets and colloquial names** (many *frazioni*, `Calolzio`) are not indexed by Open-Meteo and cannot be resolved. A fallback geocoder (e.g. Nominatim / OpenStreetMap) could cover them.
- **Homonyms** are resolved with the first Open-Meteo result. Accepting a region qualifier (`Castro, Puglia`), which Open-Meteo already supports, would let clients choose.
- **Search by area** (radius around a point) is not supported: search is by exact city and country.
