<?php


// ===== БАЗА =====
$cfg = [
  'host' => 'localhost',
  'db'   => 'GPTFOREX',
  'user' => 'GPTFOREX',
  'pass' => 'GPtushechkaForexUshechka',
  'charset' => 'utf8mb4',
];

// ===== Подключение =====
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    $db->set_charset($cfg['charset']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>'db_connect','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== Вспомогалки =====
function j($x){ return json_encode($x, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
//function ok($x){ header('Content-Type: application/json; charset=UTF-8'); echo j($x); exit; }
function ok($x){ 

    header('Content-Type: application/json; charset=UTF-8');
    print_r(j($x));
    exit;
}
function bad($msg,$code=400){ http_response_code($code); ok(['error'=>$msg]); }

function table_columns(mysqli $db, string $table): array {
    $cols = [];
    $res = $db->query("SHOW COLUMNS FROM `{$db->real_escape_string($table)}`");
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

// ===== Роут =====
$action = $_GET['action'] ?? 'signals';

// ===== Карта моделей в таблицы =====
$TABLES = [
  'ET1' => 'predictETH1',
  'ET2' => 'predictETH2',
  'ET3' => 'predictETH3',
  'ET4' => 'predictETH4',
  'ET5' => 'predictETH5',
];


/**
 * 1.1) Детали сигнала по id+model
 * GET /apiG.php?action=signal&id=123&model=ET3
 */
if ($action === 'signal') {

    $id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $model = isset($_GET['model']) ? strtoupper(trim($_GET['model'])) : '';

    if ($id <= 0) bad('id required');
    if (!isset($TABLES[$model])) bad('model required: ET1|ET2|ET3|ET4|ET5');

    $table = $TABLES[$model];
    $sql = "SELECT id, ts_utc, price_entry, price_exit, currency_pair, event, direction_pred, direction_prob, magnitude_pred, priority, updated_at
            FROM `$table`
            WHERE id = ?";
    //echo $sql;
    $st = $db->prepare($sql);
    if (!$st) bad('prepare failed');
    $st->bind_param('i', $id);
    $st->execute();
    $res = $st->get_result();
    $row = $res->fetch_assoc();
    $st->close();


    if (!$row) bad('signal not found', 404);


    ok(['model'=>$model, 'item'=>$row]);
}
/**
 * 1.2) Тики вокруг ts_utc сигнала: [-window_min; +window_min]
 * GET /apiG.php?action=prices_for_signal&id=123&model=ET3&window_min=30
 * Таблица с тиками (1m OHLC): HistDataEURUSD (timestamp_utc, open, high, low, close)
 */ 
 //prices_for_signal
if ($action === 'prices_for_signal') {
    $id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $model = isset($_GET['model']) ? strtoupper(trim($_GET['model'])) : '';
    $win   = isset($_GET['window_min']) ? max(1, min(120, (int)$_GET['window_min'])) : 30; // 1..120

    if ($id <= 0) bad('id required');
    if (!isset($TABLES[$model])) bad('model required: ET1|ET2|ET3|ET4|ET5');

    $table = $TABLES[$model];

    // 2.1 достаем сам сигнал
    $st = $db->prepare("SELECT ts_utc, price_entry, price_exit, currency_pair FROM `$table` WHERE id=?");
    $st->bind_param('i', $id);
    $st->execute();
    $sig = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$sig) bad('signal not found', 404);

    $ts = $sig['ts_utc']; // 'YYYY-MM-DD HH:MM:SS'
    // 2.2 окно вокруг сигнала
    $sqlTicks = "
      SELECT timestamp_utc, open, high, low, close
      FROM HistDataEURUSD
      WHERE timestamp_utc BETWEEN (TIMESTAMP(?) - INTERVAL ? MINUTE)
                              AND (TIMESTAMP(?) + INTERVAL ? MINUTE)
      ORDER BY timestamp_utc ASC";
    $st2 = $db->prepare($sqlTicks);
    $st2->bind_param('sisi', $ts, $win, $ts, $win);
    $st2->execute();
    $res = $st2->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $st2->close();

    ok([
      'model' => $model,
      'signal'=> $sig,
      'window_min' => $win,
      'count' => count($rows),
      'items' => $rows
    ]);
}

/**
 *1.3) Последние N минут тиков (для лайва раз в минуту)
 * GET /apiG.php?action=price_recent&pair=EUR/USD&limit=60
 * Пока у тебя одна таблица HistDataEURUSD — игнорим pair, но не ломаем контракт.
 */
if ($action === 'price_recent') {
    $pair  = $_GET['pair'] ?? 'EUR/USD';
    $limit = isset($_GET['limit']) ? max(1, min(2000, (int)$_GET['limit'])) : 60;

    // Берем последние N минут по времени UTC
    $sql = "
      SELECT timestamp_utc, open, high, low, close
      FROM HistDataEURUSD
      WHERE timestamp_utc >= UTC_TIMESTAMP() - INTERVAL ? MINUTE
      ORDER BY timestamp_utc ASC";
    $st = $db->prepare($sql);
    $st->bind_param('i', $limit);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $st->close();

    ok(['pair'=>$pair, 'count'=>count($rows), 'items'=>$rows]);
}

// ====== 1) Получить сигналы ======
// GET /api.php?action=signals&model=ET3&from=2025-10-20&to=2025-10-23&dir=up|down|all&limit=200
if ($action === 'signals') {
    $model = isset($_GET['model']) ? strtoupper(trim($_GET['model'])) : 'ET2';
    if (!isset($TABLES[$model])) bad('unknown_model');

    $table = $TABLES[$model];

    $from  = $_GET['from'] ?? null;  // YYYY-MM-DD
    $to    = $_GET['to']   ?? null;  // YYYY-MM-DD
    $dir   = $_GET['dir']  ?? 'all'; // up|down|flat|all
    $limit = max(1, min((int)($_GET['limit'] ?? 200), 1000));

    // выясняем какие колонки реально есть
    $cols = table_columns($db, $table);

    // базовые (с алиасами до единой схемы)
    $c_ts    = in_array('ts_utc', $cols)         ? 'ts_utc' : (in_array('timestamp_utc',$cols) ? 'timestamp_utc' : 'NULL');
    $c_event = in_array('event', $cols)          ? 'event'  : (in_array('headline',$cols) ? 'headline' : 'NULL');
    $c_evkey = in_array('event_key', $cols)      ? 'event_key' : 'NULL';
    $c_src   = in_array('src_id', $cols)         ? 'src_id' : 'NULL';

    // направление: новая 0/1/2 или старая -1/0/1
    if (in_array('direction_pred', $cols)) {
        $expr_dir = 'direction_pred';
    } elseif (in_array('dir', $cols)) {
        // -1 => 0 (Down), 0 => 1 (Flat), 1 => 2 (Up)
        $expr_dir = 'CASE WHEN dir=1 THEN 2 WHEN dir=-1 THEN 0 ELSE 1 END';
    } else {
        $expr_dir = 'NULL';
    }

    $c_prob  = in_array('direction_prob',$cols)  ? 'direction_prob' : (in_array('prob',$cols) ? 'prob' : 'NULL');
    $c_mag   = in_array('magnitude_pred',$cols)  ? 'magnitude_pred' : (in_array('mag_pips',$cols) ? 'mag_pips' : 'NULL');
    $c_entry = in_array('price_entry',$cols)     ? 'price_entry'    : (in_array('entry',$cols) ? '`entry`' : 'NULL');
    // exit — зарезервированное; берём как `exit` и алисим в price_exit
    $c_exit  = in_array('price_exit',$cols)      ? 'price_exit'     : (in_array('exit',$cols) ? '`exit`' : 'NULL');

    $c_pair  = in_array('currency_pair',$cols)   ? 'currency_pair'  : (in_array('pair',$cols) ? 'pair' : "'EUR/USD'");
    $c_prio  = in_array('priority',$cols)        ? 'priority'       : "'Low'";
    $c_rm    = in_array('is_removed',$cols)      ? 'is_removed'     : '0';
    $c_mtag  = in_array('model_tag',$cols)       ? 'model_tag'      : ("'". $db->real_escape_string($model) ."'");
    $c_upd   = in_array('updated_at',$cols)      ? 'updated_at'     : (in_array('modified_at',$cols) ? 'modified_at' : 'NULL');

    // WHERE
    $where = [];
    $types = '';
    $vals  = [];

    if ($from) { $where[] = "$c_ts >= ?"; $types.='s'; $vals[] = $from.' 00:00:00'; }
    if ($to)   { $where[] = "$c_ts <= ?"; $types.='s'; $vals[] = $to.' 23:59:59'; }

    // фильтр по направлению (в унифицированной шкале 0/1/2)
    $dirMap = ['down'=>0,'flat'=>1,'up'=>2];
    if ($dir !== 'all' && $expr_dir !== 'NULL' && isset($dirMap[$dir])) {
        $where[] = "($expr_dir) = ?";
        $types  .= 'i';
        $vals[]  = $dirMap[$dir];
    }

    // SELECT в единой схеме для фронта
    $sql = "SELECT
                id,
                $c_ts    AS ts_utc,
                $c_event AS event,
                $c_evkey AS event_key,
                $c_src   AS src_id,
                $expr_dir AS direction_pred,
                $c_prob  AS direction_prob,
                $c_mag   AS magnitude_pred,
                $c_entry AS price_entry,
                $c_exit  AS price_exit,
                $c_pair  AS currency_pair,
                $c_prio  AS priority,
                $c_rm    AS is_removed,
                $c_mtag  AS model_tag,
                $c_upd   AS updated_at
            FROM `{$table}`".
            (count($where) ? " WHERE ".implode(" AND ", $where) : "").
            " ORDER BY $c_ts DESC
              LIMIT ?";

    $stmt = $db->prepare($sql);
    if (!$stmt) bad('prepare_failed');

    $typesLimit = $types.'i';
    $bindVals = $vals; $bindVals[] = $limit;
    $stmt->bind_param($typesLimit, ...$bindVals);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // приведение типов и дефолты
        $r['direction_pred'] = isset($r['direction_pred']) ? (int)$r['direction_pred'] : 1;
        $r['direction_prob'] = isset($r['direction_prob']) ? (float)$r['direction_prob'] : 0.0;
        $r['magnitude_pred'] = isset($r['magnitude_pred']) ? (float)$r['magnitude_pred'] : 0.0;
        $r['price_entry']    = isset($r['price_entry']) ? (float)$r['price_entry'] : 0.0;
        $r['price_exit']     = isset($r['price_exit'])  ? (float)$r['price_exit']  : 0.0;
        $r['is_removed']     = isset($r['is_removed'])  ? (int)$r['is_removed']    : 0;
        $r['priority']       = $r['priority'] ?? 'Low';
        $r['model_tag']      = $r['model_tag'] ?? $model;
        $rows[] = $r;
    }
    $stmt->close();

    ok(['model'=>$model, 'count'=>count($rows), 'items'=>$rows]);
}

// ====== 2) Минутные цены вокруг сигнала ======
// Требует таблицу цен HistDataEURUSD(timestamp_utc, close)
// GET /api.php?action=prices&id=...&model=ET3&window=30&gran=tick
$gran = $_GET['gran'] ?? '1min';

if ($gran === 'tick') {
    $stmt = $db->prepare("
        SELECT timestamp_utc AS ts, close
        FROM HistDataEURUSD
        WHERE timestamp_utc BETWEEN DATE_SUB(?, INTERVAL ? MINUTE) AND DATE_ADD(?, INTERVAL ? MINUTE)
        ORDER BY timestamp_utc
        LIMIT 20000
    ");
    $stmt->bind_param('sisi', $ts, $window, $ts, $window);
    $stmt->execute();
    $res = $stmt->get_result();
    $series = [];
    while ($r = $res->fetch_assoc()) {
        $series[] = ['t'=>$r['ts'], 'close'=>(float)$r['close']];
    }

    //$hash = hash('sha256', implode('#', array_map(fn($r)=>$r['id'].'|'.$r['updated_at'], $items)));
    //echo json_encode(['items'=>$items,'hash'=>$hash]);

    ok([
      'id'=>$id, 'window'=>$window,
      'entry'=>(float)$sig['price_entry'],
      'exit'=>(float)$sig['price_exit'],
      'series'=>$series, 'granularity'=>'tick'
    ]);
}
// ====== 2.5) Минутные цены в реал таймк ======
// Требует таблицу цен HistDataEURUSD(timestamp_utc, close)
// GET /api.php?action=prices&id=...&model=ET3&window=30&gran=tick

/*
if(!empty($_GET['action']) && !empty($_GET['pair']) && !empty($_GET['timeframe']) && !empty($_GET['limit'])){
   $action = $_GET['price'];
   $pair = $_GET['pair'];
   $timeframe = $_GET['timeframe'];
   $limit = $_GET['limit'];

   $stmtSQL ="SELECT * FROM HistDataEURUSD WHERE timestamp_utc >= UTC_TIMESTAMP() - INTERVAL 60 MINUTE AND timestamp_utc < UTC_TIMESTAMP()";
   $typesLimit = $types.'i';
   $stmt = $db->prepare($stmtSQL);
   $stmt->execute();
   $res = $stmt->get_result();

   $rows = [];
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
   ok(['model'=>$model, 'count'=>count($rows), 'items'=>$rows]);

}*/

if ($action === $_GET['price']) {
    $pair  = $_GET['pair'] ?? 'EUR/USD';
    $limit = isset($_GET['limit']) ? max(1, min(2000, (int)$_GET['limit'])) : 60;

    $sql = "SELECT timestamp_utc, open, high, low, close
            FROM HistDataEURUSD
            WHERE timestamp_utc >= UTC_TIMESTAMP() - INTERVAL ? MINUTE
            ORDER BY timestamp_utc ASC";
    $st = $db->prepare($sql);
    $st->bind_param('i', $limit);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    ok(['pair'=>$pair, 'count'=>count($rows), 'items'=>$rows]);
}


/**
 * X) Полный пакет для графика по одному сигналу
 * GET /apiG.php?action=signal_full&id=123&model=ET3&window_min=30
 *
 * Возвращает:
 * - live: текущая строка из predictETHn
 * - candles: минутки вокруг ts_utc (−win ; +win)
 * - meta для графика (entry/exit/timestamp)
 */
if ($action === 'signal_full') {
    $id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $model = isset($_GET['model']) ? strtoupper(trim($_GET['model'])) : '';
    $win   = isset($_GET['window_min']) ? max(1, min(120, (int)$_GET['window_min'])) : 30;

    if ($id <= 0) bad('id required');
    if (!isset($TABLES[$model])) bad('model required: ET1|ET2|ET3|ET4|ET5');

    $table = $TABLES[$model];

    // достать сам сигнал
    $sqlSig = "SELECT id, ts_utc, price_entry, price_exit, currency_pair, event,
                      direction_pred, direction_prob, magnitude_pred, priority, updated_at
               FROM `$table`
               WHERE id = ?";
    $st = $db->prepare($sqlSig);
    $st->bind_param('i', $id);
    $st->execute();
    $resSig = $st->get_result();
    $sig = $resSig->fetch_assoc();
    $st->close();

    if (!$sig) bad('signal not found', 404);

    // дёрнем цены вокруг ts_utc
    $ts = $sig['ts_utc']; // 'YYYY-MM-DD HH:MM:SS'
    $sqlTicks = "
      SELECT timestamp_utc, open, high, low, close
      FROM HistDataEURUSD
      WHERE timestamp_utc BETWEEN (TIMESTAMP(?) - INTERVAL ? MINUTE)
                              AND (TIMESTAMP(?) + INTERVAL ? MINUTE)
      ORDER BY timestamp_utc ASC";
    $st2 = $db->prepare($sqlTicks);
    $st2->bind_param('sisi', $ts, $win, $ts, $win);
    $st2->execute();
    $resTicks = $st2->get_result();

    $rows = [];
    while ($r = $resTicks->fetch_assoc()) {
        $rows[] = $r;
    }
    $st2->close();

    ok([
      'ok'         => true,
      'model'      => $model,
      'window_min' => $win,
      'live'       => $sig,
      'candles'    => $rows,
      'graph_meta' => [
        'ts_utc'       => $sig['ts_utc'],
        'price_entry'  => $sig['price_entry'],
        'price_exit'   => $sig['price_exit']
      ]
    ]);
}

// ====== 3) Отзыв по сигналу ======
// POST /api.php?action=feedback  JSON: {signal_id, model, rating, comment}
// Таблица feedbacks: id, model_tag, signal_id, rating TINYINT, comment TEXT, created_at
if ($action === 'feedback') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') bad('method_not_allowed', 405);

    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (!is_array($j)) bad('bad_json');

    $signalId = (int)($j['signal_id'] ?? 0);
    $model    = strtoupper(trim($j['model'] ?? 'ET3'));
    $rating   = (int)($j['rating'] ?? 0);
    $comment  = trim((string)($j['comment'] ?? ''));

    if (!$signalId || !isset($TABLES[$model])) bad('bad_params');

    $stmt = $db->prepare("INSERT INTO feedbacks(model_tag, signal_id, rating, comment, created_at)
                          VALUES(?,?,?,?, NOW())");
    $stmt->bind_param('siis', $model, $signalId, $rating, $comment);
    $stmt->execute();
    ok(['status'=>'ok']);
}

bad('unknown_action', 404);

