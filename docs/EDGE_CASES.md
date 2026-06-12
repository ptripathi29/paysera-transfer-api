# Edge Cases

Complete reference for 20 critical scenarios. For each: risk, behavior, HTTP response, implementation, interview explanation.

---

## 1. Insufficient Funds

| | |
|---|---|
| **Risk** | Negative balance, money creation |
| **Behavior** | Transfer rejected inside locked transaction; no balance change |
| **HTTP** | `422 INSUFFICIENT_FUNDS` |
| **Implementation** | `Account::hasSufficientFunds()` before debit; transaction rolled back |
| **Interview** | "We never debit without verifying balance under lock. Rollback ensures zero side effects." |

---

## 2. Non-existent Accounts

| | |
|---|---|
| **Risk** | Orphan transfers, FK violations |
| **Behavior** | Rejected after lock attempt returns null |
| **HTTP** | `404 ACCOUNT_NOT_FOUND` |
| **Implementation** | `findPairForUpdate()` returns null → exception |
| **Interview** | "Validation happens inside the transaction after lock to prevent TOCTOU race." |

---

## 3. Frozen (BLOCKED) Accounts

| | |
|---|---|
| **Risk** | Transfer from/to restricted account |
| **Behavior** | Rejected before balance modification |
| **HTTP** | `422 ACCOUNT_NOT_ACTIVE` |
| **Implementation** | `AccountStatus::allowsTransfers()` check |
| **Interview** | "Compliance holds must block both debit and credit paths." |

---

## 4. Closed Accounts

| | |
|---|---|
| **Risk** | Activity on terminated account |
| **Behavior** | Same as blocked |
| **HTTP** | `422 ACCOUNT_NOT_ACTIVE` |
| **Implementation** | Status enum check |
| **Interview** | "Closed accounts are terminal state — no exceptions without admin override." |

---

## 5. Same Source and Destination

| | |
|---|---|
| **Risk** | Meaningless transfer, potential audit confusion |
| **Behavior** | Rejected before transaction |
| **HTTP** | `422 SAME_ACCOUNT` |
| **Implementation** | UUID equality check in `TransferService` |
| **Interview** | "No-op transfers create ledger noise without business value." |

---

## 6. Zero Transfer Amount

| | |
|---|---|
| **Risk** | Ledger spam, accounting noise |
| **Behavior** | Rejected at validation |
| **HTTP** | `422 VALIDATION_FAILED` |
| **Implementation** | `ValidTransferAmountValidator` — `bccomp($value, '0', 4) <= 0` |
| **Interview** | "Zero-amount transfers have no financial meaning and could mask fraud patterns." |

---

## 7. Negative Transfer Amount

| | |
|---|---|
| **Risk** | Balance manipulation, money creation |
| **Behavior** | Rejected at validation |
| **HTTP** | `422 VALIDATION_FAILED` |
| **Implementation** | Regex + BCMath validation |
| **Interview** | "Amount sign is always positive; direction is determined by debit/credit roles." |

---

## 8. Currency Mismatch

| | |
|---|---|
| **Risk** | Silent FX conversion errors |
| **Behavior** | Rejected inside transaction |
| **HTTP** | `422 CURRENCY_MISMATCH` |
| **Implementation** | Compare `source.currency === destination.currency` |
| **Interview** | "FX is a separate bounded context. Never implicit conversion." |

---

## 9. Duplicate Requests (Client Retries)

| | |
|---|---|
| **Risk** | Double spending |
| **Behavior** | Return original transfer response |
| **HTTP** | `201` (same as original) |
| **Implementation** | `IdempotencyService::resolve()` |
| **Interview** | "This is the primary reason idempotency exists in payment APIs." |

---

## 10. Same Idempotency Key, Different Payload

| | |
|---|---|
| **Risk** | Key reuse attack or client bug |
| **Behavior** | Rejected |
| **HTTP** | `409 IDEMPOTENCY_CONFLICT` |
| **Implementation** | SHA-256 hash of payload compared to stored hash |
| **Interview** | "Keys are operation-scoped, not client-scoped. Mismatched body means client error." |

---

## 11. Redis Unavailable

| | |
|---|---|
| **Risk** | Idempotency failure, duplicate transfers |
| **Behavior** | Falls back to database lookup; transfers still work |
| **HTTP** | Normal responses (slightly slower) |
| **Implementation** | `PredisClient::markUnavailable()` + DB unique constraint |
| **Interview** | "Redis is a performance layer. MySQL unique index on idempotency_key is the safety net." |

---

## 12. Database Deadlocks

