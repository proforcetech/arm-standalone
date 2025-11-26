# Deployment checklist (Ubuntu + LiteSpeed)

Use this checklist when promoting the standalone ARM application to a LiteSpeed-backed Ubuntu host.

## PHP runtime

- PHP 8.1 or newer with `php-fpm` and the following extensions enabled: `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `json`, `xml`, `curl`, `zip`, `dom`, and `gd` (required by dompdf for PDF export). Intl (`intl`) is recommended for locale-aware formatting.
- Composer installed system-wide so production builds can run `composer install --no-dev`.
- CLI PHP available for cron jobs and install scripts (`php bin/migrate`).

## Environment configuration

- Copy `.env.example` to `.env` and populate database credentials, payment processor keys (Stripe + PayPal), PartsTech base URL/key, SMTP details, and `APP_URL` for link generation.
- Ensure the web server user can read `.env` but restrict it from world read access (`chmod 640 .env` with the PHP-FPM user group is typical).

## Web server and rewrites

- Serve the project root with the document root set to `public/`.
- Keep the rewrite rules from `public/.htaccess` (or the README snippet) so unknown routes hit `public/index.php`. Add the LiteSpeed optimization that short-circuits `/assets/` so static files bypass PHP.
- Confirm `assets/` and `public/` are world-readable; `bin/migrate` should be executable (`chmod +x bin/migrate`).

## Database migrations

- Run `composer install --no-dev` followed by `php bin/migrate up` after the first deploy and whenever schema updates ship. Use `composer migrate:status` to confirm pending work before cutovers.

## Scheduled jobs and notifications

- The codebase still uses the WordPress-style scheduled hooks `arm_re_cleanup` (daily) and `arm_re_send_reminders` (hourly) for maintenance and reminder delivery. Wire these into a real cron runner (or WP-CLI if embedded in WordPress) so they execute on schedule instead of relying on traffic-driven triggers.
- If email reminders are enabled, verify SMTP credentials and outbound mail ports (587/465) are permitted by the host firewall.
