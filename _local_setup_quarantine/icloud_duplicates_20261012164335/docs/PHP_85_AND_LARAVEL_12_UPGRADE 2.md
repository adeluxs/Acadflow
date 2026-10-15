# PHP 8.5 and Laravel framework upgrade

AcadFlow now targets Laravel `^12.40` so the framework itself uses the namespaced PDO MySQL constants required by PHP 8.5. The application-level `config/database.php` also resolves `Pdo\Mysql::ATTR_SSL_CA` safely and falls back only when the legacy constant actually exists. MySQL SSL options are omitted when `pdo_mysql` or the CA path is unavailable.

The old Laravel 11 lock file is intentionally not shipped because restoring it would restore the deprecated framework code. A fresh lock file must be generated from `composer.json` on a connected installation machine.

## Upgrade an existing installation

1. Put the application in maintenance mode and back up the database, `.env`, and storage.
2. Use Composer 2 and PHP 8.2 or newer:

```bash
composer update --with-all-dependencies
composer validate --strict
php artisan optimize:clear
php artisan migrate --force
php artisan test
php artisan route:list
```

3. Commit the generated `composer.lock` and deploy that lock file to every environment.
4. Start the web process, queue workers, and scheduler, then verify database SSL connectivity if `MYSQL_ATTR_SSL_CA` is configured.

## Fresh installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan optimize:clear
php artisan test
php artisan serve
```

A successful PHP 8.5 acceptance run must show no `PDO::MYSQL_ATTR_SSL_CA` deprecation from either the application or `vendor/laravel/framework`.
