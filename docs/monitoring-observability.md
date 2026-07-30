# Monitoring & Observability (Phase 1)

## What you get

Admin page: `/admin/monitoring`

### 1) Operational signals
- Queue Jobs / Failed Jobs
- Exceptions / Slow Requests / API Errors
- Wallet Alerts / Settlement Alerts
- Email / WhatsApp failures
- Pending Approvals

### 2) System Health (🟢 / 🟡 / 🔴)
Application · Database · Queue · Cache · Mail · SMS · WhatsApp · BookNow · Insurance · eSIM · Payment

### 3) Background jobs
Scheduled via `routes/console.php`:

| Job | Cadence |
|-----|---------|
| `RunSystemHealthProbesJob` | every 5 min |
| `CheckProviderWalletsJob` | every 15 min |
| `RemindOpenSettlementsJob` | daily 08:00 |
| `ExpirePendingApprovalsJob` | hourly |
| `RetryFailedNotificationsJob` | every 30 min |
| `CleanupMonitoringDataJob` | daily 02:30 |

Optional async approvals:
```env
MONITORING_APPROVALS_ASYNC=true
```
Then approved actions run via `ExecuteApprovalActionJob` (Queue → Worker → Completed).

### 4) Local ops

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder

# process queue
php artisan queue:work

# run scheduler (production: cron * * * * * php artisan schedule:run)
php artisan schedule:work

# manual dispatch
php artisan monitoring:dispatch all
php artisan monitoring:dispatch health
```

## Tables
- `system_health_checks` — probe history
- `application_events` — exceptions, slow requests, system alerts
