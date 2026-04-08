# Alpha Server Deployment

This project is ready for a single-server alpha on Ubuntu 24.04 with:

- `nginx`
- `php8.3-fpm`
- `mariadb` or `postgresql`
- `systemd`
- `node 22`
- `composer`

The checked-in deployment assets live in:

- `.env.production.example`
- `deploy/nginx/lumiis.conf`
- `deploy/systemd/lumiis-queue.service`
- `deploy/systemd/lumiis-scheduler.service`
- `deploy/systemd/lumiis-scheduler.timer`
- `scripts/deploy-production.sh`

## 1. Provision the VPS

Use at least:

- 2 vCPU
- 4 GB RAM
- 40+ GB SSD
- Ubuntu 24.04 LTS

Install the base stack:

```bash
sudo apt update
sudo apt install -y nginx mariadb-server unzip git curl
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
sudo apt install -y composer
```

Install Node 22 from NodeSource or your preferred package source:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Create the database

Example for MariaDB:

```sql
CREATE DATABASE lumiis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lumiis'@'127.0.0.1' IDENTIFIED BY 'replace-this-password';
GRANT ALL PRIVILEGES ON lumiis.* TO 'lumiis'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## 3. Clone the application

This deployment pack assumes a direct checkout in `/var/www/lumiis`.
There is no release-symlink layout with a `current/` folder in the checked-in setup.

```bash
sudo mkdir -p /var/www/lumiis
sudo chown -R "$USER":www-data /var/www/lumiis
git clone <your-repo-url> /var/www/lumiis
cd /var/www/lumiis
```

Grant Laravel write access:

```bash
chmod -R ug+rwx storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## 4. Configure production env

Start from the checked-in template:

```bash
cp .env.production.example .env
php artisan key:generate
```

Update these values before first boot:

- `APP_URL`
- `APP_KEY`
- `DB_*`
- `MAIL_*`
- `SESSION_DOMAIN`
- `GAME_SIMULATOR_API_ENABLED=false`

Important production values:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `SESSION_SECURE_COOKIE=true`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`

## 5. First deploy

Run the checked-in deploy script:

```bash
bash scripts/deploy-production.sh
```

That script will:

- install PHP dependencies without dev packages
- build frontend assets
- run database migrations
- run `WorldSeeder`
- refresh Laravel config/view caches

`WorldSeeder` is safe during deploys because the world content uses idempotent `upsert` calls.

## 6. Nginx and TLS

Issue the first certificate before enabling the final HTTPS config:

```bash
sudo apt install -y certbot
sudo systemctl stop nginx
sudo certbot certonly --standalone -d alpha.example.com
```

Then copy the checked-in site config:

```bash
sudo cp deploy/nginx/lumiis.conf /etc/nginx/sites-available/lumiis.conf
sudo ln -s /etc/nginx/sites-available/lumiis.conf /etc/nginx/sites-enabled/lumiis.conf
sudo nginx -t
sudo systemctl start nginx
```

Before enabling the config, replace:

- `alpha.example.com`
- `/var/www/lumiis` if you deployed elsewhere
- `php8.3-fpm.sock` if your PHP socket path differs

## 7. Start background services

Install the queue worker:

```bash
sudo cp deploy/systemd/lumiis-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now lumiis-queue.service
```

Install the scheduler timer:

```bash
sudo cp deploy/systemd/lumiis-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/lumiis-scheduler.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now lumiis-scheduler.timer
```

The app does not currently define scheduled jobs, but enabling the timer now keeps the server ready for future scheduled work without another ops pass.

## 8. Create the first admin

After you register a real account through the browser:

```bash
php artisan game:grant-admin you@example.com
```

## 9. Updates after the first deploy

For each update:

```bash
cd /var/www/lumiis
git pull --ff-only
bash scripts/deploy-production.sh
```

If you already built assets elsewhere and only want backend rollout:

```bash
RUN_BUILD=0 bash scripts/deploy-production.sh
```

## 10. Smoke checks

Run these checks after every deploy:

- `php artisan about`
- `php artisan test` on staging before prod
- open `/`, `/login`, `/register`, `/dashboard`
- register a new account
- promote an admin
- verify password reset email delivery
- run `composer run release:gate` on staging before public alpha releases

## 11. Minimum ops safety

Do not launch alpha without these:

- daily database backups
- log rotation for `storage/logs`
- UFW or cloud firewall allowing only `22`, `80`, and `443`
- SSH keys only, no password login
- `fail2ban` or equivalent SSH protection
- monitoring for `nginx`, `php8.3-fpm`, and `lumiis-queue.service`

## Notes

- Route caching is intentionally not part of the deploy script yet because `routes/web.php` still contains a closure route for the landing page.
- The checked-in release gate can self-start a local server on normal hosts. In sandboxed environments, the Playwright step may require a less restricted shell.
