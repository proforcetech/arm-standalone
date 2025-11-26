Standalone script created from existing plugin.

## Front controller

Requests are routed through `public/index.php`, which loads the Composer autoloader, reads environment variables from `.env`, defines project path constants, and boots every ARM module in the same order as the WordPress plugin.

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

The repository ships with `public/.htaccess` that routes unknown paths to the front controller:

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```
