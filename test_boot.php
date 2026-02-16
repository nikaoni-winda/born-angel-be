<?php
echo "Starting test_boot...\n";
require __DIR__ . '/vendor/autoload.php';
echo "Autoload loaded\n";
$app = require_once __DIR__ . '/bootstrap/app.php';
echo "App created via bootstrap/app.php\n";
try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "Kernel resolved\n";
} catch (\Exception $e) {
    echo "Error resolving kernel: " . $e->getMessage() . "\n";
}
