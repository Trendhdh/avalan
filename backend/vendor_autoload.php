<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the demo.
 * ---------------------------------------------------------------
 * Production uses Composer's real generated autoloader (see
 * composer.json in this same folder — `composer install` regenerates
 * vendor/autoload.php from it). This hand-written equivalent exists
 * only so a reviewer can run the demo with zero dependencies —
 * `php -S localhost:8000 public/index.php` — without needing Composer
 * installed. If Composer IS available, `composer install` will
 * generate a real vendor/autoload.php that also works; this file is
 * skipped automatically in that case.
 */

$realAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($realAutoload)) {
    require $realAutoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Avalan\\SmartPay\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
