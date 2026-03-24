<?php

declare(strict_types=1);

return [
    'endpoints' => [
        'login_get' => [
            'method' => 'GET',
            'path' => '/login',
            'notes' => [
                'request' => 'No payload. Used to initialize cookies and retrieve XSRF token.',
                'response' => 'HTTP 200 expected. XSRF token should be present in cookie or page meta.',
            ],
        ],
        'login_post' => [
            'method' => 'POST',
            'path' => '/login',
            'notes' => [
                'request' => 'Fields: username, password, _token (Laravel style form auth).',
                'response' => 'Expected business case: AUTH_OK. Session cookie must be returned.',
            ],
        ],
        'check_position' => [
            'method' => 'POST',
            'path' => '/api/check-position',
            'notes' => [
                'request' => 'Fields: container.',
                'response' => 'Expected business case: POSITION_OK or POSITION_NOT_FOUND.',
            ],
        ],
        'check_package' => [
            'method' => 'POST',
            'path' => '/api/check-package',
            'notes' => [
                'request' => 'Fields: track, container.',
                'response' => 'Expected business case: PACKAGE_OK or PACKAGE_NOT_DECLARED.',
            ],
        ],
    ],
    'http' => [
        'timeout_connect_ms' => (int)(getenv('FORWARDER_TIMEOUT_CONNECT_MS') ?: 3000),
        'timeout_total_ms' => (int)(getenv('FORWARDER_TIMEOUT_TOTAL_MS') ?: 10000),
        'retry_count' => (int)(getenv('FORWARDER_RETRY_COUNT') ?: 1),
        'retry_delay_ms' => (int)(getenv('FORWARDER_RETRY_DELAY_MS') ?: 250),
    ],
];
