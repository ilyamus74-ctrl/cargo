examples/structured_core_api/api/users.php

<?php
use Cargo\Api\Utils as Utils;

// Логика модуля пользователей
Utils\jsonResponse([
    'module' => 'users',
    'users' => [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ],
]);