| | |
|---|---|
| **Risk** | Failed transfers under contention |
| **Behavior** | Automatic retry up to 3 times with backoff |
| **HTTP** | `201` on success; `500` after exhausted retries |
| **Implementation** | Catch `DeadlockException`, `usleep(50ms × attempt)` |
| **Interview** | "Ordered locking reduces deadlocks; retries handle the remainder." |

---

## 13. Concurrent Transfers on Same Account

| | |
|---|---|
| **Risk** | Lost updates, double spending |
| **Behavior** | Serialized via row locks; each sees current balance |
| **HTTP** | All succeed if funds sufficient; otherwise `422` |
| **Implementation** | `SELECT FOR UPDATE` on account rows |
| **Interview** | "Two 60 EUR transfers on 100 EUR balance: first succeeds, second gets insufficient funds." |

---

## 14. Double Spending Attempts

| | |
|---|---|
| **Risk** | Account debited twice for one intent |
| **Behavior** | Prevented by lock + idempotency |
| **HTTP** | `201` once; subsequent identical requests return cached |
| **Implementation** | Combined pessimistic lock and idempotency key |
| **Interview** | "Defense in depth: lock prevents concurrent double debit; idempotency prevents retry double debit." |

---

## 15. Network Timeout After Successful Commit

| | |
|---|---|
| **Risk** | Client unsure if transfer succeeded |
| **Behavior** | Client retries with same key → gets original response |
| **HTTP** | `201` (cached) |
| **Implementation** | Idempotency cache populated after commit |
| **Interview** | "Classic distributed systems problem. Idempotency is the standard solution." |

---

## 16. Database Crash During Transaction

| | |
|---|---|
| **Risk** | Partial state, inconsistent balances |
| **Behavior** | InnoDB rolls back uncommitted transaction |
| **HTTP** | `500` to client |
| **Implementation** | InnoDB ACID guarantees |
| **Interview** | "Uncommitted transactions vanish on crash. Ledger and balances stay consistent." |

---

## 17. Very Large Amounts

| | |
|---|---|
| **Risk** | Overflow, limit violations |
| **Behavior** | Rejected if above `TRANSFER_MAX_AMOUNT` |
| **HTTP** | `422 AMOUNT_EXCEEDS_LIMIT` |
| **Implementation** | `bccomp` against configured limit |
| **Interview** | "DECIMAL(19,4) supports up to 999,999,999,999,999.9999. Business limits are stricter." |

---

## 18. Precision and Rounding Issues

| | |
|---|---|
| **Risk** | Cent drift over millions of operations |
| **Behavior** | Exact arithmetic, 4 decimal places |
| **HTTP** | N/A (prevented at design level) |
| **Implementation** | `DECIMAL(19,4)` + BCMath with scale 4 |
| **Interview** | "This is why we never use float and why Stripe uses integer cents." |

---

## 19. Replay Attacks

| | |
|---|---|
| **Risk** | Re-execution of captured requests |
| **Behavior** | JWT expiry prevents stale auth; idempotency returns same result (no new debit) |
| **HTTP** | `201` cached (harmless) or `401` (expired JWT) |
| **Implementation** | JWT TTL + idempotency |
| **Interview** | "Idempotency makes replays safe. JWT expiry limits the attack window." |

---

## 20. High-Load Scenarios (1000+ Requests)

| | |
|---|---|
| **Risk** | Lock contention, timeouts, deadlocks |
| **Behavior** | Transfers serialize per account; system remains correct |
| **HTTP** | Mix of `201`, `422`, `429` |
| **Implementation** | Rate limiting + deadlock retry + connection pooling |
| **Interview** | "Correctness holds at any load. Throughput scales with account distribution. Hot accounts need queuing." |

---

## Test Coverage Mapping

| Edge Case | Test |
|-----------|------|
| Insufficient funds | `TransferServiceTest::testInsufficientFundsDoesNotChangeBalances` |
| Same account | `TransferServiceTest::testSameAccountTransferIsRejected` |
| Currency mismatch | `TransferServiceTest::testCurrencyMismatchIsRejected` |
| Idempotency | `TransferServiceTest::testDuplicateIdempotencyKeyReturnsOriginalResult` |
| Blocked account | `TransferServiceTest::testBlockedAccountIsRejected` |
| Concurrency | `TransferServiceTest::testConcurrentTransfersMaintainConsistency` |
| Rollback | `TransferServiceTest::testTransactionRollbackOnFailure` |
| Validation | `ValidTransferAmountValidatorTest` |
| Auth failure | `TransferApiTest::testAuthenticationFailure` |
| Missing idempotency key | `TransferApiTest::testCreateTransferRequiresIdempotencyKey` |
