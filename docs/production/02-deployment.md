# Deployment Guide

Stack target: **PHP 8.3+ · Laravel 13 · MySQL 8 · Redis (recommended) · Nginx · Supervisor**

## 1. Server packages

```bash
# Ubuntu/Debian sketch
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-xml \
  php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath \
  unzip git
```

Node 20+ is required only for building assets (`npm ci && npm run build`).

## 2. Application layout

```text
/var/www/cpbooke
  current/          # release symlink target
  releases/
  shared/
    .env
    storage/
    storage/app/backups/
```

```bash
cd /var/www/cpbooke/current
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## 3. Environment (production essentials)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ops.example.com
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpbooke
DB_USERNAME=cpbooke
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

MAIL_MAILER=smtp
FILESYSTEM_DISK=local

WALLET_ALLOW_NEGATIVE=false
MONITORING_APPROVALS_ASYNC=true
AUDIT_ENABLED=true
```

Rotate `ADMIN_PASSWORD` immediately after first seed. Never leave seed credentials in production.

## 4. Nginx (HTTPS)

```nginx
server {
    listen 443 ssl http2;
    server_name ops.example.com;

    root /var/www/cpbooke/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/ops.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ops.example.com/privkey.pem;

    client_max_body_size 512M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~* /\.(?!well-known).* {
        deny all;
    }
}
```

Obtain certificates with Certbot (`certbot --nginx`).

## 5. PHP-FPM

- `pm.max_children` sized to RAM
- `upload_max_filesize=512M`
- `post_max_size=512M`
- `memory_limit=512M`

## 6. Supervisor — queue workers

`/etc/supervisor/conf.d/cpbooke-worker.conf`:

```ini
[program:cpbooke-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cpbooke/current/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cpbooke-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cpbooke-worker:*
```

## 7. Scheduler (cron)

```cron
* * * * * www-data cd /var/www/cpbooke/current && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled jobs include health probes, wallet checks, settlement reminders, approval expiry, notification retries, monitoring/audit cleanup, and `backup:database`.

## 8. Storage

- `storage/` and `bootstrap/cache/` must be writable by the PHP user
- Support attachments live on the public disk today — back them up with DB dumps
- Prefer moving attachments to a private disk + signed URLs in a follow-up release

## 9. Redis

Recommended for production:

| Concern | Driver |
|---------|--------|
| Cache | redis |
| Queue | redis |
| Session | redis |

Keep MySQL for durable business data. Database queue is acceptable only for small staging environments.

## 10. Health checks after deploy

```bash
curl -fsS https://ops.example.com/up
php artisan monitoring:dispatch all
php artisan queue:failed
```

Open `/admin/monitoring` and confirm services are green.
