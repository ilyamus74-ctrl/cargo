examples/structured_core_api/includes/bootstrap.php

<?php
namespace Cargo\Api;

// Общие зависимости, доступные всем модулям API.
require_once __DIR__ . '/../api/utils.php';

// Простейший роутер: подключает модуль по ключу.
class Router
{
    public function __construct(private array $map)
    {
    }

    public function dispatch(string $module): void
    {
        if (!isset($this->map[$module])) {
            http_response_code(404);
            echo json_encode(['error' => 'Unknown module']);
            return;
        }

        require $this->map[$module];
    }
}