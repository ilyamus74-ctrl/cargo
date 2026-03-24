<?php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../core_helpers.php';

auth_require_login();
header('Content-Type: application/json; charset=utf-8');

$_POST['action'] = 'forwarder_scan';
$response = [
    'status' => 'error',
    'message' => 'Forwarder handler did not return response',
];

require __DIR__ . '/forwarder_actions.php';

echo json_encode($response, JSON_UNESCAPED_UNICODE);
