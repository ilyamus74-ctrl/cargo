<?php
require_once __DIR__ . '/request_history_lib.php';

if (!defined('DCR_IMAGE_MAX_SIZE')) {
    define('DCR_IMAGE_MAX_SIZE', 15 * 1024 * 1024);
}

function dcr_tables_ready($db)
{
    $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    foreach (array('zs_delivered_car_reports', 'zs_delivered_car_report_photos') as $table) {
        $st->bind_param('s', $table);
        $st->execute();
        if (!$st->get_result()->fetch_row()) {
            return false;
        }
    }
    return true;
}

function dcr_storage_root()
{
    return dirname(__DIR__) . '/storage/delivered_car_reports';
}

function dcr_storage_relative($reportId)
{
    return 'delivered_car_reports/' . (int)$reportId;
}

function dcr_report_by_request($db, $requestId)
{
    $st = $db->prepare('SELECT * FROM zs_delivered_car_reports WHERE request_id=? LIMIT 1');
    $st->bind_param('i', $requestId);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

function dcr_report($db, $reportId)
{
    $st = $db->prepare('SELECT * FROM zs_delivered_car_reports WHERE id=? LIMIT 1');
    $st->bind_param('i', $reportId);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

function dcr_get_or_create_report($db, $request, $adminId)
{
    $requestId = (int)$request['id'];
    $report = dcr_report_by_request($db, $requestId);
    if ($report) {
        return $report;
    }

    $title = trim((string)$request['lotImgDir']);
    if ($title !== '') {
        $title = 'Передано авто за заявкою ' . $title;
    } else {
        $title = 'Передано авто для ЗСУ';
    }

    $st = $db->prepare('INSERT INTO zs_delivered_car_reports (request_id, title, report_text, delivered_at, is_published, created_by, created_at, updated_at, published_at) VALUES (?, ?, \'\', NULL, 0, ?, NOW(), NOW(), NULL)');
    $st->bind_param('isi', $requestId, $title, $adminId);
    $st->execute();

    return dcr_report($db, (int)$db->insert_id);
}

function dcr_photos($db, $reportId)
{
    $st = $db->prepare('SELECT * FROM zs_delivered_car_report_photos WHERE report_id=? ORDER BY sort_order ASC, id ASC');
    $st->bind_param('i', $reportId);
    $st->execute();
    $result = $st->get_result();
    $photos = array();
    while ($row = $result->fetch_assoc()) {
        $photos[] = $row;
    }
    return $photos;
}

function dcr_public_reports($db)
{
    $sql = "SELECT r.*,
        (SELECT p.id FROM zs_delivered_car_report_photos p WHERE p.report_id=r.id ORDER BY p.sort_order ASC, p.id ASC LIMIT 1) AS cover_photo_id,
        (SELECT COUNT(*) FROM zs_delivered_car_report_photos p2 WHERE p2.report_id=r.id) AS photo_count
        FROM zs_delivered_car_reports r
        WHERE r.is_published=1
        ORDER BY COALESCE(r.delivered_at, DATE(r.published_at), DATE(r.updated_at)) DESC, r.id DESC";
    $result = $db->query($sql);
    $reports = array();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    return $reports;
}

function dcr_public_report($db, $reportId, $allowPreview)
{
    if ($allowPreview) {
        return dcr_report($db, $reportId);
    }
    $st = $db->prepare('SELECT * FROM zs_delivered_car_reports WHERE id=? AND is_published=1 LIMIT 1');
    $st->bind_param('i', $reportId);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

function dcr_validate_delivered_at($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new Exception('Некоректна дата передачі');
    }
    return $value;
}

function dcr_validate_image_upload($file)
{
    list($mime, $ext) = rh_validate_upload($file, 'image');
    if (!in_array($ext, array('jpg', 'jpeg', 'png', 'webp'), true)) {
        throw new Exception('Для звіту дозволені фото JPG, PNG або WEBP');
    }
    if ((int)$file['size'] > DCR_IMAGE_MAX_SIZE) {
        throw new Exception('Фото перевищує допустимий розмір');
    }
    return array($mime, $ext);
}

function dcr_photo_url($photoId)
{
    return '/delivered_car_report_file.php?file_id=' . (int)$photoId;
}

function dcr_public_url($reportId)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'zsuauto.info';
    return $scheme . '://' . $host . '/peredani-avto?report=' . (int)$reportId;
}
