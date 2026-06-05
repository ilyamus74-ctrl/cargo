examples/structured_core_api/api/utils.php

<?php
namespace Cargo\Api\Utils;

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function textResponse(string $body, int $status = 200, string $contentType = 'text/plain; charset=utf-8'): void
{
    http_response_code($status);
    header('Content-Type: ' . $contentType);
    echo $body;
}