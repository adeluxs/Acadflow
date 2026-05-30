<?php
$f = file_get_contents('database/migrations/2024_01_01_000001_create_core_tables.php');
$lines = explode("\n", $f);
for($i=0; $i<20; $i++) {
    echo "Line " . ($i+1) . ": " . $lines[$i] . "\n";
}