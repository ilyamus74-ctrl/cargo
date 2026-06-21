<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$connectCandidates = array(__DIR__ . '/../configs/connectDB.php', '/home/zsuauto/web/configs/connectDB.php');
foreach ($connectCandidates as $connectFile) { if (is_file($connectFile)) { require_once $connectFile; break; } }
if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) { http_response_code(500); die('DB connection error'); }

define('RH_IMAGE_MAX_SIZE', 15 * 1024 * 1024);
define('RH_VIDEO_MAX_SIZE', 200 * 1024 * 1024);

function rh_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function rh_is_admin(){ return !empty($_SESSION['admin_user']['id']); }
function rh_admin_id(){ return rh_is_admin() ? (int)$_SESSION['admin_user']['id'] : 0; }
function rh_csrf(){ if (empty($_SESSION['request_history_csrf'])) { $_SESSION['request_history_csrf'] = bin2hex(random_bytes(32)); } return $_SESSION['request_history_csrf']; }
function rh_check_csrf(){ if ($_SERVER['REQUEST_METHOD'] === 'POST' && (empty($_POST['csrf']) || !hash_equals(rh_csrf(), $_POST['csrf']))) { http_response_code(403); die('CSRF error'); } }
function rh_storage_root(){ return dirname(__DIR__) . '/storage/request_history'; }
function rh_storage_relative($requestId){ return 'request_history/' . (int)$requestId . '/' . date('Y') . '/' . date('m'); }
function rh_new_token(){ return bin2hex(random_bytes(32)); }
function rh_request($db, $requestId){ $st=$db->prepare('SELECT id, lotImgDir, name, phone, date, remark FROM zs_requests WHERE id=? LIMIT 1'); $st->bind_param('i',$requestId); $st->execute(); return $st->get_result()->fetch_assoc(); }
function rh_case($db, $caseId){ $st=$db->prepare('SELECT * FROM zs_request_cases WHERE id=? AND is_active=1 LIMIT 1'); $st->bind_param('i',$caseId); $st->execute(); return $st->get_result()->fetch_assoc(); }
function rh_case_by_request($db, $requestId){ $st=$db->prepare('SELECT * FROM zs_request_cases WHERE request_id=? AND is_active=1 LIMIT 1'); $st->bind_param('i',$requestId); $st->execute(); return $st->get_result()->fetch_assoc(); }
function rh_case_by_token($db, $token){ if(!preg_match('/^[a-f0-9]{64}$/', (string)$token)) return null; $st=$db->prepare('SELECT * FROM zs_request_cases WHERE public_token=? AND is_active=1 LIMIT 1'); $st->bind_param('s',$token); $st->execute(); return $st->get_result()->fetch_assoc(); }
function rh_get_or_create_case($db, $requestId, $adminId){
    $st=$db->prepare('SELECT * FROM zs_request_cases WHERE request_id=? LIMIT 1'); $st->bind_param('i',$requestId); $st->execute(); $row=$st->get_result()->fetch_assoc(); if($row){ return $row; }
    do { $token=rh_new_token(); $st=$db->prepare('SELECT id FROM zs_request_cases WHERE public_token=? LIMIT 1'); $st->bind_param('s',$token); $st->execute(); $exists=$st->get_result()->fetch_assoc(); } while($exists);
    $st=$db->prepare('INSERT INTO zs_request_cases (request_id, public_token, created_by, created_at, updated_at, is_active) VALUES (?, ?, ?, NOW(), NOW(), 1)'); $st->bind_param('isi',$requestId,$token,$adminId); $st->execute();
    $id=$db->insert_id; $st=$db->prepare('SELECT * FROM zs_request_cases WHERE id=? LIMIT 1'); $st->bind_param('i',$id); $st->execute(); return $st->get_result()->fetch_assoc();
}
function rh_regenerate_token($db, $caseId){ do { $token=rh_new_token(); $st=$db->prepare('SELECT id FROM zs_request_cases WHERE public_token=? AND id<>? LIMIT 1'); $st->bind_param('si',$token,$caseId); $st->execute(); $exists=$st->get_result()->fetch_assoc(); } while($exists); $st=$db->prepare('UPDATE zs_request_cases SET public_token=?, updated_at=NOW() WHERE id=?'); $st->bind_param('si',$token,$caseId); $st->execute(); return $token; }
function rh_entries($db, $caseId){
    $st=$db->prepare('SELECT e.*, a.name AS admin_name FROM zs_request_entries e LEFT JOIN zs_adminusers a ON a.id=e.author_admin_id WHERE e.case_id=? ORDER BY e.created_at DESC, e.id DESC'); $st->bind_param('i',$caseId); $st->execute(); $res=$st->get_result(); $entries=array();
    while($r=$res->fetch_assoc()){ $r['files']=array(); $entries[(int)$r['id']]=$r; }
    if($entries){ $ids=array_keys($entries); $in=implode(',', array_fill(0,count($ids),'?')); $types=str_repeat('i',count($ids)); $st=$db->prepare('SELECT * FROM zs_request_files WHERE entry_id IN ('.$in.') ORDER BY id ASC'); $st->bind_param($types, ...$ids); $st->execute(); $fr=$st->get_result(); while($f=$fr->fetch_assoc()){ $entries[(int)$f['entry_id']]['files'][]=$f; } }
    return $entries;
}
function rh_normalize_files($field){ $out=array(); if(empty($_FILES[$field])) return $out; $f=$_FILES[$field]; if(is_array($f['name'])){ foreach($f['name'] as $i=>$n){ $out[]=array('name'=>$n,'type'=>$f['type'][$i],'tmp_name'=>$f['tmp_name'][$i],'error'=>$f['error'][$i],'size'=>$f['size'][$i]); } } else { $out[]=$f; } return $out; }
function rh_has_double_extension($name){ $base=basename((string)$name); return substr_count($base, '.') > 1; }
function rh_validate_upload($file, $expectedType){
    $allowed = array(
        'image' => array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'),
        'video' => array('video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'),
    );
    $max = $expectedType === 'video' ? RH_VIDEO_MAX_SIZE : RH_IMAGE_MAX_SIZE;
    if(empty($allowed[$expectedType])) throw new Exception('Невідомий тип файлу');
    if($file['error']!==UPLOAD_ERR_OK) throw new Exception('Помилка завантаження файлу');
    if($file['size']<=0 || $file['size']>$max) throw new Exception('Файл перевищує допустимий розмір');
    if(rh_has_double_extension($file['name'])) throw new Exception('Подвійне розширення файлу заборонене');
    $ext=strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExt = $expectedType === 'video' ? array('mp4','webm','mov','qt') : array('jpg','jpeg','png','webp');
    if(!in_array($ext,$allowedExt,true)) throw new Exception('Недопустиме розширення файлу');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
    if(empty($allowed[$expectedType][$mime])) throw new Exception('Недопустимий MIME-тип файлу');
    return array($mime,$allowed[$expectedType][$mime]);
}
function rh_public_url($token){ $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $host = $_SERVER['HTTP_HOST'] ?? 'zsuauto.info'; return $scheme . '://' . $host . '/request-history.php?token=' . rawurlencode($token); }
function rh_format_size($bytes){ $bytes=(int)$bytes; if($bytes>=1073741824) return round($bytes/1073741824,2).' GB'; if($bytes>=1048576) return round($bytes/1048576,2).' MB'; if($bytes>=1024) return round($bytes/1024,2).' KB'; return $bytes.' B'; }
function rh_post_too_large(){ $len=(int)($_SERVER['CONTENT_LENGTH'] ?? 0); $postMax=ini_get('post_max_size'); $unit=strtolower(substr($postMax,-1)); $num=(float)$postMax; if($unit==='g') $num*=1024*1024*1024; elseif($unit==='m') $num*=1024*1024; elseif($unit==='k') $num*=1024; return $len>0 && $num>0 && $len>$num; }