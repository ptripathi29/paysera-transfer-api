# Architecture

## System Context

The Transfer API is a stateless Symfony service responsible for atomically moving funds between pre-existing accounts. It sits behind an API gateway (conceptual) and integrates with:

- **MySQL 8** — source of truth for balances, transfers, ledger
- **Redis** — idempotency cache (non-authoritative)
- **Monolog** — structured application, transfer, and audit logs

## Layered Design

```
┌─────────────────────────────────────────────┐
│  Controller Layer                           │
│  - HTTP concerns, rate limiting, auth       │
│  - DTO deserialization + validation         │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  Service Layer                              │
│  - TransferService (orchestration)          │
│  - IdempotencyService (deduplication)       │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  Repository Layer                           │
│  - Pessimistic locking queries              │
│  - Deterministic lock ordering              │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  Entity Layer                               │
│  - Account, Transfer, LedgerEntry           │
│  - Domain invariants (debit, credit)        │
└─────────────────────────────────────────────┘
```

## Concurrency Model

**Choice: Pessimistic locking (`SELECT FOR UPDATE`)**

### Why pessimistic over optimistic?

| Factor | Pessimistic | Optimistic |
|--------|-------------|------------|
| Hot account contention | Blocks immediately, predictable | High retry rate under load |
| Financial correctness | Strong exclusion | Risk of retry storms |
| Implementation complexity | Moderate | Version column + retry logic |
| User experience | Slight wait | Unpredictable failures |

In payment systems, a failed transfer due to version conflict is worse than a brief wait. Paysera-scale transfer volume on individual retail accounts favors correctness.

### Deadlock prevention

When locking two accounts, always lock in **sorted UUID order**:

```php
$ids = [$sourceId, $destinationId];
sort($ids);
// lock $ids[0] first, then $ids[1]
```

This prevents circular wait (A→B and B→A simultaneously).

### Retry strategy

MySQL may still deadlock under extreme contention. `TransferService` retries up to 3 times with exponential backoff (50ms × attempt).

## Idempotency Architecture

```
Request + Idempotency-Key
        │
        ▼
   Redis lookup ──hit──► Return cached response
        │ miss
        ▼
   DB lookup by key ──found──► Validate hash → Return / 409
        │ not found
        ▼
   Execute transfer in transaction
        │
        ▼
   Cache result in Redis (TTL 24h)
```

**Database is source of truth.** Redis outage degrades to DB-only idempotency.

## Ledger Design

Double-entry bookkeeping:

| Entry | Account | Type | Amount |
|-------|---------|------|--------|
| 1 | Source | DEBIT | 100.00 |
| 2 | Destination | CREDIT | 100.00 |

Sum of debits = sum of credits for every transfer. Enables:

- End-of-day reconciliation
- Regulatory audit
- Dispute resolution ("prove balance at time T")

## Index Strategy

| Table | Index | Purpose |
|-------|-------|---------|
| accounts | account_number (unique) | Lookup by IBAN-style number |
| accounts | status | Filter active accounts |
| transfers | idempotency_key (unique) | Deduplication |
| transfers | source_account_id | Account history queries |
| transfers | created_at | Time-range reporting |
| ledger_entries | transfer_id | Reconstruct transfer |
| ledger_entries | account_id | Account statement |

## Observability

Every request receives:

- `X-Request-Id` — unique per HTTP request
- `X-Correlation-Id` — propagated from client or defaults to request ID

Logs written to:

- `var/log/transfer.log` — business events
- `var/log/audit.log` — immutable-style audit records
