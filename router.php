<?php
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$file = __DIR__ . rawurldecode($uri);

// If physical file exists (static asset, image, css, js) and is not a directory, serve it directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// If extensionless PHP file exists (e.g., /shop -> shop.php)
if (file_exists($file . '.php') && is_file($file . '.php')) {
    require $file . '.php';
    exit;
}

// If directory with index.php exists
if (is_dir($file) && file_exists($file . '/index.php')) {
    require $file . '/index.php';
    exit;
}

// Fallback to main index.php
require __DIR__ . '/index.php';
