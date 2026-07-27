<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/delivered_car_reports_lib.php';

if (!dcr_tables_ready($dbcnx)) {
    http_response_code(404);
    die('File not found');
}

$fileId = (int)($_GET['file_id'] ?? 0);
$st = $dbcnx->prepare('SELECT p.*, r.is_published FROM zs_delivered_car_report_photos p INNER JOIN zs_delivered_car_reports r ON r.id=p.report_id WHERE p.id=? LIMIT 1');
$st->bind_param('i', $fileId);
$st->execute();
$file = $st->get_result()->fetch_assoc();

if (!$file || ((int)$file['is_published'] !== 1 && !rh_is_admin())) {
    http_response_code(404);
    die('File not found');
}

$base = realpath(dcr_storage_root());
$path = realpath(dirname(__DIR__) . '/storage/' . $file['relative_path'] . '/' . $file['stored_name']);
$basePrefix = $base ? rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
if (!$base || !$path || strpos($path, $basePrefix) !== 0 || !is_file($path)) {
    http_response_code(404);
    die('File not found');
}

header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="report-photo-' . (int)$file['id'] . '.' . pathinfo($file['stored_name'], PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
if ((int)$file['is_published'] === 1) {
    header('Cache-Control: public, max-age=86400');
} else {
    header('Cache-Control: private, no-store');
}
readfile($path);
