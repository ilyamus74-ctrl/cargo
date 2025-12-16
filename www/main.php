<?php

$smarty->assign('user_settings', $_SESSION['user']);
$smarty->assign('header_data', $header_data);
$smarty->assign('main','main');

// === Dashboard metrics ===
/**
 * Calculate percentage change between two numbers.
 */
function calc_percent_change(int $current, int $previous): string {
    if ($previous === 0) {
        return $current > 0 ? '100%' : '0%';
    }

    $delta = (($current - $previous) / $previous) * 100;
    return sprintf('%.0f%%', $delta);
}

/**
 * Get a single integer result from a COUNT(*) query.
 */
function fetch_scalar_count(mysqli $dbcnx, string $sql, string $types = '', array $params = []): int {
    $stmt = $dbcnx->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    if ($types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_row() : [0];
    $stmt->close();

    return isset($row[0]) ? (int)$row[0] : 0;
}

/**
 * Get activity of users based on processed packages in the current month.
 */
function fetch_monthly_user_activity(mysqli $dbcnx): array {
    $data = [];

    $sql = "
        SELECT
            COALESCE(NULLIF(TRIM(u.full_name), ''), CONCAT('User #', wi.user_id)) AS user_name,
            COUNT(*) AS processed
          FROM warehouse_item_in wi
          LEFT JOIN users u ON u.id = wi.user_id
         WHERE wi.committed = 1
           AND YEAR(wi.created_at) = YEAR(CURDATE())
           AND MONTH(wi.created_at) = MONTH(CURDATE())
         GROUP BY wi.user_id
         ORDER BY processed DESC, user_name ASC
    ";

    if ($res = $dbcnx->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $row['processed'] = (int)$row['processed'];
            $data[] = $row;
        }
        $res->free();
    }

    return $data;
}

// Counts for today/month/year
$packagesToday = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in WHERE committed = 1 AND DATE(created_at) = CURDATE()"
);

$packagesYesterday = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in WHERE committed = 1 AND DATE(created_at) = (CURDATE() - INTERVAL 1 DAY)"
);

$packagesMonth = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in
      WHERE committed = 1
        AND YEAR(created_at) = YEAR(CURDATE())
        AND MONTH(created_at) = MONTH(CURDATE())"
);

$packagesPrevMonth = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in
      WHERE committed = 1
        AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
);

$packagesYear = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in
      WHERE committed = 1
        AND YEAR(created_at) = YEAR(CURDATE())"
);

$packagesPrevYear = fetch_scalar_count(
    $dbcnx,
    "SELECT COUNT(*) FROM warehouse_item_in
      WHERE committed = 1
        AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))"
);

// Week-by-week chart data (Mon-Sun)
function build_week_series(mysqli $dbcnx, string $targetWeekSql): array {
    $data = array_fill(0, 7, 0);

    $stmt = $dbcnx->prepare($targetWeekSql);
    if (!$stmt) {
        return $data;
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // MySQL DAYOFWEEK: 1=Sunday ... 7=Saturday; shift to 0=Mon ... 6=Sun
        $dow     = (int)$row['dow'];
        $mapped  = ($dow + 5) % 7; // Sunday (1) -> 6
        $data[$mapped] = (int)$row['cnt'];
    }
    $stmt->close();

    return $data;
}

$currentWeekPackages = build_week_series(
    $dbcnx,
    "SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
       FROM warehouse_item_in
      WHERE committed = 1
        AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
      GROUP BY dow"
);

$previousWeekPackages = build_week_series(
    $dbcnx,
    "SELECT DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
       FROM warehouse_item_in
      WHERE committed = 1
        AND YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
      GROUP BY dow"
);


$monthlyUserActivity = fetch_monthly_user_activity($dbcnx);

// Assign metrics to template
$smarty->assign('packagesToday',        $packagesToday);
$smarty->assign('packagesTodayChange',  calc_percent_change($packagesToday, $packagesYesterday));
$smarty->assign('packagesMonth',        $packagesMonth);
$smarty->assign('packagesMonthChange',  calc_percent_change($packagesMonth, $packagesPrevMonth));
$smarty->assign('packagesYear',         $packagesYear);
$smarty->assign('packagesYearChange',   calc_percent_change($packagesYear, $packagesPrevYear));

$smarty->assign('currentWeekPackages',  json_encode($currentWeekPackages));
$smarty->assign('previousWeekPackages', json_encode($previousWeekPackages));
$smarty->assign('activeUsersMonthly',   json_encode($monthlyUserActivity));
$smarty->assign('activeUsersCount',     count($monthlyUserActivity));

$smarty->display('cells_NA_index.html');

?>