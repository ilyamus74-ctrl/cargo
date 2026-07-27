<?php
require_once __DIR__ . '/delivered_car_reports_lib.php';

$reportsReady = dcr_tables_ready($dbcnx);
$reportId = (int)($_GET['report'] ?? 0);
$deliveredReport = null;
$deliveredReports = array();

if ($reportsReady && $reportId > 0) {
    $deliveredReport = dcr_public_report($dbcnx, $reportId, rh_is_admin());
    if (!$deliveredReport) {
        http_response_code(404);
        $data['main_text'] = 'Вибачте, такого звіту не існує.';
        $data['main_text_h1'] = 'Звіт не знайдено';
        $data['description'] = 'Звіт про передане авто не знайдено.';
        $data['title'] = 'Звіт не знайдено';
        $data['keywords'] = 'звіт не знайдено';
        $smarty->assign('data', $data);
        $smarty->assign('reqUrl', 'https://' . $_SERVER['SERVER_NAME'] . '/peredani-avto');
        $smarty->assign('pageView', '404');
        $smarty->display('index.html');
        return;
    }

    $deliveredReport['photos'] = dcr_photos($dbcnx, (int)$deliveredReport['id']);
    foreach ($deliveredReport['photos'] as &$photo) {
        $photo['url'] = dcr_photo_url((int)$photo['id']);
    }
    unset($photo);
    $deliveredReport['report_text_html'] = nl2br(rh_h($deliveredReport['report_text']));
    $deliveredReport['delivered_at_view'] = $deliveredReport['delivered_at'] ? date('d.m.Y', strtotime($deliveredReport['delivered_at'])) : '';
} elseif ($reportsReady) {
    $deliveredReports = dcr_public_reports($dbcnx);
    foreach ($deliveredReports as &$report) {
        $plainText = trim((string)$report['report_text']);
        $report['summary'] = mb_strlen($plainText) > 240 ? mb_substr($plainText, 0, 240) . '…' : $plainText;
        $report['cover_url'] = !empty($report['cover_photo_id']) ? dcr_photo_url((int)$report['cover_photo_id']) : '';
        $report['delivered_at_view'] = $report['delivered_at'] ? date('d.m.Y', strtotime($report['delivered_at'])) : '';
    }
    unset($report);
}

$smarty->assign('DELIVERED_REPORTS_READY', $reportsReady);
$smarty->assign('deliveredReport', $deliveredReport);
$smarty->assign('deliveredReports', $deliveredReports);

$data['main_text'] = 'Короткі звіти про автомобілі, які були знайдені, підготовлені та передані військовим підрозділам. Ми не заробляємо на цьому — це волонтерська допомога.';
$data['main_text_h1'] = $deliveredReport ? $deliveredReport['title'] : 'Передані авто для ЗСУ';
$data['description'] = 'Звіти про автомобілі, передані військовим підрозділам України волонтерами.';
$data['title'] = $deliveredReport ? $deliveredReport['title'] : 'Передані авто для ЗСУ — звіти волонтерів';
$data['keywords'] = 'передані авто для ЗСУ, звіти волонтерів, автомобілі військовим';

$smarty->assign('data', $data);
$smarty->assign('reqUrl', $deliveredReport ? dcr_public_url((int)$deliveredReport['id']) : ('https://' . $_SERVER['SERVER_NAME'] . '/peredani-avto'));
$smarty->assign('pageView', 'deliveredCars');
$smarty->display('index.html');
