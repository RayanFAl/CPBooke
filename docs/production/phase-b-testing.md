# Phase B — Testing Roadmap

Current baseline: feature tests for Approvals, Settlements, Wallets, Support, Monitoring, Audit/Search, eSIM, etc.

## Target

| Layer | Goal |
|-------|------|
| Feature tests | Every critical admin/API flow has at least one happy-path + one denial path |
| Integration | BookNow sync, notifications channels, mail failures, wallet debit idempotency |
| Load tests | Global Search, Orders search, Support Show, Order sync endpoints |
| Coverage focus | Finance, wallets, approvals, settlements, support money actions |

## Priority flows to cover next

1. Support cancel / refund / compensation **permission denial**
2. Approval pending → approve → execute → fail → retry
3. Wallet deposit requiring approval vs auto-execute
4. Settlement import → compare → resolve → close
5. Global search across order/customer/ticket
6. Audit log written on wallet + approval actions
7. API auth throttle (429)
8. Backup command dry-run on mysql CI (optional job)

## Load test sketch (k6 / artillery)

- `GET /admin/search?q=...` (authenticated session)
- `GET /admin/orders?search=...`
- `POST /api/v1/orders/sync-flight` at expected peak RPS
- Measure p95 latency and error rate; alert if p95 > 1.5s (matches slow-request threshold)

## Definition of done for Phase B

- Critical money paths have deny + allow tests
- CI runs full Feature suite green
- One documented load-test report attached to release notes
