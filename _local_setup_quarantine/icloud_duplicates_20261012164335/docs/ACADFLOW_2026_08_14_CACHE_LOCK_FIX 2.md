# AcadFlow Cache Lock Configuration Fix

## Problem

Laravel scheduler mutexes use the configured cache store's atomic lock implementation. When the database cache store is active and `DB_CACHE_LOCK_TABLE` exists in `.env` but is blank, Laravel receives an empty lock table name and MySQL generates SQL against `` (an empty table name).

## Fix

- `.env.example` now defaults `DB_CACHE_LOCK_TABLE=cache_locks`.
- `config/cache.php` now treats blank cache store/table/prefix values safely instead of allowing an empty string to override Laravel defaults.
- Database cache lock table falls back to `cache_locks`.
- The existing migration `0001_01_01_000001_create_cache_table.php` already creates both `cache` and `cache_locks`.

## Existing installations

If the current `.env` contains `DB_CACHE_LOCK_TABLE=`, change it to:

```env
DB_CACHE_LOCK_TABLE=cache_locks
```

Then run:

```bash
php artisan optimize:clear
php artisan migrate
```

If Redis is configured as the cache store, use `CACHE_STORE=redis`; the database lock table is then not used by the default cache store.
