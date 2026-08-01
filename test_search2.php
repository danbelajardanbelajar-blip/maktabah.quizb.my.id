<?php
require 'app/Config/Database.php';
require 'app/Helpers/AuthHelper.php';
require 'app/Helpers/SearchHelper.php';
require 'app/Controllers/SearchController.php';

$_GET['q'] = 'فتح القريب';

echo "Testing Search Books...\n";
$controller = new App\Controllers\SearchController();
try {
    ob_start();
    $controller->handleSearchBooks();
    $out = ob_get_clean();
    echo substr($out, 0, 500) . "\n";
} catch (Throwable $e) {
    echo "ERROR (Books): " . $e->getMessage() . "\n";
}

echo "Testing Search Categories...\n";
try {
    ob_start();
    $controller->handleSearchCategories();
    $out = ob_get_clean();
    echo substr($out, 0, 500) . "\n";
} catch (Throwable $e) {
    echo "ERROR (Categories): " . $e->getMessage() . "\n";
}

echo "Testing Search Scholarium...\n";
try {
    ob_start();
    $controller->handleSearchScholarium();
    $out = ob_get_clean();
    echo substr($out, 0, 500) . "\n";
} catch (Throwable $e) {
    echo "ERROR (Scholarium): " . $e->getMessage() . "\n";
}
