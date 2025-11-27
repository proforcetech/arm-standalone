# ARM Repair Estimates - Installation Guide

Complete guide for installing and configuring the ARM (Auto Repair Management) Repair Estimates standalone application.

## Table of Contents

1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [Environment Configuration](#environment-configuration)
7. [Payment Gateway Integration](#payment-gateway-integration)
8. [Email Configuration](#email-configuration)
9. [Scheduled Tasks](#scheduled-tasks)
10. [Post-Installation](#post-installation)
11. [Troubleshooting](#troubleshooting)

---

## Overview

ARM Repair Estimates is a standalone PHP application for managing auto repair estimates, invoices, customers, and vehicles. Originally a WordPress plugin, it has been converted to a standalone application using modern PHP practices with:

- **FastRoute** for lightweight HTTP routing
- **DomPDF** for PDF generation (estimates and invoices)
- **Stripe & PayPal** payment integrations
- **PHPDotenv** for environment configuration
- Database migrations for schema management

---

## System Requirements

### PHP Runtime
- **PHP 8.1 or newer** with PHP-FPM enabled
- Required PHP extensions:
  - `pdo_mysql` - Database connectivity
  - `mysqli` - MySQL interface
  - `mbstring` - Multi-byte string handling
  - `openssl` - Secure communications
  - `json` - JSON encoding/decoding
  - `xml` - XML processing
  - `curl` - HTTP requests
  - `zip` - Archive handling
  - `dom` - DOM manipulation
  - `gd` - Image processing (required by DomPDF)
- Recommended:
  - `intl` - Internationalization support for locale-aware formatting

### Database
- **MySQL 5.7+** or **MariaDB 10.2+**
- Database user with full privileges (CREATE, ALTER, SELECT, INSERT, UPDATE, DELETE)

### Web Server
- **Apache 2.4+** with `mod_rewrite` enabled
- **LiteSpeed** web server
- **Nginx 1.18+**

### System Tools
- **Composer** (latest version) for PHP dependency management
- Command-line PHP access for migrations and scheduled tasks
- Git (for deployment and updates)

### Server Resources
- Minimum 256MB PHP memory limit
- Recommended 512MB+ for PDF generation with large estimates

---

## Installation Steps

### 1. Download and Extract

Clone the repository or extract the application to your web server:

```bash
cd /var/www
git clone <repository-url> arm-standalone
cd arm-standalone
```

### 2. Set Correct Permissions

Ensure the web server user can read the application files:

```bash
# For Apache/LiteSpeed (typically www-data)
sudo chown -R www-data:www-data /var/www/arm-standalone

# For Nginx (typically www-data or nginx)
sudo chown -R www-data:www-data /var/www/arm-standalone

# Set appropriate file permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod +x bin/migrate
```

### 3. Install PHP Dependencies

Use Composer to install all required PHP libraries:

```bash
cd /var/www/arm-standalone
composer install --no-dev --optimize-autoloader
```

For development environments, omit `--no-dev`:

```bash
composer install
```

### 4. Create Environment Configuration

Copy the example environment file and configure it:

```bash
cp .env.example .env
chmod 640 .env
```

**Security Note**: The `.env` file contains sensitive credentials. Ensure it's readable by the web server but not publicly accessible:
- Never commit `.env` to version control
- Restrict permissions: `chmod 640 .env`
- Ensure ownership is correct: `chown www-data:www-data .env`

---

## Database Setup

### 1. Create Database and User

Log into MySQL/MariaDB as root or admin user:

```bash
mysql -u root -p
```

Execute the following SQL commands:

```sql
-- Create database
CREATE DATABASE arm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user and grant privileges
CREATE USER 'arm_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON arm.* TO 'arm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Configure Database Credentials

Edit `.env` and set your database configuration:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=arm
DB_USER=arm_user
DB_PASSWORD=your_secure_password
DB_PREFIX=wp_
DB_CHARSET=utf8mb4
DB_COLLATE=utf8mb4_unicode_ci
```

**Parameters:**
- `DB_HOST` - Database server hostname (usually `localhost`)
- `DB_PORT` - MySQL port (default: `3306`)
- `DB_NAME` - Database name created above
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password
- `DB_PREFIX` - Table prefix (default: `wp_` for WordPress compatibility)
- `DB_CHARSET` - Character set (recommended: `utf8mb4`)
- `DB_COLLATE` - Collation (recommended: `utf8mb4_unicode_ci`)

### 3. Run Database Migrations

Execute migrations to create all required database tables:

```bash
php bin/migrate up
```

This command will:
1. Create the migrations tracking table
2. Run all pending migrations to create application tables
3. Execute seeders to populate default data

**Database Tables Created:**
- `wp_arm_customers` - Customer information
- `wp_arm_vehicles` - Vehicle details linked to customers
- `wp_arm_service_types` - Service categories
- `wp_arm_estimates` - Repair estimates
- `wp_arm_invoices` - Invoices
- `wp_arm_estimate_items` - Line items for estimates
- `wp_arm_invoice_items` - Line items for invoices
- `wp_arm_payments` - Payment records
- `wp_arm_options` - Application settings
- `wp_arm_users` - User accounts (if auth tables migration is applied)
- Additional tables for appointments, credit accounts, etc.

### 4. Verify Migration Status

Check if all migrations have been applied:

```bash
php bin/migrate status
```

Expected output:
```
Pending migrations: none
Pending seeders: none
```

---

## Web Server Configuration

Configure your web server to serve the application with the document root set to `public/` directory.

### Apache / LiteSpeed

The application includes a `public/.htaccess` file with basic rewrite rules. For LiteSpeed optimization, create or edit your virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName arm.example.com
    DocumentRoot /var/www/arm-standalone/public

    <Directory /var/www/arm-standalone/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # LiteSpeed optimization: serve static assets directly
    <Directory /var/www/arm-standalone/public/assets>
        Options -Indexes
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/arm_error.log
    CustomLog ${APACHE_LOG_DIR}/arm_access.log combined
</VirtualHost>
```

**Enhanced `.htaccess` for LiteSpeed** (optional, place in `public/.htaccess`):

```apache
RewriteEngine On

# Serve assets directly so LiteSpeed can leverage static caching
RewriteCond %{REQUEST_URI} ^/assets/
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Everything else is handled by the front controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

Enable the site and reload Apache:

```bash
sudo a2ensite arm
sudo systemctl reload apache2
```

### Nginx

Add the following server block to your Nginx configuration (e.g., `/etc/nginx/sites-available/arm`):

```nginx
server {
    listen 80;
    server_name arm.example.com;
    root /var/www/arm-standalone/public;

    index index.php;

    # Security: deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    access_log /var/log/nginx/arm_access.log;
    error_log /var/log/nginx/arm_error.log;
}
```

Enable the site and reload Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/arm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### SSL/HTTPS Configuration

For production environments, secure your installation with SSL. Using Let's Encrypt with Certbot:

```bash
sudo certbot --apache -d arm.example.com
# or for Nginx
sudo certbot --nginx -d arm.example.com
```

Update your `.env` file:

```env
APP_URL=https://arm.example.com
```

---

## Environment Configuration

### Core Application Settings

Edit `.env` to configure the application URL and security key:

```env
# Application URL (used for generating links in emails and PDFs)
APP_URL=https://arm.example.com

# Application encryption key (generate a random 32+ character string)
APP_KEY=base64:your-random-32-character-key-here
```

**Generate a secure APP_KEY:**

```bash
php -r "echo 'APP_KEY=base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

### PartsTech API Integration

If using PartsTech for parts lookup:

```env
PARTSTECH_API_BASE=https://api.partstech.com
PARTSTECH_API_KEY=your_partstech_api_key
```

To obtain PartsTech credentials:
1. Visit [https://www.partstech.com](https://www.partstech.com)
2. Sign up for an API account
3. Generate API credentials from your dashboard

---

## Payment Gateway Integration

### Stripe Configuration

ARM supports Stripe for credit card payments.

#### 1. Create Stripe Account

1. Visit [https://stripe.com](https://stripe.com)
2. Sign up for an account or log in
3. Navigate to **Developers** → **API keys**

#### 2. Get API Keys

Copy your API keys (use test keys for development):
- **Publishable key** - Used in frontend forms
- **Secret key** - Used for server-side processing

#### 3. Configure Stripe Webhooks

1. Go to **Developers** → **Webhooks**
2. Click **Add endpoint**
3. Enter your webhook URL: `https://arm.example.com/webhook/stripe`
4. Select events to listen for:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. Copy the **Webhook signing secret**

#### 4. Update .env

```env
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key
STRIPE_SECRET_KEY=sk_test_your_secret_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
```

For production, replace `pk_test_` and `sk_test_` with live keys (`pk_live_` and `sk_live_`).

### PayPal Configuration

ARM supports PayPal for payment processing.

#### 1. Create PayPal Business Account

1. Visit [https://developer.paypal.com](https://developer.paypal.com)
2. Log in with your PayPal account
3. Navigate to **Dashboard** → **My Apps & Credentials**

#### 2. Create REST API App

1. Under **REST API apps**, click **Create App**
2. Enter an app name (e.g., "ARM Repair Estimates")
3. Choose **Merchant** as the app type
4. Click **Create App**

#### 3. Get API Credentials

Copy your credentials (use Sandbox for development):
- **Client ID**
- **Secret**

#### 4. Configure Webhooks

1. In your app settings, scroll to **Webhooks**
2. Click **Add Webhook**
3. Enter webhook URL: `https://arm.example.com/webhook/paypal`
4. Select event types:
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.DENIED`
   - `PAYMENT.CAPTURE.REFUNDED`
5. Copy the **Webhook ID**

#### 5. Update .env

```env
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_SECRET=your_paypal_secret
PAYPAL_WEBHOOK_ID=your_webhook_id
```

For production, switch from Sandbox to Live credentials in PayPal Developer Dashboard.

---

## Email Configuration

ARM sends email notifications for estimates, invoices, and appointment reminders.

### SMTP Settings

Configure SMTP in `.env`:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_ENCRYPTION=tls
SMTP_FROM_ADDRESS=no-reply@arm.example.com
SMTP_FROM_NAME="ARM Repair Estimates"
```

### Common SMTP Providers

#### Gmail

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-16-char-app-password
SMTP_ENCRYPTION=tls
```

**Note**: Use an [App Password](https://support.google.com/accounts/answer/185833) instead of your regular Gmail password.

#### Amazon SES

```env
SMTP_HOST=email-smtp.us-east-1.amazonaws.com
SMTP_PORT=587
SMTP_USERNAME=your-ses-smtp-username
SMTP_PASSWORD=your-ses-smtp-password
SMTP_ENCRYPTION=tls
```

#### SendGrid

```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
SMTP_ENCRYPTION=tls
```

#### Mailgun

```env
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USERNAME=postmaster@your-domain.mailgun.org
SMTP_PASSWORD=your-mailgun-password
SMTP_ENCRYPTION=tls
```

### Firewall Configuration

Ensure outbound connections are allowed on SMTP ports:
- **Port 587** (TLS/STARTTLS) - Recommended
- **Port 465** (SSL) - Alternative
- **Port 25** (Unencrypted) - Not recommended

---

## Scheduled Tasks

ARM requires scheduled tasks (cron jobs) for automated maintenance and notifications.

### Required Cron Jobs

Add these cron entries for the web server user (typically `www-data`):

```bash
sudo crontab -e -u www-data
```

Add the following lines:

```cron
# ARM Repair Estimates - Send appointment/estimate reminders every hour
0 * * * * cd /var/www/arm-standalone && php -r "require 'includes/bootstrap.php'; do_action('arm_re_send_reminders');" >> /var/log/arm-reminders.log 2>&1

# ARM Repair Estimates - Daily cleanup tasks at 2 AM
0 2 * * * cd /var/www/arm-standalone && php -r "require 'includes/bootstrap.php'; do_action('arm_re_cleanup');" >> /var/log/arm-cleanup.log 2>&1
```

**Note**: The above uses WordPress-style action hooks. You may need to adjust based on the actual implementation.

### Alternative: Using WP-CLI (if embedded in WordPress)

If ARM is running alongside WordPress:

```cron
0 * * * * wp cron event run arm_re_send_reminders
0 2 * * * wp cron event run arm_re_cleanup
```

### Create Log Files

Ensure log files exist with proper permissions:

```bash
sudo touch /var/log/arm-reminders.log /var/log/arm-cleanup.log
sudo chown www-data:www-data /var/log/arm-reminders.log /var/log/arm-cleanup.log
sudo chmod 644 /var/log/arm-reminders.log /var/log/arm-cleanup.log
```

---

## Post-Installation

### 1. Test the Installation

Visit the health check endpoint to verify the application is running:

```bash
curl https://arm.example.com/health
```

Expected response (JSON with `Content-Type: application/json` header):
```json
{"status":"ok"}
```

You can also test with verbose output to see the headers:
```bash
curl -v https://arm.example.com/health
```

### 2. Create Admin User

The database seeders should create a default admin user. Check the seeder file at `database/seeders/20240702000000_seed_auth.php` for default credentials, or create a user manually.

### 3. Access the Application

Navigate to your configured URL:

```
https://arm.example.com
```

### 4. Configure Application Settings

Log in as admin and configure:
- **Labor rates** - Hourly rates for different service types
- **Tax rates** - Sales tax percentages
- **Terms and conditions** - Default terms for estimates/invoices
- **Email templates** - Customize notification emails
- **Service types** - Add/edit available repair services

### 5. Test Key Features

Verify the following functionality:
- [ ] Customer creation and management
- [ ] Vehicle addition
- [ ] Estimate generation
- [ ] Invoice creation
- [ ] PDF export (estimates and invoices)
- [ ] Email delivery
- [ ] Payment processing (test mode)
- [ ] Appointment scheduling

---

## Troubleshooting

### Database Connection Errors

**Error**: `SQLSTATE[HY000] [2002] Connection refused`

**Solutions**:
1. Verify database is running: `sudo systemctl status mysql`
2. Check credentials in `.env`
3. Ensure database user has proper permissions
4. For remote databases, verify `DB_HOST` and firewall rules

### /health Endpoint Returns 404

If the base URL works but `/health` returns a 404 error, the web server is not routing all requests through `index.php`.

**Diagnosis**:
```bash
# This should work (returns catch-all route):
curl http://yourdomain.com/
# Output: {"message":"ARM front controller initialized","path":""}

# This returns 404:
curl http://yourdomain.com/health
# Output: Not Found (404)
```

**Solutions**:

1. **Verify document root is set to `public/` directory**:
   - Apache/LiteSpeed: Check your virtual host `DocumentRoot`
   - Nginx: Check `root` directive in server block

2. **Check `.htaccess` is being read (Apache/LiteSpeed)**:
   ```bash
   # Verify .htaccess exists in public/ directory
   ls -la public/.htaccess

   # Test if mod_rewrite is enabled
   apache2ctl -M | grep rewrite
   # Should show: rewrite_module (shared)

   # Ensure AllowOverride is set correctly in virtual host:
   # <Directory /path/to/public>
   #     AllowOverride All
   # </Directory>
   ```

3. **Test rewrite rules manually (Apache/LiteSpeed)**:
   Add this to `public/.htaccess` temporarily for debugging:
   ```apache
   RewriteEngine On
   RewriteBase /

   # Log rewrite activity (requires write permissions)
   RewriteLog "/tmp/rewrite.log"
   RewriteLogLevel 3

   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^ index.php [QSA,L]
   ```

4. **For Nginx, verify try_files directive**:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

   Ensure this is **before** the `location ~ \.php$` block.

5. **Check if a static file named `health` exists**:
   ```bash
   # If this exists, it will be served instead of routing to index.php
   ls -la public/health

   # Remove if found
   rm public/health
   ```

6. **Test index.php directly**:
   ```bash
   # This should work even with broken rewrites
   curl 'http://yourdomain.com/index.php?/health'
   ```

7. **Verify PHP is executing**:
   Create `public/test.php`:
   ```php
   <?php
   echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set') . PHP_EOL;
   echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set') . PHP_EOL;
   ```

   Then test:
   ```bash
   curl http://yourdomain.com/test.php
   ```

   **Remove `test.php` after testing!**

8. **Restart web server after configuration changes**:
   ```bash
   # Apache
   sudo systemctl restart apache2

   # Nginx
   sudo systemctl restart nginx

   # LiteSpeed
   sudo systemctl restart lsws
   ```

### 500 Internal Server Error

**Solutions**:
1. Check PHP error logs:
   ```bash
   sudo tail -f /var/log/php8.1-fpm.log
   sudo tail -f /var/log/apache2/arm_error.log
   ```
2. Verify file permissions
3. Ensure all PHP extensions are installed:
   ```bash
   php -m | grep -E 'pdo_mysql|mysqli|mbstring|openssl|json|xml|curl|zip|dom|gd'
   ```

### PDF Generation Fails

**Error**: PDF export returns blank or errors

**Solutions**:
1. Verify `gd` extension is installed: `php -m | grep gd`
2. Increase PHP memory limit in `php.ini`:
   ```ini
   memory_limit = 512M
   ```
3. Check DomPDF font cache permissions:
   ```bash
   sudo mkdir -p /var/www/arm-standalone/storage/fonts
   sudo chown -R www-data:www-data /var/www/arm-standalone/storage
   ```

### Emails Not Sending

**Solutions**:
1. Test SMTP connectivity:
   ```bash
   telnet smtp.example.com 587
   ```
2. Verify SMTP credentials in `.env`
3. Check firewall allows outbound SMTP ports (587/465)
4. Review mail logs for errors
5. Test with a simple SMTP testing script

### Payment Gateway Errors

**Solutions**:
1. Verify API keys are correct (test vs. live)
2. Check webhook URLs are publicly accessible
3. Review Stripe/PayPal dashboard for error details
4. Ensure SSL is properly configured for production
5. Verify webhook signing secrets match

### Migration Errors

**Error**: Migrations fail to run

**Solutions**:
1. Ensure database user has CREATE and ALTER privileges
2. Check for existing tables with same names
3. Review migration file syntax
4. Run with verbose output to identify specific failure

### Permission Denied Errors

**Solutions**:
1. Fix ownership:
   ```bash
   sudo chown -R www-data:www-data /var/www/arm-standalone
   ```
2. Fix permissions:
   ```bash
   find /var/www/arm-standalone -type d -exec chmod 755 {} \;
   find /var/www/arm-standalone -type f -exec chmod 644 {} \;
   chmod +x /var/www/arm-standalone/bin/migrate
   ```
3. Ensure `.env` is readable: `chmod 640 .env`

---

## Updating the Application

To update ARM to a newer version:

```bash
# 1. Backup database
mysqldump -u arm_user -p arm > arm_backup_$(date +%Y%m%d).sql

# 2. Pull latest code
git pull origin main

# 3. Update dependencies
composer install --no-dev --optimize-autoloader

# 4. Run new migrations
php bin/migrate status
php bin/migrate up

# 5. Clear any application cache if implemented
composer cache:warmup
```

---

## Security Best Practices

1. **Keep `.env` secure**
   - Never commit to version control
   - Restrict file permissions: `chmod 640`
   - Store backups encrypted

2. **Use HTTPS in production**
   - Install SSL certificate
   - Redirect HTTP to HTTPS
   - Update `APP_URL` to use `https://`

3. **Regular updates**
   - Keep PHP updated
   - Update Composer dependencies regularly: `composer update`
   - Monitor security advisories

4. **Database security**
   - Use strong passwords
   - Limit user privileges to only what's needed
   - Regular backups

5. **File permissions**
   - Application files: 644 (files) and 755 (directories)
   - Configuration: 640 for `.env`
   - Never use 777 permissions

6. **Firewall configuration**
   - Only expose necessary ports (80, 443)
   - Restrict database access to localhost unless needed
   - Use fail2ban to prevent brute force attacks

---

## Support and Documentation

- **Project Repository**: [GitHub URL]
- **API Documentation**: `docs/route-plan.md`
- **Deployment Guide**: `docs/deployment.md`
- **Issue Tracker**: [GitHub Issues URL]

---

## License

[Include license information here]

---

**Last Updated**: November 2025
