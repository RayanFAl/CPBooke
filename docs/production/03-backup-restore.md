# Backup & Restore

## Policy

| Item | Policy |
|------|--------|
| Frequency | Daily at **01:15** (`backup:database`) |
| Retention | Keep **14** daily dumps by default (`--keep=14`) |
| Contents | Full MySQL dump (gzip) |
| Also back up | `storage/app` (attachments, backups folder itself off-box) |
| Off-site | Copy dumps to S3/object storage or another region nightly |
| Restore test | Monthly — restore into staging and smoke-test login + one order |

## Create a backup

```bash
php artisan backup:database --keep=14
```

Dumps land in:

```text
storage/app/backups/<database>_YYYY-mm-dd_HHMMSS.sql.gz
```

Requires `mysqldump` on the host PATH and a MySQL connection.

### Manual shell alternative

```bash
mysqldump --single-transaction --routines --triggers \
  -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  | gzip -9 > "cpbooke_$(date +%F_%H%M%S).sql.gz"
```

### Windows (PowerShell sketch)

```powershell
$env:MYSQL_PWD = $env:DB_PASSWORD
mysqldump --single-transaction --routines --triggers `
  --host=$env:DB_HOST --user=$env:DB_USERNAME $env:DB_DATABASE `
  | Out-File -Encoding ascii dump.sql
```

Prefer running the Artisan command on the app server.

## Restore

1. Put the app in maintenance mode:

```bash
php artisan down
sudo supervisorctl stop cpbooke-worker:*
```

2. Restore the dump:

```bash
gunzip -c storage/app/backups/cpbooke_2026-07-22_011500.sql.gz \
  | mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE"
```

3. Restore `storage/app` from the matching file backup if attachments are required.

4. Clear caches and bring services back:

```bash
php artisan config:cache
php artisan route:cache
php artisan up
sudo supervisorctl start cpbooke-worker:*
```

5. Smoke test:

- `/up`
- Admin login
- Open Monitoring
- Open one Order + one Support ticket
- Confirm Queue workers are processing (`php artisan queue:work` / Supervisor)

## Retention & off-site

- Local: 14 days via `--keep`
- Off-site: keep at least **30–90 days** depending on finance/audit requirements
- Never store production dumps on developer laptops unencrypted

## Restore test checklist (monthly)

- [ ] Restore latest dump into staging DB
- [ ] `php artisan migrate --force` (should be no-op if dump is current)
- [ ] Login as ops user
- [ ] Verify orders / wallets / approvals counts look sane
- [ ] Verify a support attachment opens
- [ ] Record time-to-restore in the runbook log
