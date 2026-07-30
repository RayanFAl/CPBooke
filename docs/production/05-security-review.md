# Security Review — Phase A

Date: 2026-07-22  
Scope: Policies, Gates, RBAC, rate limiting, uploads, audit coverage.

## Summary

| Area | Verdict | Notes |
|------|---------|-------|
| Admin auth stack | OK | `auth` + `admin` + CSRF (web) |
| RBAC registry | OK | Central `RbacRegistry` + Gate defines |
| Support money routes | **Fixed** | Fine-grained permissions on cancel/refund/reverse/compensation |
| API throttling | **Fixed** | Auth, order writes, support writes |
| Airports admin | **Fixed** | `settings.manage` |
| Wallet negative balance | **Hardened** | Default `false` |
| Model policies | Partial | API models have policies; admin uses Gates |
| Upload hardening | Partial | MIME/size rules exist; no AV; public disk |
| User privilege grant | Open risk | Users with `users.update` can assign any permission name |
| Audit coverage | Good baseline | Orders, support, wallets, settlements, approvals |

## Policies & Gates

- Registered in `AppServiceProvider`
- `Gate::before` grants all RBAC permissions to super_admin
- Dynamic `Gate::define` for every registry permission
- Model policies: `OrderPolicy`, `FavoritePolicy`, `SavedPassengerPolicy`

**Recommendation:** keep admin on permission middleware; add policies only where object-level ownership matters.

## RBAC fixes applied

| Action | Permission |
|--------|------------|
| Cancel order from support | `support.cancel-order` |
| Full refund | `support.full-refund` |
| Partial refund / compensation | `support.partial-refund` |
| Reverse refund | `finance.reverse-refund` |
| Airports CRUD/import | `settings.manage` |

FormRequests now authorize the same permissions (defense in depth with route middleware).

## Rate limiting

| Route group | Limit |
|-------------|-------|
| API login/register | 10 / min |
| Order sync | 60 / min |
| Order store | 30 / min |
| Support create | 20 / min |
| Support replies / chat messages | 30 / min |
| Favorites / saved passengers | existing |

## File uploads

Support attachments: max 20MB, broad mime list (images/docs/video).

**Follow-ups**

1. Store on private disk + signed URLs
2. Block dangerous extensions explicitly
3. Optional ClamAV / cloud AV scanning for production

## Audit coverage

Recorded via `AuditRecorder` for:

- Order tracked field changes
- Support history actions
- Wallet deposit / adjust / debit
- Settlement create / import / resolve / close
- Approval request / approve / reject / execute / fail

RBAC-sensitive admin actions still also flow through `rbac_audit_logs`.

Retention: `AUDIT_RETENTION_DAYS` (default 180), pruned by `CleanupMonitoringDataJob`.

## Remaining risks (prioritized)

1. **Permission assignment escalation** — restrict grantable permissions to a subset of the actor's permissions
2. **Support Show over-fetch** — payloads/messages volume (performance + data exposure in browser)
3. **Public attachment URLs** — treat as sensitive
4. **API lacking global throttleApi()** — consider Laravel default API rate limiter
5. **Seed admin password** — must be rotated in production

## Checklist for go-live

- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY` and DB passwords
- [ ] `WALLET_ALLOW_NEGATIVE=false` unless finance explicitly opts in
- [ ] Roles re-seeded after permission changes
- [ ] HTTPS only
- [ ] Backups verified with a restore test
- [ ] Queue workers supervised
