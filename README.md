# CDP — Simplified Customer Data Platform

Framework-free PHP 8.2+ / MySQL 8 backend: event ingestion, customer profiles, segmentation.

## Setup

### With Docker

Requires Docker (Docker Desktop on Windows/macOS) running, and ports `8080` / `3306` free. No local PHP, MySQL or Composer needed.

```bash
git clone <repo-url> cdp && cd cdp
docker compose up -d --build
docker compose exec php composer install
```

API available at http://localhost:8080 — check with http://localhost:8080/api/health (`{"status":"ok","database":"up"}`).

No `.env` needed: DB credentials are set in `docker-compose.yml`. The schema in `database/schema.sql` is loaded on the first MySQL start only; after changing it, reset the database with `docker compose down -v && docker compose up -d`.

Next starts: `docker compose up -d` · stop: `docker compose down`.

phpMyAdmin (dev only) is available at http://localhost:8081 to browse the database — logged in automatically as `cdp`.

### Without Docker

Requires PHP 8.2+ with `pdo_mysql`, Composer and a local MySQL 8.

```bash
cp .env.example .env          # adjust DB_* values
composer install
mysql -u root -p cdp < database/schema.sql
composer serve                # http://localhost:8000
```

### Tests

```bash
composer test
```

## Architecture

```
public/index.php   Front controller
config/            Config + route table
src/Core/          In-house mini framework (Router, Request/Response, DI Container, PDO wrapper, Kernel)
src/Controller/    Thin HTTP layer: parse request, call a service, return JSON
src/Service/       Business logic (ingestion, profile, segmentation)
src/Repository/    All SQL lives here
src/Model/         Entities / DTOs
src/Validation/    Payload validators
database/          Schema + seed
```

Request flow: `index.php → Kernel → Router → Controller → Service → Repository → MySQL`.
Exceptions are converted to JSON errors by the Kernel (`422` validation, `4xx` HTTP, `500` otherwise).

## API

All errors share the format `{"error": {"code": 422, "message": "...", "details": {...}}}`.

| Method | Path                    | Description                          |
|--------|-------------------------|--------------------------------------|
| GET    | `/api/health`           | Liveness + DB check                  |
| POST   | `/api/events`           | Ingest an event (upserts customer)   |
| GET    | `/api/customers/{id}`   | Profile, last 10 events, statistics  |
| POST   | `/api/segments/query`   | Customers matching conditions        |

_TODO: examples for each endpoint._

## Design decisions & trade-offs

_TODO_

## Assumptions, limitations, improvements

_TODO_
