# CDP — Customer Data Platform

A simplified **Customer Data Platform** backend written in **plain PHP 8 and MySQL 8**, without a framework.

It collects behavioral events about customers (page views, purchases, sign-ups…), builds a unified profile for each customer, and **segments** customers with conditions such as *"made a purchase over 100 €"* or *"bought at least 3 times"*.

**Features**

- **Event ingestion**: `POST /api/events`. It validates the payload, creates or updates the customer (keyed by email), stores the event and its properties, and ignores duplicate events.
- **Customer profile**: `GET /api/customers/{id}`. It returns the customer, their last 10 events and aggregated statistics (total purchases, total spend…).
- **Segmentation engine**: `POST /api/segments/query`. Conditions can target event properties (`=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `contains`, `starts_with`) or aggregates (`count`, `total_amount`), combined with `all` or `any`. Queries only read indexes (no full table scan) and results are paginated.
- **Web interface**: a customers list, customer profiles, the latest events and a segment builder.
- **Tooling**: a reproducible seed (200,000 events), a benchmark script, unit and integration tests, and a Docker setup.

**Stack**: PHP 8.2+ (in-house MVC-style architecture, PDO), MySQL 8.0, nginx, PHPUnit 11. Composer is used only for autoloading and PHPUnit.

---

## Installation

You can run the project **with Docker (recommended)** or on a local PHP/MySQL setup.

### Option 1: With Docker (recommended)

**Prerequisites**

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows / macOS) or Docker Engine + Compose v2 (Linux), running.
- Ports `8080`, `8081` and `3306` free on your machine.

You don't need PHP, MySQL or Composer installed locally: everything runs in containers.

**Steps**

```bash
# 1. Get the code
git clone <repo-url> cdp
cd cdp

# 2. Build the PHP image and start the containers (nginx, php-fpm, MySQL, phpMyAdmin)
docker compose up -d --build

# 3. Install the PHP dependencies inside the container
docker compose exec php composer install
```

No `.env` file is needed: the database credentials are defined in `docker-compose.yml`. On its first start, MySQL creates the `cdp` database and loads `database/schema.sql`.

**Check that it works**: http://localhost:8080/api/health should return `{"status":"ok","database":"up"}`.

| URL | What |
|---|---|
| http://localhost:8080 | Web interface |
| http://localhost:8080/api/… | JSON API |
| http://localhost:8081 | phpMyAdmin (dev only, auto-logged in as `cdp`) |

**Useful commands**

```bash
docker compose up -d                          # start (after the first install)
docker compose down                           # stop
docker compose down -v && docker compose up -d   # reset the database (reloads schema.sql)
docker compose logs -f php                    # follow PHP logs
docker compose exec mysql mysql -ucdp -pcdp cdp  # MySQL console
```

### Option 2: Without Docker

**Prerequisites**

- **PHP 8.2+** with the `pdo_mysql` and `mbstring` extensions
- **Composer 2**
- **MySQL 8.0.19+**. The queries use MySQL 8 features (window functions, `INSERT … AS alias`), so MariaDB is not supported.

**Steps**

1. **Create the database and its user** (as MySQL root):

   ```sql
   CREATE DATABASE cdp CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   CREATE USER 'cdp'@'localhost' IDENTIFIED BY 'cdp';
   GRANT ALL PRIVILEGES ON cdp.* TO 'cdp'@'localhost';
   ```

2. **Load the schema**:

   ```bash
   mysql -u cdp -p cdp < database/schema.sql
   ```

3. **Configure the application**. Copy the example file, then adjust the `DB_*` values if your credentials differ:

   ```bash
   cp .env.example .env
   ```

   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=cdp
   DB_USER=cdp
   DB_PASSWORD=cdp
   ```

4. **Install the dependencies and start the built-in PHP server**:

   ```bash
   composer install
   composer serve        # → http://localhost:8000
   ```

**Check that it works**: http://localhost:8000/api/health.

> **Windows tip**: if PHP reports `Unable to load dynamic library 'openssl'` (or `pdo_mysql`), set the extensions folder in your `php.ini`, for example `extension_dir = "C:/php/ext"`, and enable `extension=openssl`, `extension=pdo_mysql` and `extension=mbstring`.

---

## Sample data

The seed **wipes all tables**, then generates a realistic and reproducible dataset: 10,000 customers and 200,000 events spread over the last 365 days. It takes about 2–3 minutes.

```bash
# With Docker
docker compose exec php composer seed
docker compose exec php php database/seed.php --customers=2000 --events=20000 --seed=7   # custom size / dataset

# Without Docker
composer seed
```

The seed also creates a demo API key: `cdp_demo_key_change_me_0123456789`.

To time the segment queries and the customer profile on the current data:

```bash
docker compose exec php composer benchmark    # or: composer benchmark
```

## Tests

```bash
docker compose exec php composer test         # or: composer test
```

- **Unit tests**: router, validators, SQL builder and form mapping. They don't need a database.
- **Integration tests**: repositories and services, run against the real MySQL database. Each test runs in a transaction that is rolled back at the end, so it never modifies your data.
  - They read the connection from the `DB_*` environment variables, which default to `cdp`/`cdp` on `127.0.0.1:3306`.
  - They are **skipped** if the database is unreachable.

---

## API quick reference

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/health` | Liveness and database check |
| `POST` | `/api/events` | Ingest an event: `201` when created, `200` for a duplicate |
| `GET` | `/api/customers/{id}` | Profile: customer, last 10 events, statistics |
| `POST` | `/api/segments/query` | Customers matching conditions, paginated |

```bash
# Ingest an event
curl -X POST http://localhost:8080/api/events -H 'Content-Type: application/json' -d '{
  "customer": {"email": "john@example.com", "name": "John Doe"},
  "event": "purchase",
  "properties": {"amount": 120, "product": "Shoes"},
  "timestamp": "2026-04-10T12:00:00Z"
}'

# Customer profile
curl http://localhost:8080/api/customers/1

# Segment: customers with a purchase over 100
curl -X POST http://localhost:8080/api/segments/query -H 'Content-Type: application/json' -d '{
  "conditions": [{"event": "purchase", "property": "amount", "operator": ">", "value": 100}]
}'
```

Errors always have the same shape: `{"error": {"code": 422, "message": "Validation failed.", "details": {"customer.email": "Invalid email address."}}}`.

---

## Project structure

```
public/index.php        Front controller (single entry point)
config/                 Configuration and route table
src/
├── Core/               In-house mini framework: Kernel, Router, Request/Response, DI container, PDO wrapper, views
├── Controller/         Thin HTTP layer (JSON API) + Web/ (HTML pages)
├── Service/            Business logic: ingestion, customer profile, Segmentation/ engine
├── Repository/         All the SQL lives here
├── Model/              Immutable entities and DTOs
├── Validation/         Payload validators
└── Seed/               Dataset generator
views/                  HTML templates of the web interface
database/               schema.sql, seed.php, benchmark.php
tests/                  Unit/ and Integration/ (PHPUnit)
docker/                 PHP image and nginx configuration
```

Request flow: `public/index.php → Kernel → Router → Controller → Service → Repository → MySQL`.
