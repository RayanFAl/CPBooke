# Production Readiness — Phase A

Status: **in progress / baseline shipped**

CPBooke is treated as an operations platform. Phase A hardens the system before Feature Freeze and go-live.

## Goals

1. Performance — indexes, safer search, fewer full-table scans
2. Security — RBAC alignment, rate limits, safer defaults
3. Backup & Restore — daily dumps + retention
4. Disaster Recovery — runbooks for DB / BookNow / Queue / Mail
5. Deployment — clear production topology

## What shipped in code

| Area | Change |
|------|--------|
| Security | Support money actions require fine-grained permissions |
| Security | API auth / orders / support writes throttled |
| Security | Airports admin behind `settings.manage` |
| Security | `WALLET_ALLOW_NEGATIVE` default `false` |
| Performance | Production indexes on orders, support SLA, audit, events |
| Performance | Order/global search no longer scans JSON payloads |
| Performance | Wallet alert count uses SQL, not collection filter |
| Ops | `php artisan backup:database` + daily schedule 01:15 |
| Ops | Audit log retention via `CleanupMonitoringDataJob` |

## Documents in this folder

| Doc | Purpose |
|-----|---------|
| [02-deployment.md](./02-deployment.md) | PHP, MySQL, Redis, Nginx, Supervisor, SSL, env |
| [03-backup-restore.md](./03-backup-restore.md) | Backup, restore, retention, restore test |
| [04-disaster-recovery.md](./04-disaster-recovery.md) | DB / BookNow / Queue / Mail recovery |
| [05-security-review.md](./05-security-review.md) | Policies, Gates, RBAC, uploads, audit coverage |
| [phase-b-testing.md](./phase-b-testing.md) | Testing roadmap |
| [phase-c-documentation.md](./phase-c-documentation.md) | Operational docs roadmap |

## Local verification

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan backup:database --keep=14
php artisan queue:work
php artisan schedule:work
```

## Next (still Phase A polish)

- Paginate Support Show messages
- Cache Support inbox counters / notification metrics
- Private disk for support attachments (or signed URLs)
- Privilege escalation guard on user permission assignment
- Redis cutover for cache/queue/session in production
