Standalone script created from existing plugin.

## Front controller

Requests are routed through `public/index.php`, which loads the Composer autoloader, reads environment variables from `.env` via [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv), defines project path constants, and boots every ARM module in the same order as the WordPress plugin.

## Routing

FastRoute provides lightweight dispatching. A `/health` endpoint is available for readiness checks, and all other paths are routed through the ARM kernel for now.

## Rewrite rules

### Nginx

```
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### LiteSpeed/Apache

The repository ships with `public/.htaccess` that routes unknown paths to the front controller. LiteSpeed users can keep static assets out of PHP by short-circuiting `/assets/` before falling through to the front controller:

```
RewriteEngine On

# Serve assets directly so LiteSpeed can leverage static caching.
RewriteCond %{REQUEST_URI} ^/assets/
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Everything else is handled by the front controller.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

## Database migrations and seeders

The WordPress activation logic has been replaced with a migration runner so the database can be provisioned from the command line.

1. Copy `.env.example` to `.env` (or create `.env`) and provide `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_PREFIX` values. Character set and collation can be overridden with `DB_CHARSET` and `DB_COLLATE`.
2. Install PHP dependencies: `composer install`.
3. Apply migrations and seed defaults (Ubuntu/LiteSpeed): `php bin/migrate up` from the project root. The script is idempotent and will skip already-applied versions.
4. Check pending work without applying it: `php bin/migrate status`.

Seeders replace the plugin’s activation hooks by inserting default service types and option defaults for terms, notification email, labor/tax rates, and callout settings.
