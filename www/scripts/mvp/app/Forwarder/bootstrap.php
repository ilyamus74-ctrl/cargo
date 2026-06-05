<?php

declare(strict_types=1);

if (!defined('FORWARDER_BOOTSTRAP_DONE')) {
    define('FORWARDER_BOOTSTRAP_DONE', true);

    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\Forwarder\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

        if (is_file($filePath)) {
            require_once $filePath;
        }
    });
}
