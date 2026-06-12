# Paysera Transfer API

Production-oriented REST API for secure fund transfers between accounts. Built as a Paysera technical assignment demonstrating financial correctness, concurrency safety, idempotency, auditability, and production readiness.

**Stack:** PHP 8.4 · Symfony 7 · MySQL 8 · Redis · Docker · PHPUnit

---

## Architecture Overview

```
Client
  │
  ▼
Nginx → Symfony API (JWT, Rate Limiting, Validation)
  │
  ├── IdempotencyService (Redis cache + DB unique constraint)
  │
  └── TransferService (DB transaction)
        ├── Pessimistic row locks (SELECT FOR UPDATE)
        ├── Balance debit/credit (BCMath DECIMAL)
        ├── Transfer record
        └── Double-entry ledger entries
```

### Core guarantees

| Guarantee | Mechanism |
|-----------|-----------|
| No money creation/loss | Atomic DB transaction; debit + credit in same commit |
| No double spending | `SELECT FOR UPDATE` on source account |
| Atomic debit/credit | Single InnoDB transaction |
| Idempotent requests | `Idempotency-Key` + unique DB index + Redis cache |
| Audit trail | Immutable `ledger_entries` per transfer |
| Safe concurrency | Pessimistic locks acquired in deterministic UUID order |

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for full design rationale.

---

## Project Structure

```
paysera-transfer-api/
├── config/                 # Symfony configuration
├── docker/                 # Nginx, supervisord, entrypoint
├── migrations/             # Doctrine schema migrations
├── public/                 # Web entry point
├── scripts/                # JWT key generation
├── src/
│   ├── Command/            # CLI (seed accounts)
│   ├── Controller/         # REST endpoints
│   ├── DTO/                # Request/response objects
│   ├── Entity/             # Account, Transfer, LedgerEntry
│   ├── Enum/               # Status enums
│   ├── EventSubscriber/    # Logging, exception handling
│   ├── Exception/          # Domain exceptions
│   ├── Infrastructure/     # Redis client
│   ├── Repository/         # Data access + locking
│   ├── Security/           # JWT user provider
│   ├── Service/            # Transfer + idempotency logic
│   └── Validator/          # Custom constraints
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Functional/
└── docs/                   # API spec, architecture, edge cases
```

---

## Quick Start (Docker)

### Prerequisites

- Docker & Docker Compose
- Git

### 1. Start services

```bash
cd c:\paysera-transfer-api
docker compose up -d --build
```

API available at: **http://localhost:8080**

MySQL: `localhost:3307` · Redis: `localhost:6380`

### 2. Seed demo accounts

```bash
docker compose exec app php bin/console app:seed-accounts
```

### 3. Obtain JWT token

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"paysera_api\",\"password\":\"PayseraDemo123!\"}"
```

### 4. Create a transfer

```bash
curl -X POST http://localhost:8080/api/v1/transfers \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: demo-transfer-001" \
  -d "{\"source_account_id\":\"<SOURCE_UUID>\",\"destination_account_id\":\"<DEST_UUID>\",\"amount\":\"25.5000\"}"
```

### 5. Get transfer status

```bash
curl http://localhost:8080/api/v1/transfers/<TRANSFER_UUID> \
  -H "Authorization: Bearer <TOKEN>"
```

---

## Local Development (without Docker)

```bash
composer install
bash scripts/generate-jwt-keys.sh   # or use Git Bash on Windows
php bin/console doctrine:migrations:migrate
php bin/console app:seed-accounts
symfony server:start   # or php -S localhost:8000 -t public
```

---

## Running Tests

```bash
# Create test database
docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS paysera_transfer_test;"

