<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'Nutronyx\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load local .env if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

// Load tenant configuration if present
$tenantConfigFile = __DIR__ . '/../config/tenant.php';
if (file_exists($tenantConfigFile)) {
    require_once $tenantConfigFile;
}

// Initialize database, tenant context, and SDK modules
\Nutronyx\Database::getConnection();
\Nutronyx\Context::init();
\Nutronyx\SDK\ModuleLoader::getInstance()->bootstrap();
