# Phase C — Documentation Roadmap

Not just README — operational documentation for the people who run CPBooke.

## Deliverables

| Doc | Audience | Status |
|-----|----------|--------|
| Admin Manual | Support / Ops / Finance | Planned |
| API Documentation | Mobile / integrations | Partial (`docs/api-orders-sync-esim.md`, Postman) |
| Architecture | Engineers | Partial (`docs/cpbooke-system-overview.md`) |
| Database ERD | Engineers / DBA | Planned |
| Runbook | On-call | **Started** (`04-disaster-recovery.md`) |
| Release Notes | All stakeholders | Planned per release |
| Deployment | DevOps | **Started** (`02-deployment.md`) |
| Backup/Restore | DevOps / DBA | **Started** (`03-backup-restore.md`) |

## Admin Manual outline

1. Login & roles
2. Orders & Timeline
3. Support workspace & money actions
4. Approvals queue
5. Provider wallets
6. Settlements
7. Monitoring & Audit Center
8. Global Search tips

## API Documentation outline

1. Auth (Sanctum)
2. Orders (create, sync-flight, sync-esim)
3. Support chat
4. Favorites / saved passengers
5. Error envelope (`ApiResponse`)
6. Idempotency rules

## Architecture outline

1. Modular admin/API layout
2. Approvals engine
3. Wallet ledger
4. Settlement compare loop
5. Monitoring + audit event streams
6. Queue/scheduler topology

## ERD

Generate from migrations (or MySQL Workbench / `schema:dump`) covering:

`users`, `orders`, `support_tickets`, `provider_wallets`, `provider_wallet_transactions`, `approvals`, `settlements`, `settlement_items`, `audit_logs`, `application_events`

## Release Notes template

```markdown
## Version X.Y.Z — YYYY-MM-DD
### Added
### Changed
### Fixed
### Ops notes (migrate / seed / workers)
```
