# Disaster Recovery Runbook

Goal: if something stops, ops knows **what to check** and **how to recover** within minutes.

## 0. First 60 seconds

1. Open `/admin/monitoring`
2. Check `/up`
3. Check Supervisor: `sudo supervisorctl status`
4. Check Redis: `redis-cli ping`
5. Check MySQL: `mysqladmin ping`
6. Check failed jobs: `php artisan queue:failed`

---

## 1. Database is down

**Symptoms:** 500s, Monitoring Database 🔴, artisan commands fail.

**Immediate**

1. Fail closed: `php artisan down` on app nodes if writes are unsafe.
2. Page DB owner / host provider.
3. Confirm disk space and MySQL process.

**Recover**

1. Restart MySQL.
2. If data loss/corruption: restore latest dump ([03-backup-restore.md](./03-backup-restore.md)).
3. `php artisan up`
4. Restart workers.
5. Spot-check Approvals / Wallets / Settlements for in-flight actions.

**Follow-up**

- Capture root cause (disk, OOM, bad migration).
- If restore used: document dump timestamp and RTO.

---

## 2. BookNow / provider API is down

**Symptoms:** Provider Health 🔴/🟡, sync failures, Monitoring BookNow fail, `provider_api_events` errors spike.

**Immediate**

1. Do **not** mass-retry blindly.
2. Pause high-volume sync if possible (feature flag / stop mobile traffic).
3. Communicate to Support: booking sync degraded.

**Recover**

1. Confirm BookNow status with provider.
2. When healthy: retry failed approvals with **Retry Execution** (Approvals UI).
3. Re-run targeted order sync for stuck bookings.
4. Watch Provider Health until green.

**Workarounds while down**

- Manual ticket issuance offline process (ops playbook).
- Queue wallet debits only after confirmed booking success (already idempotent by reference).

---

## 3. Queue / workers stopped

**Symptoms:** Monitoring Queue 🔴, pending jobs grow, notifications/approvals stuck, backups/probes not updating.

**Immediate**

```bash
sudo supervisorctl status
sudo supervisorctl start cpbooke-worker:*
php artisan queue:work redis --once   # smoke
php artisan queue:failed
```

**Recover**

1. Fix crash cause (memory, bad job, Redis down).
2. Restart workers.
3. Retry failed jobs selectively: `php artisan queue:retry <id>` or `queue:retry all` only when safe.
4. Confirm Monitoring probes refresh within 5 minutes.

**If Redis queue is unavailable**

- Temporary: set `QUEUE_CONNECTION=database`, clear config cache, restart workers pointing at database.
- Move back to Redis after Redis is healthy.

---

## 4. Mail / SMS / WhatsApp stopped

**Symptoms:** Monitoring Mail/SMS/WhatsApp 🟡/🔴, notification failures in Monitoring signals, customer complaints.

**Immediate**

1. Check mailer credentials and provider status.
2. Confirm `.env` `MAIL_*` / notification endpoints.
3. Use in-app notifications as fallback for staff.

**Recover**

1. Fix provider credentials / quota.
2. Scheduler already runs `RetryFailedNotificationsJob` every 30 minutes.
3. Manually retry from Notifications admin if needed.
4. For finance-critical messages, send via alternate channel and note in Audit/Support.

---

## 5. Application node crash / Nginx / PHP-FPM

**Symptoms:** site unreachable, `/up` fails, SSL errors.

**Recover**

```bash
sudo systemctl status nginx php8.3-fpm
sudo systemctl restart php8.3-fpm nginx
```

Verify release symlink and `storage` permissions. Redeploy last known good release if code is broken.

---

## RTO / RPO targets (suggested)

| Scenario | RTO | RPO |
|----------|-----|-----|
| App node | 15 min | 0 (stateless) |
| Queue workers | 5–15 min | job retry |
| MySQL | 1–2 h | ≤ 24 h (daily backup) — improve with binlogs |
| BookNow outage | depends on provider | no local data loss |
| Mail | 1 h | delayed delivery |

Improve RPO with binlog / continuous backup when finance volume grows.
