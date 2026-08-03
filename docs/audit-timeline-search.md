# Audit Center, System Timeline & Global Search

Operational visibility layer for CPBooke admin.

## Audit Center

- Route: `/admin/audit`
- Permission: `audit.view`
- Table: `audit_logs`
- Records: actor, timestamp, IP/user-agent, old/new values, entity, success/failed

Wired into:

- Orders (status / payment / notes)
- Support ticket history
- Provider wallets (deposit / adjust / debit)
- Settlements (create / import / resolve / close)
- Approvals (request / approve / reject / execute / fail)

## System Timeline

Unified lifecycle feed on entity show pages:

- Orders → Timeline tab
- Support → Activity tab
- Provider Wallets
- Settlements
- Approvals → `/admin/approvals/{id}`

Built by `EntityTimelineService` from domain history + `audit_logs`.

## Global Search

- Route: `/admin/search?q=`
- Permission: `search.view`
- Groups: Orders, Customers, Support Tickets, Wallet Transactions, Settlements, Passengers

Searchable fields include booking reference, PNR, order id, customer name/email/phone, ticket number, passport hash, wallet movements, settlement refs.

## Setup

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Config

```env
AUDIT_ENABLED=true
AUDIT_RETENTION_DAYS=180
AUDIT_TIMELINE_LIMIT=100
AUDIT_SEARCH_LIMIT_PER_GROUP=10
```