composer install
cp phpunit.xml.dist phpunit.xml
vendor/bin/phpunit
```

Test suites:

| Suite | Purpose |
|-------|---------|
| `Unit` | Validators, idempotency logic |
| `Integration` | Transfer service, DB transactions, concurrency |
| `Functional` | HTTP API, auth, validation |

---

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/v1/auth/login` | No | Obtain JWT |
| POST | `/api/v1/transfers` | Yes | Create transfer |
| GET | `/api/v1/transfers/{id}` | Yes | Get transfer |
| GET | `/api/v1/accounts/{id}` | Yes | Get account (optional) |

Full specification: [docs/API_SPEC.md](docs/API_SPEC.md)

---

## Database Schema

### `accounts`
- UUID PK, `account_number`, `balance DECIMAL(19,4)`, `currency`, `status`, timestamps, `version`

### `transfers`
- UUID PK, source/destination FKs, `amount`, `status`, `idempotency_key` (unique), `request_hash`, timestamps

### `ledger_entries`
- UUID PK, transfer FK, account FK, `DEBIT`/`CREDIT`, `amount`, `balance_after`, timestamp

**Why DECIMAL?** Floating-point types (`FLOAT`, `DOUBLE`) cannot represent decimal fractions exactly. Financial systems require exact arithmetic — `DECIMAL(19,4)` with BCMath prevents rounding drift.

---

## Key Design Decisions

### Pessimistic locking (chosen over optimistic)

Banking transfers are write-heavy on hot accounts. Pessimistic `SELECT FOR UPDATE` provides immediate exclusion, preventing lost updates and double spending. Locks are acquired in sorted UUID order to reduce deadlocks.

### Double-entry ledger

Every transfer creates one DEBIT and one CREDIT ledger entry with `balance_after`. Enables reconciliation, regulatory audits, and dispute investigation without replaying all transfers.

### Idempotency (Stripe-inspired)

Clients send `Idempotency-Key` header. Same key + same payload → return cached result. Same key + different payload → `409 Conflict`. Redis accelerates lookups; MySQL unique constraint is source of truth.

---

## Security

- **JWT authentication** — stateless API access
- **Rate limiting** — 60 requests/minute per IP (configurable)
- **Input validation** — Symfony Validator + UUID checks
- **Secure errors** — no stack traces or internal details in responses
- **Structured logging** — correlation/request IDs on every request

---

## Assumptions

- Single currency per transfer (source currency applies)
- No fractional currency conversion
- Demo user provider (replace with real IAM in production)
- Transfer limits enforced via `TRANSFER_MAX_AMOUNT` env var
- Accounts pre-exist (no account creation endpoint in scope)

---

## Trade-offs

| Decision | Benefit | Cost |
|----------|---------|------|
| Pessimistic locking | Strong correctness | Reduced throughput on hot accounts |
| Sync processing | Simplicity, immediate consistency | No async scaling yet |
| In-process Redis fallback | Resilient idempotency | Extra DB reads when Redis down |
| DECIMAL + BCMath | Exact money math | Slightly slower than integers |

---

## Future Improvements

- **Event Sourcing** — immutable event log as system of record
- **CQRS** — separate read models for reporting
- **Outbox Pattern** — reliable async event publishing
- **Saga Pattern** — distributed multi-step payments
- **RabbitMQ/Kafka** — async notifications, fraud checks
- **Fraud detection** — velocity rules, anomaly scoring
- **AML monitoring** — suspicious transfer flagging
- **Multi-currency** — FX rates, hedging
- **Regulatory reporting** — automated audit exports

---

## Documentation Index

| Document | Description |
|----------|-------------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System design and folder rationale |
| [docs/API_SPEC.md](docs/API_SPEC.md) | Endpoint contracts |
| [docs/EDGE_CASES.md](docs/EDGE_CASES.md) | 20 edge cases with behavior |
---

## Time Estimate

| Phase | Hours |
|-------|-------|
| Architecture & schema design | 3 |
| Core transfer + locking | 4 |
| Idempotency + Redis | 2 |
| Security + logging | 2 |
| Tests | 4 |
| Documentation | 3 |
| **Total** | **~18 hours** |

---

## License

Proprietary — Paysera technical assignment.
