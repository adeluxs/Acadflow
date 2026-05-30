<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$routes = $app->make('router')->getRoutes();
foreach ($routes as $route) {
    if (strpos($route->getName(), 'attendance') !== false) {
        echo $route->getName() . "\n";
    }
}

// Also check if route exists
$routes = $app->make('router')->getRoutes();
$attendanceRoutes = [];
foreach ($routes as $route) {
    $name = $route->getName();
    if ($name && strpos($name, 'attendance') !== false) {
        $attendanceRoutes[] = $name;
    }
}
sort($attendanceRoutes);
echo "\nAll attendance routes:\n";
foreach ($attendanceRoutes as $r) {
    echo "  - $r\n";
}
