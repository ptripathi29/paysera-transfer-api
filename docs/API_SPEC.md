# API Specification

Base URL: `http://localhost:8080/api/v1`

All authenticated endpoints require:

```
Authorization: Bearer <jwt_token>
```

Transfer creation additionally requires:

```
Idempotency-Key: <unique-client-generated-key>
Content-Type: application/json
```

---

## POST /auth/login

Obtain JWT access token.

### Request

```json
{
  "username": "paysera_api",
  "password": "PayseraDemo123!"
}
```

### Response `200`

```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

### Errors

| Status | Code | Condition |
|--------|------|-----------|
| 401 | - | Invalid credentials |

---

## POST /transfers

Create a fund transfer.

### Request

```json
{
  "source_account_id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
  "destination_account_id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c",
  "amount": "25.5000"
}
```

### Headers

| Header | Required | Description |
|--------|----------|-------------|
| Idempotency-Key | Yes | Client-generated unique key (max 128 chars) |
| Authorization | Yes | Bearer JWT |
| X-Correlation-Id | No | Client trace ID (propagated in response) |

### Response `201`

```json
{
  "id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5d",
  "source_account_id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
  "destination_account_id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5c",
  "amount": "25.5000",
  "currency": "EUR",
  "status": "COMPLETED",
  "failure_reason": null,
  "created_at": "2025-06-08T12:00:00+00:00",
  "completed_at": "2025-06-08T12:00:00+00:00"
}
```

### Error Responses

| Status | Code | Condition |
|--------|------|-----------|
| 400 | MISSING_IDEMPOTENCY_KEY | Header absent |
| 401 | - | Missing/invalid JWT |
| 404 | ACCOUNT_NOT_FOUND | Account does not exist |
| 409 | IDEMPOTENCY_CONFLICT | Same key, different payload |
| 422 | INSUFFICIENT_FUNDS | Source balance too low |
| 422 | SAME_ACCOUNT | Source = destination |
| 422 | CURRENCY_MISMATCH | Different currencies |
| 422 | ACCOUNT_NOT_ACTIVE | Blocked or closed account |
| 422 | AMOUNT_EXCEEDS_LIMIT | Above configured max |
| 422 | VALIDATION_FAILED | Invalid UUID, zero amount, etc. |
| 429 | RATE_LIMIT_EXCEEDED | Too many requests |

---

## GET /transfers/{id}

Retrieve transfer by UUID.

### Response `200`

Same schema as POST response.

### Errors

| Status | Code | Condition |
|--------|------|-----------|
| 400 | INVALID_UUID | Malformed UUID |
| 404 | TRANSFER_NOT_FOUND | Transfer does not exist |

---

## GET /accounts/{id}

Retrieve account details (optional endpoint).

### Response `200`

```json
{
  "id": "0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b",
  "account_number": "LT0010000000000001",
  "balance": "974.5000",
  "currency": "EUR",
  "status": "ACTIVE",
  "created_at": "2025-06-08T10:00:00+00:00",
  "updated_at": "2025-06-08T12:00:00+00:00"
}
```

---

## Idempotency Semantics

1. Client generates unique `Idempotency-Key` per logical operation
2. On network timeout, client retries with **same key and same body**
3. Server returns original `201` response without re-executing
4. If same key with **different body** → `409 Conflict`
5. Keys should be unique across different operations

Recommended key format: `{client_id}-{uuid_v4}`
