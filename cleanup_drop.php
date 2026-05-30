<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use Illuminate\Support\Facades\DB;

// Disable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS=0');

// Get all tables
$tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
echo "Dropping tables...\n";
foreach($tables as $table) {
    $tableName = $table->TABLE_NAME;
    try {
        DB::statement("DROP TABLE IF EXISTS `$tableName`");
        echo "? Dropped: $tableName\n";
    } catch (\Exception $e) {
        echo "? Failed to drop $tableName: " . $e->getMessage() . "\n";
    }
}

// Re-enable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS=1');
echo "Done!\n";
?>
