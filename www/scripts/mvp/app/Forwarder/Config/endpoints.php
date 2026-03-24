return [
  'login_get' => [
    'method' => 'GET',
    'uri' => '/login',
    'content_type' => 'text/html',
    'expects' => ['status' => 200],
  ],
  'login_post' => [
    'method' => 'POST',
    'uri' => '/login',
    'content_type' => 'application/x-www-form-urlencoded',
    'expects' => ['status' => [200,302]],
  ],
  'check_position' => [
    'method' => 'POST',
    'uri' => '/collect/check-position',
    'content_type' => 'application/json',
    'expects' => ['json_case' => ['success','ops']],
  ],
  'check_package' => [
    'method' => 'POST',
    'uri' => '/collect/check-package',
    'content_type' => 'application/json',
    'expects' => ['json_case' => ['success','ops']],
  ],
];
