# MySQL Migration Identifier Safety

MySQL limits schema identifiers such as index, unique-key, and foreign-key names to 64 characters.
Laravel normally generates names from the table and column names, which can exceed that limit for long AcadFlow schema names.

## Protection included in AcadFlow

1. All migration identifiers currently capable of exceeding 64 characters use explicit short names.
2. `scripts/check-mysql-identifiers.php` statically checks all migrations without requiring a database connection.
3. Composer runs this check during `pre-autoload-dump` so invalid migrations are caught during install/update.
4. `AppServiceProvider` runs the same check before every `php artisan migrate*` command.
5. `tests/Architecture/MysqlIdentifierLengthTest.php` protects CI/test runs.
6. `2026_08_07_000001_repair_mysql_identifier_indexes.php` repairs indexes that may be missing after a previous MySQL migration stopped midway.

## Before adding a migration

Run:

```bash
composer schema-lint
```

or, without Composer:

```bash
php scripts/check-mysql-identifiers.php
```

If the checker reports a violation, give the index/unique/foreign constraint an explicit short name.

## Recovering from the August 2026 ai_grounding_sources failure

Do not drop the database and do not run `migrate:fresh` on an installation that contains data.
After updating the source code, run:

```bash
php artisan optimize:clear
php scripts/check-mysql-identifiers.php
php artisan migrate
```

The original migration is safe to rerun because its table creation steps are guarded with `Schema::hasTable()`. The final repair migration detects any missing index from the interrupted run and creates it with a valid short name.
