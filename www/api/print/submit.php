<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../configs/secure.php';
require_once __DIR__ . '/_lib.php';

header('Content-Type: application/json; charset=utf-8');

$data = print_read_json_body();
$deviceUid = trim((string)($data['device_uid'] ?? ''));
$labelUrl = trim((string)($data['label_url'] ?? ''));
$labelBase64 = trim((string)($data['label_base64'] ?? ''));
$fileName = trim((string)($data['file_name'] ?? 'label.pdf'));

if ($deviceUid === '') {
    print_json_response(['status' => 'error', 'message' => 'device_uid required'], 400);
}
if ($labelUrl === '' && $labelBase64 === '') {
    print_json_response(['status' => 'error', 'message' => 'label_url or label_base64 required'], 400);
}

$token = trim((string)($data['device_token'] ?? ''));
if ($token === '') {
    $token = print_bearer_token();
}

$auth = auth_device_by_token($deviceUid, $token, true);
if (!$auth['ok']) {
    print_json_response(['status' => 'error', 'message' => $auth['message'] ?? 'auth error'], (int)($auth['http_code'] ?? 403));
}

$queue = print_read_queue($deviceUid);

$jobId = (string)($data['job_id'] ?? '');
if ($jobId === '') {
    $jobId = 'job-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
}

$job = [
    'job_id' => $jobId,
    'status' => 'queued',
    'created_at' => gmdate('c'),
    'file_name' => $fileName,
];

if ($labelUrl !== '') {
    $job['label_url'] = $labelUrl;
}
if ($labelBase64 !== '') {
    $job['label_base64'] = $labelBase64;
}

$queue[] = $job;
if (!print_write_queue($deviceUid, $queue)) {
    print_json_response(['status' => 'error', 'message' => 'failed to save queue'], 500);
}

print_json_response(['status' => 'ok', 'job_id' => $jobId], 200);
