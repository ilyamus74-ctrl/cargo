examples/structured_core_api/core_api.php

<?php
// Минимальный bootstrap для маршрутизации API по зонам ответственности.
// Подключает централизованный bootstrap и передает управление в роутер.

require_once __DIR__ . '/includes/bootstrap.php';

use Cargo\Api\Router;

$router = new Router([
    'users'  => __DIR__ . '/api/users.php',
    'orders' => __DIR__ . '/api/orders.php',
]);

$router->dispatch($_GET['module'] ?? 'users');