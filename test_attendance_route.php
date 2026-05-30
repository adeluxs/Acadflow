<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

// Clear caches
$app->make('files')->delete($app->getCachedRoutesPath());

// Rebuild router
$app->make('router')->getRoutes()->refreshNameLookups();

$routes = $app->make('router')->getRoutes();
$found = false;
foreach ($routes as $route) {
    echo $route->getName() . "\n";
    if (strpos($route->getName(), 'attendance.start') !== false) {
        $found = true;
        echo "FOUND: " . $route->getName() . "\n";
        echo "URI: " . $route->uri() . "\n";
        echo "Action: " . $route->getActionName() . "\n";
    }
}

if (!$found) {
    echo "\nattendance.start route NOT FOUND\n";
    echo "\nChecking all attendance routes:\n";
    foreach ($routes as $route) {
        $name = $route->getName();
        if ($name && strpos($name, 'attendance') !== false) {
            echo "  - $name\n";
        }
    }
}
