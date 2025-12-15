examples/structured_core_api/api/orders.php

<?php
use Cargo\Api\Utils as Utils;

// Логика модуля заказов (HTML-ответ)
$orders = [
    ['id' => 1001, 'total' => 25.5],
    ['id' => 1002, 'total' => 42.0],
];

$list = array_map(
    fn(array $order) => sprintf('<li>№%d — %.2f</li>', $order['id'], $order['total']),
    $orders
);

$html = "<h1>Orders</h1><ul>" . implode('', $list) . "</ul>";

Utils\textResponse($html, 200, 'text/html; charset=utf-8');