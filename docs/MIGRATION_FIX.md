# Migration & Seeder Idempotency Fix

## Problem Fixed
- Error: `SQLSTATE[42S01]: Base table or view already exists`
- Cause: Migrations weren't idempotent (no `Schema::hasTable()` check)
- Additional issue: `fix_idempotent.php` script accidentally truncated `Schema::create()` callback bodies

## Files Fixed
1. `database/migrations/0001_01_01_000000_create_users_table.php` - Fixed with full table definitions
2. `database/migrations/0001_01_01_000001_create_cache_table.php` - Fixed
3. `database/migrations/0001_01_01_000002_create_jobs_table.php` - Fixed (no syntax errors)
4. `database/migrations/2026_04_24_101816_create_audit_logs_table.php` - Made idempotent

## Pattern for Future Migrations
Always wrap `Schema::create()` in `if (!Schema::hasTable())`:

```php
public function up(): void>
{
    if (!Schema::hasTable('table_name')) {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            // ... column definitions
        });
    }
}
```

## Seeder Fix
- `SettingsSeeder.php` - Uses `updateOrCreate()` instead of `create()`
- `SubscriptionPlanSeeder.php` - Uses `updateOrCreate()` 
- This prevents "duplicate entry" errors

## How to Verify
```bash
cd "C:\Users\Admin\Desktop\uni-management-system"
php artisan migrate --pretend  # Check what would run
php artisan migrate              # Actually run migrations
```

## For Going Live
1. Run `php artisan migrate --pretend` on local FIRST
2. Fix any errors before going live
3. On production: `php artisan migrate --force`
4. Seeders: `php artisan db:seed`

## Template for New Migrations
```php
<?php>

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration>
{
    public function up(): void>
    {
        if (!Schema::hasTable('your_table')) {
            Schema::create('your_table', function (Blueprint $table) {
                $table->id();
                // Add your columns here
                $table->timestamps();
            });
        }
    }

    public function down(): void>
    {
        Schema::dropIfExists('your_table');
    }
};
```
