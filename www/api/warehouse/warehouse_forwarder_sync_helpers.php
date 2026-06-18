<?php
declare(strict_types=1);

if (!function_exists('warehouse_forwarder_table_exists')) {
    function warehouse_forwarder_table_exists(mysqli $dbcnx, string $table): bool
    {
        $res = $dbcnx->query("SHOW TABLES LIKE '" . $dbcnx->real_escape_string($table) . "'");
        if (!($res instanceof mysqli_result)) return false;
        $ok = $res->num_rows > 0; $res->free(); return $ok;
    }
}
if (!function_exists('warehouse_forwarder_column_exists')) {
    function warehouse_forwarder_column_exists(mysqli $dbcnx, string $table, string $column): bool
    {
        if (!warehouse_forwarder_table_exists($dbcnx, $table)) return false;
        $res = $dbcnx->query("SHOW COLUMNS FROM `" . str_replace('`','``',$table) . "` LIKE '" . $dbcnx->real_escape_string($column) . "'");
        if (!($res instanceof mysqli_result)) return false;
        $ok = $res->num_rows > 0; $res->free(); return $ok;
    }
}

function warehouse_forwarder_ensure_sync_tables(mysqli $dbcnx): void
{
    $dbcnx->query("CREATE TABLE IF NOT EXISTS forwarder_positions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, connector_id INT NOT NULL, position_code VARCHAR(64) NOT NULL,
        position_label VARCHAR(255) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, source_url VARCHAR(512) NULL,
        raw_json LONGTEXT NULL, last_seen_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id),
        UNIQUE KEY uq_connector_position (connector_id, position_code), KEY idx_connector_active (connector_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        'missing_since' => "ALTER TABLE forwarder_positions ADD COLUMN missing_since DATETIME NULL AFTER last_seen_at",
        'sync_source' => "ALTER TABLE forwarder_positions ADD COLUMN sync_source VARCHAR(64) NULL AFTER missing_since",
    ] as $col => $sql) {
        if (!warehouse_forwarder_column_exists($dbcnx, 'forwarder_positions', $col)) {
            $dbcnx->query($sql);
        }
    }
    $dbcnx->query("CREATE TABLE IF NOT EXISTS warehouse_cell_forwarder_map (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, connector_id INT NOT NULL, forwarder_position_code VARCHAR(64) NOT NULL,
        cell_id INT NOT NULL, country_code VARCHAR(16) NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, comment VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_connector_position (connector_id, forwarder_position_code), KEY idx_cell (cell_id),
        KEY idx_connector_cell (connector_id, cell_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $dbcnx->query("CREATE TABLE IF NOT EXISTS forwarder_report_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, connector_id INT NOT NULL, report_uid VARCHAR(128) NULL,
        tracking_no VARCHAR(255) NOT NULL, forwarder_internal_no VARCHAR(255) NULL, client_id VARCHAR(128) NULL,
        client_name VARCHAR(255) NULL, declaration_status VARCHAR(64) NULL, forwarder_position_code VARCHAR(64) NULL,
        weight_kg DECIMAL(10,3) NULL, category VARCHAR(255) NULL, seller VARCHAR(255) NULL, invoice_amount DECIMAL(12,2) NULL,
        invoice_currency VARCHAR(16) NULL, invoice_uploaded VARCHAR(32) NULL, remote_created_at DATETIME NULL, remote_updated_at DATETIME NULL,
        report_date DATE NULL, raw_json LONGTEXT NULL, last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_connector_tracking (connector_id, tracking_no), KEY idx_position (connector_id, forwarder_position_code),
        KEY idx_client (connector_id, client_id), KEY idx_last_seen (last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        'source_origin'=>"ALTER TABLE warehouse_item_stock ADD COLUMN source_origin VARCHAR(64) NULL AFTER addons_json",
        'connector_id'=>"ALTER TABLE warehouse_item_stock ADD COLUMN connector_id INT NULL AFTER source_origin",
        'forwarder_report_item_id'=>"ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_report_item_id BIGINT UNSIGNED NULL AFTER connector_id",
        'forwarder_position_code'=>"ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_position_code VARCHAR(64) NULL AFTER forwarder_report_item_id",
        'forwarder_synced_at'=>"ALTER TABLE warehouse_item_stock ADD COLUMN forwarder_synced_at DATETIME NULL AFTER forwarder_position_code",
    ] as $col=>$sql) if (!warehouse_forwarder_column_exists($dbcnx,'warehouse_item_stock',$col)) $dbcnx->query($sql);
    if (!warehouse_forwarder_table_exists($dbcnx, 'warehouse_sync_audit')) {
        $dbcnx->query("CREATE TABLE IF NOT EXISTS warehouse_sync_audit (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, item_id BIGINT UNSIGNED NULL, tracking_no VARCHAR(255) NULL, forwarder VARCHAR(64) NULL, country_code VARCHAR(16) NULL, status VARCHAR(64) NOT NULL, message TEXT NULL, response_json LONGTEXT NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_status_created (status, created_at), KEY idx_tracking (tracking_no)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}


function warehouse_forwarder_sql_date_or_null($value): ?string
{
    $s = trim((string)$value);
    if ($s === '' || $s === '0000-00-00') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
        return $m[0];
    }

    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

function warehouse_forwarder_sql_datetime_or_null($value): ?string
{
    $s = trim((string)$value);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $s, $m)) {
        return $m[0];
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return $s . ' 00:00:00';
    }

    $ts = strtotime($s);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function warehouse_forwarder_norm_code(string $v): string { return strtoupper(trim($v)); }
function warehouse_forwarder_pick(array $row, array $keys): string { foreach ($keys as $k) if (isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]); return ''; }
function warehouse_forwarder_num($v): ?float { $s=str_replace(',','.',trim((string)$v)); return is_numeric($s)?(float)$s:null; }

function warehouse_forwarder_resolve_local_cell(mysqli $dbcnx, int $connectorId, string $positionCode, string $countryCode = ''): ?int
{
    warehouse_forwarder_ensure_sync_tables($dbcnx); $positionCode = warehouse_forwarder_norm_code($positionCode); $countryCode = strtoupper(trim($countryCode));
    if ($connectorId <= 0 || $positionCode === '') return null;
    $stmt = $dbcnx->prepare("SELECT cell_id FROM warehouse_cell_forwarder_map WHERE connector_id=? AND forwarder_position_code=? AND is_active=1 AND (country_code=? OR country_code IS NULL OR country_code='') ORDER BY CASE WHEN country_code=? THEN 0 ELSE 1 END, id DESC LIMIT 1");
    $stmt->bind_param('isss',$connectorId,$positionCode,$countryCode,$countryCode); $stmt->execute(); $r=$stmt->get_result()->fetch_assoc(); $stmt->close();
    return $r ? (int)$r['cell_id'] : null;
}

function warehouse_forwarder_sync_positions(mysqli $dbcnx, int $connectorId): array
{
    warehouse_forwarder_ensure_sync_tables($dbcnx); $diag=['found_count'=>0,'inserted'=>0,'updated'=>0,'missing_marked'=>0,'source'=>'remote_collector','source_url'=>null,'errors'=>[]];
    $connector = null; $stmt=$dbcnx->prepare('SELECT * FROM connectors WHERE id=? LIMIT 1'); if($stmt){$stmt->bind_param('i',$connectorId);$stmt->execute();$connector=$stmt->get_result()->fetch_assoc();$stmt->close();}
    if (!$connector) { $diag['errors'][] = 'connector_not_found'; return $diag; }
    $baseUrl = rtrim((string)($connector['base_url'] ?? ''), '/');
    $sourceUrl = ($baseUrl !== '' ? $baseUrl : 'https://backend.colibri.az') . '/collector';
    $diag['source_url'] = $sourceUrl;
    $html = '';
    if ($baseUrl !== '' && trim((string)($connector['auth_username'] ?? '')) !== '' && trim((string)($connector['auth_password'] ?? '')) !== '') {
        try {
            require_once dirname(__DIR__, 2) . '/scripts/mvp/app/Forwarder/bootstrap.php';
            $oldEnv = [];
            foreach (['DEV_COLIBRI_BASE_URL','DEV_COLIBRI_LOGIN','DEV_COLIBRI_PASSWORD','FORWARDER_BASE_URL','FORWARDER_LOGIN','FORWARDER_PASSWORD','FORWARDER_SESSION_FILE'] as $key) $oldEnv[$key] = getenv($key);
            putenv('DEV_COLIBRI_BASE_URL=' . $baseUrl); putenv('FORWARDER_BASE_URL=' . $baseUrl);
            putenv('DEV_COLIBRI_LOGIN=' . (string)$connector['auth_username']); putenv('FORWARDER_LOGIN=' . (string)$connector['auth_username']);
            putenv('DEV_COLIBRI_PASSWORD=' . (string)$connector['auth_password']); putenv('FORWARDER_PASSWORD=' . (string)$connector['auth_password']);
            putenv('FORWARDER_SESSION_FILE=' . sys_get_temp_dir() . '/forwarder_positions_' . $connectorId . '.json');
            $config = new \App\Forwarder\Config\ForwarderConfig();
            $logger = new \App\Forwarder\Logging\ForwarderLogger('warehouse-forwarder-positions-' . $connectorId . '-' . date('YmdHis'));
            $httpClient = new \App\Forwarder\Http\ForwarderHttpClient($config);
            $session = new \App\Forwarder\Http\SessionManager();
            $loginService = new \App\Forwarder\Services\LoginService($config, $httpClient, $session, $logger);
            $sessionClient = new \App\Forwarder\Http\ForwarderSessionClient($config, $httpClient, $session, $loginService, $logger);
            $response = $sessionClient->requestWithSession('GET', '/collector', [], false);
            if ((int)($response['status_code'] ?? 0) >= 200 && (int)($response['status_code'] ?? 0) < 400) $html = (string)($response['body'] ?? '');
            else $diag['errors'][] = 'collector_fetch_status_' . (int)($response['status_code'] ?? 0);
        } catch (Throwable $e) { $diag['errors'][] = 'collector_fetch_error: ' . $e->getMessage();
        } finally {
            foreach (($oldEnv ?? []) as $key => $value) { $value === false ? putenv($key) : putenv($key . '=' . $value); }
        }
    } else {
        $diag['errors'][] = 'connector_credentials_missing';
    }
    $codes=[]; if($html!==''){ libxml_use_internal_errors(true); $dom=new DOMDocument(); @$dom->loadHTML($html); $xp=new DOMXPath($dom); foreach($xp->query("//select[@id='position_select']//option") as $opt){$code=warehouse_forwarder_norm_code($opt->getAttribute('value') ?: $opt->textContent); if($code!=='' && $code!=='0') $codes[$code]=trim($opt->textContent);}}
    $isColibri = stripos($baseUrl, 'backend.colibri.az') !== false || stripos((string)($connector['name'] ?? ''), 'COLIBRI') !== false;
    if (!$codes && $isColibri) { for($i=1;$i<=50;$i++) $codes[sprintf('PSB%03d',$i)]=sprintf('PSB%03d',$i); $diag['source']='fallback_psb_range'; $diag['errors'][]='collector_html_unavailable_used_psb_fallback'; }
    $hasMissing = warehouse_forwarder_column_exists($dbcnx,'forwarder_positions','missing_since'); $hasSyncSource = warehouse_forwarder_column_exists($dbcnx,'forwarder_positions','sync_source');
    $seen=[]; foreach($codes as $code=>$label){$seen[]=$code; $raw=json_encode(['code'=>$code,'label'=>$label,'source'=>$diag['source']],JSON_UNESCAPED_UNICODE); $sql="INSERT INTO forwarder_positions (connector_id,position_code,position_label,is_active,source_url,raw_json,last_seen_at".($hasMissing?',missing_since':'').($hasSyncSource?',sync_source':'').") VALUES (?,?,?,?,?,?,NOW()".($hasMissing?',NULL':'').($hasSyncSource?',?':'').") ON DUPLICATE KEY UPDATE position_label=VALUES(position_label), is_active=1, source_url=VALUES(source_url), raw_json=VALUES(raw_json), last_seen_at=NOW(), updated_at=NOW()".($hasMissing?', missing_since=NULL':'').($hasSyncSource?', sync_source=VALUES(sync_source)':''); $stmt=$dbcnx->prepare($sql); $active=1; if($hasSyncSource){$src=(string)$diag['source']; $stmt->bind_param('ississs',$connectorId,$code,$label,$active,$sourceUrl,$raw,$src);} else {$stmt->bind_param('ississ',$connectorId,$code,$label,$active,$sourceUrl,$raw);} $stmt->execute(); $stmt->affected_rows===1?$diag['inserted']++:$diag['updated']++; $stmt->close(); }
    $diag['found_count']=count($seen);
    if($seen && $hasMissing){$in="'".implode("','",array_map([$dbcnx,'real_escape_string'],$seen))."'"; $dbcnx->query("UPDATE forwarder_positions SET missing_since=COALESCE(missing_since,NOW()), updated_at=NOW() WHERE connector_id=".(int)$connectorId." AND position_code NOT IN ($in) AND missing_since IS NULL"); $diag['missing_marked']=$dbcnx->affected_rows;}
    if($seen && warehouse_forwarder_column_exists($dbcnx,'connectors','addons_json')){ $addons=json_decode((string)($connector['addons_json']??''),true); if(!is_array($addons))$addons=[]; $addons['forwarder_positions_cache']=['synced_at'=>date('Y-m-d H:i:s'),'source_url'=>$sourceUrl,'positions'=>$seen]; $json=json_encode($addons,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $st=$dbcnx->prepare('UPDATE connectors SET addons_json=? WHERE id=? LIMIT 1'); if($st){$st->bind_param('si',$json,$connectorId);$st->execute();$st->close();}}
    return $diag;
}

function warehouse_forwarder_import_report_items(mysqli $dbcnx, int $connectorId, array $rows): array
{
    warehouse_forwarder_ensure_sync_tables($dbcnx); $summary=['rows_total'=>count($rows),'report_items_upserted'=>0,'stock_created'=>0,'stock_updated'=>0,'mapped_to_cells'=>0,'unmapped_positions'=>0,'errors'=>[]];
    $conn=['name'=>'','country_code'=>'']; $connectorCountrySelect = warehouse_forwarder_column_exists($dbcnx, 'connectors', 'country_code') ? ', country_code' : ", '' AS country_code"; $st=$dbcnx->prepare('SELECT name' . $connectorCountrySelect . ' FROM connectors WHERE id=? LIMIT 1'); if($st){$st->bind_param('i',$connectorId);$st->execute();$conn=$st->get_result()->fetch_assoc()?:$conn;$st->close();}
    $stockHasUserId = warehouse_forwarder_column_exists($dbcnx, 'warehouse_item_stock', 'user_id');
    $stockHasCommitted = warehouse_forwarder_column_exists($dbcnx, 'warehouse_item_stock', 'committed');
    foreach($rows as $idx=>$row){ try{ if(!is_array($row)) continue; $tracking=warehouse_forwarder_pick($row,['tracking_no','tracking','track','barcode','tuid']); $internal=warehouse_forwarder_pick($row,['forwarder_internal_no','internal_no','order_no']); if($tracking==='') $tracking=$internal; $tracking=trim($tracking); if($tracking===''){ $summary['errors'][]='row '.($idx+1).': empty tracking_no'; continue; }
        $pos=warehouse_forwarder_norm_code(warehouse_forwarder_pick($row,['forwarder_position_code','position','position_code','cell','place'])); $clientId=warehouse_forwarder_pick($row,['client_id','client','customer_id']); $clientName=warehouse_forwarder_pick($row,['client_name','receiver_name','name']); $weight=warehouse_forwarder_num($row['weight_kg']??($row['weight']??null)); $raw=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $stmt=$dbcnx->prepare("INSERT INTO forwarder_report_items (connector_id,report_uid,tracking_no,forwarder_internal_no,client_id,client_name,declaration_status,forwarder_position_code,weight_kg,category,seller,invoice_amount,invoice_currency,invoice_uploaded,remote_created_at,remote_updated_at,report_date,raw_json,last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE report_uid=VALUES(report_uid), forwarder_internal_no=VALUES(forwarder_internal_no), client_id=VALUES(client_id), client_name=VALUES(client_name), declaration_status=VALUES(declaration_status), forwarder_position_code=VALUES(forwarder_position_code), weight_kg=VALUES(weight_kg), category=VALUES(category), seller=VALUES(seller), invoice_amount=VALUES(invoice_amount), invoice_currency=VALUES(invoice_currency), invoice_uploaded=VALUES(invoice_uploaded), remote_created_at=VALUES(remote_created_at), remote_updated_at=VALUES(remote_updated_at), report_date=VALUES(report_date), raw_json=VALUES(raw_json), last_seen_at=NOW()");
        $reportUid=warehouse_forwarder_pick($row,['report_uid','report_id']); $decl=warehouse_forwarder_pick($row,['declaration_status','status']); $cat=warehouse_forwarder_pick($row,['category']); $seller=warehouse_forwarder_pick($row,['seller','shop']); $inv=warehouse_forwarder_num($row['invoice_amount']??null); $cur=warehouse_forwarder_pick($row,['invoice_currency','currency']); $upl=warehouse_forwarder_pick($row,['invoice_uploaded']);
        $remoteCreated=warehouse_forwarder_sql_datetime_or_null($row['remote_created_at']??($row['created_at']??null)); $remoteUpdated=warehouse_forwarder_sql_datetime_or_null($row['remote_updated_at']??($row['updated_at']??null)); $rdate=warehouse_forwarder_sql_date_or_null($row['report_date']??null);
        $stmt->bind_param('isssssssdssdssssss',$connectorId,$reportUid,$tracking,$internal,$clientId,$clientName,$decl,$pos,$weight,$cat,$seller,$inv,$cur,$upl,$remoteCreated,$remoteUpdated,$rdate,$raw); $stmt->execute(); $reportId=$stmt->insert_id ?: (int)($dbcnx->query("SELECT id FROM forwarder_report_items WHERE connector_id=".(int)$connectorId." AND tracking_no='".$dbcnx->real_escape_string($tracking)."' LIMIT 1")->fetch_assoc()['id']??0); $summary['report_items_upserted']++; $stmt->close();
        $resolvedCellId = $pos!=='' ? warehouse_forwarder_resolve_local_cell($dbcnx,$connectorId,$pos,(string)($conn['country_code']??'')) : null; if($resolvedCellId !== null) $summary['mapped_to_cells']++; elseif($pos!=='') $summary['unmapped_positions']++;
        $find=$dbcnx->prepare("SELECT id, addons_json FROM warehouse_item_stock WHERE connector_id=? AND tracking_no=? AND source_origin='forwarder_report' LIMIT 1"); $find->bind_param('is',$connectorId,$tracking); $find->execute(); $existing=$find->get_result()->fetch_assoc(); $find->close();
        if(!$existing){ $find=$dbcnx->prepare("SELECT id, addons_json FROM warehouse_item_stock WHERE tracking_no=? LIMIT 1"); $find->bind_param('s',$tracking); $find->execute(); $existing=$find->get_result()->fetch_assoc(); $find->close(); }
        $existingAddons = $existing ? json_decode((string)($existing['addons_json'] ?? ''), true) : []; if(!is_array($existingAddons)) $existingAddons=[]; $addons=array_merge($existingAddons,['forwarder_report'=>$row,'forwarder_internal_no'=>$internal]); $addonsJson=json_encode($addons,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if($existing){ $sql="UPDATE warehouse_item_stock SET receiver_name=COALESCE(NULLIF(?,''),receiver_name), receiver_address=COALESCE(NULLIF(?,''),receiver_address), weight_kg=COALESCE(?,weight_kg), addons_json=?, source_origin='forwarder_report', connector_id=?, forwarder_report_item_id=?, forwarder_position_code=?, forwarder_synced_at=NOW(), cell_id=? WHERE id=? LIMIT 1"; $stmt=$dbcnx->prepare($sql); $id=(int)$existing['id']; $cid=$resolvedCellId; $stmt->bind_param('ssdsiisii',$clientName,$clientId,$weight,$addonsJson,$connectorId,$reportId,$pos,$cid,$id); $stmt->execute(); $stmt->close(); $summary['stock_updated']++; $stockId=$id; }
        else { $tuid=$tracking; $batchUid=99990000+$connectorId; $uidCreated=9999; $baseCols=['created_at','batch_uid','uid_created','tuid','tracking_no','receiver_country_code','receiver_name','receiver_company','receiver_address','weight_kg','cell_id','addons_json','source_origin','connector_id','forwarder_report_item_id','forwarder_position_code','forwarder_synced_at']; $extraCols=[]; if($stockHasUserId)$extraCols[]='user_id'; if($stockHasCommitted)$extraCols[]='committed'; $cols=array_merge($baseCols,$extraCols); $placeholders=['NOW()','?','?','?','?','?','?','?','?','?','?','?','\'forwarder_report\'','?','?','?','NOW()']; foreach($extraCols as $_)$placeholders[]='?'; $sql="INSERT INTO warehouse_item_stock (`".implode('`,`',$cols)."`) VALUES (".implode(',',$placeholders).")"; $stmt=$dbcnx->prepare($sql); $cid=$resolvedCellId; $country=(string)($conn['country_code']??''); $cname=(string)($conn['name']??''); if($stockHasUserId && $stockHasCommitted){$committed=1; $stmt->bind_param('iisssssdissiisii',$batchUid,$uidCreated,$tuid,$tracking,$country,$clientName,$cname,$clientId,$weight,$cid,$addonsJson,$connectorId,$reportId,$pos,$uidCreated,$committed);} elseif($stockHasUserId){$stmt->bind_param('iisssssdissiisi',$batchUid,$uidCreated,$tuid,$tracking,$country,$clientName,$cname,$clientId,$weight,$cid,$addonsJson,$connectorId,$reportId,$pos,$uidCreated);} elseif($stockHasCommitted){$committed=1; $stmt->bind_param('iisssssdissiisi',$batchUid,$uidCreated,$tuid,$tracking,$country,$clientName,$cname,$clientId,$weight,$cid,$addonsJson,$connectorId,$reportId,$pos,$committed);} else {$stmt->bind_param('iisssssdissiis',$batchUid,$uidCreated,$tuid,$tracking,$country,$clientName,$cname,$clientId,$weight,$cid,$addonsJson,$connectorId,$reportId,$pos);} $stmt->execute(); $stockId=$stmt->insert_id; $stmt->close(); $summary['stock_created']++; }
        $audit=json_encode(['row'=>$row,'stock_item_id'=>$stockId,'mapped_cell_id'=>$resolvedCellId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $stmt=$dbcnx->prepare("INSERT INTO warehouse_sync_audit (item_id,tracking_no,forwarder,country_code,status,message,response_json) VALUES (?,?,?,?, 'imported_from_forwarder','Created from forwarder report',?)"); $f=(string)($conn['name']??''); $country=(string)($conn['country_code']??''); $stmt->bind_param('issss',$stockId,$tracking,$f,$country,$audit); $stmt->execute(); $stmt->close();
    } catch (Throwable $e) { $summary['errors'][]='row '.($idx+1).': '.$e->getMessage(); }} return $summary;
}
