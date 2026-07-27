<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$connectCandidates = array(__DIR__ . '/../configs/connectDB.php', '/home/zsuauto/web/configs/connectDB.php');
foreach ($connectCandidates as $connectFile) { if (is_file($connectFile)) { require_once $connectFile; break; } }
if (!isset($dbcnx) || !($dbcnx instanceof mysqli)) { http_response_code(500); die('DB connection error'); }

define('RH_IMAGE_MAX_SIZE', 15 * 1024 * 1024);
define('RH_VIDEO_MAX_SIZE', 200 * 1024 * 1024);
define('RH_DOCUMENT_MAX_SIZE', 50 * 1024 * 1024);

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
function rh_normalize_files($field){ $out=array(); if(empty($_FILES[$field])) return $out; $f=$_FILES[$field]; if(is_array($f['name'])){ foreach($f['name'] as $i=>$n){ $out[]=array('name'=>$n,'type'=>$f['type'][$i] ?? '','tmp_name'=>$f['tmp_name'][$i] ?? '','error'=>$f['error'][$i] ?? UPLOAD_ERR_NO_FILE,'size'=>$f['size'][$i] ?? 0); } } else { $out[]=$f; } return $out; }
function rh_non_empty_uploads($field){ return array_values(array_filter(rh_normalize_files($field), function($f){ return (int)$f['error'] !== UPLOAD_ERR_NO_FILE; })); }
function rh_has_unsafe_name($name){ $base=basename((string)$name); if($base !== (string)$name || preg_match('/[\\\/\x00]/', (string)$name)) return true; $parts=array_map('strtolower', explode('.', $base)); $bad=array('php','phtml','phar','html','htm','js','svg','sh','exe','bat','cmd','com'); foreach(array_slice($parts,0,-1) as $p){ if(in_array($p,$bad,true)) return true; } return in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)),$bad,true); }
function rh_zip_has_office_structure($path,$dir){ if(!class_exists('ZipArchive')) return false; $zip=new ZipArchive(); if($zip->open($path)!==true) return false; $hasContent=$zip->locateName('[Content_Types].xml')!==false; $hasDir=false; for($i=0;$i<$zip->numFiles;$i++){ $name=$zip->getNameIndex($i); if(strpos($name,$dir.'/')===0){ $hasDir=true; break; } } $zip->close(); return $hasContent && $hasDir; }
function rh_mime_allowed_for_ext($ext,$mime,$path){
    $map=array(
        'jpg'=>array('image/jpeg'),'jpeg'=>array('image/jpeg'),'png'=>array('image/png'),'webp'=>array('image/webp'),'heic'=>array('image/heic','image/heif'),'heif'=>array('image/heic','image/heif'),
        'mp4'=>array('video/mp4'),'webm'=>array('video/webm'),'mov'=>array('video/quicktime'),'qt'=>array('video/quicktime'),
        'pdf'=>array('application/pdf'),'txt'=>array('text/plain'),'csv'=>array('text/csv','text/plain'),'rtf'=>array('application/rtf','text/rtf','text/plain'),
        'doc'=>array('application/msword'),'xls'=>array('application/vnd.ms-excel'),'ppt'=>array('application/vnd.ms-powerpoint'),
        'odt'=>array('application/vnd.oasis.opendocument.text'),'ods'=>array('application/vnd.oasis.opendocument.spreadsheet'),
        'docx'=>array('application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip'),
        'xlsx'=>array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'),
        'pptx'=>array('application/vnd.openxmlformats-officedocument.presentationml.presentation','application/zip'),
    );
    if(!isset($map[$ext]) || !in_array($mime,$map[$ext],true)) return false;
    if($ext==='pdf' && file_get_contents($path,false,null,0,5)!=='%PDF-') return false;
    if(in_array($ext,array('docx','xlsx','pptx'),true)){ $dir=array('docx'=>'word','xlsx'=>'xl','pptx'=>'ppt')[$ext]; return rh_zip_has_office_structure($path,$dir); }
    return true;
}
function rh_validate_upload($file, $expectedType){
    $ext=strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allowedExt=array('image'=>array('jpg','jpeg','png','webp','heic','heif'),'video'=>array('mp4','webm','mov','qt'),'document'=>array('pdf','txt','csv','rtf','doc','docx','xls','xlsx','ppt','pptx','odt','ods'));
    $max=array('image'=>RH_IMAGE_MAX_SIZE,'video'=>RH_VIDEO_MAX_SIZE,'document'=>RH_DOCUMENT_MAX_SIZE);
    if(empty($allowedExt[$expectedType])) throw new Exception('Невідомий тип файлу');
    if((int)$file['error']!==UPLOAD_ERR_OK) throw new Exception('Помилка завантаження файлу');
    if((int)$file['size']<=0 || (int)$file['size']>$max[$expectedType]) throw new Exception('Файл перевищує допустимий розмір для типу');
    if(rh_has_unsafe_name($file['name'])) throw new Exception('Небезпечне імʼя або розширення файлу заборонене');
    if(!in_array($ext,$allowedExt[$expectedType],true)) throw new Exception('Недопустиме розширення файлу');
    $finfo=new finfo(FILEINFO_MIME_TYPE); $mime=$finfo->file($file['tmp_name']);
    if($mime==='application/octet-stream' && !in_array($ext,array('docx','xlsx','pptx'),true)) throw new Exception('Недопустимий MIME-тип файлу');
    if(in_array($ext,array('docx','xlsx','pptx'),true) && $mime==='application/octet-stream'){ $mime='application/zip'; }
    if(!rh_mime_allowed_for_ext($ext,$mime,$file['tmp_name'])) throw new Exception('MIME-тип не відповідає розширенню файлу');
    return array($mime,$ext);
}

function rh_upload_type_by_ext($file,$fallback){ $ext=strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)); if(in_array($ext,array('mp4','webm','mov','qt'),true)) return 'video'; if(in_array($ext,array('jpg','jpeg','png','webp','heic','heif'),true)) return 'image'; return $fallback; }
function rh_collect_entry_uploads(){ $out=array(); foreach(rh_non_empty_uploads('media_files') as $f){ $out[]=array('type'=>rh_upload_type_by_ext($f,'image'),'file'=>$f); } foreach(rh_non_empty_uploads('camera_photo') as $f){ $out[]=array('type'=>'image','file'=>$f); } foreach(rh_non_empty_uploads('camera_video') as $f){ $out[]=array('type'=>'video','file'=>$f); } foreach(rh_non_empty_uploads('document_files') as $f){ $out[]=array('type'=>'document','file'=>$f); } return $out; }
function rh_public_url($token){ $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $host = $_SERVER['HTTP_HOST'] ?? 'zsuauto.info'; return $scheme . '://' . $host . '/request-history.php?token=' . rawurlencode($token); }
function rh_format_size($bytes){ $bytes=(int)$bytes; if($bytes>=1073741824) return round($bytes/1073741824,2).' GB'; if($bytes>=1048576) return round($bytes/1048576,2).' MB'; if($bytes>=1024) return round($bytes/1024,2).' KB'; return $bytes.' B'; }
function rh_post_too_large(){ $len=(int)($_SERVER['CONTENT_LENGTH'] ?? 0); $postMax=ini_get('post_max_size'); $unit=strtolower(substr($postMax,-1)); $num=(float)$postMax; if($unit==='g') $num*=1024*1024*1024; elseif($unit==='m') $num*=1024*1024; elseif($unit==='k') $num*=1024; return $len>0 && $num>0 && $len>$num; }
function rh_file_url($fileId,$token=''){ return 'request_history_file.php?file_id='.(int)$fileId.($token!=='' ? '&token='.rawurlencode($token) : ''); }
function rh_doc_icon($f){ $ext=strtolower(pathinfo($f['original_name'],PATHINFO_EXTENSION)); return $ext==='pdf' ? '📄 PDF' : '📎 '.strtoupper($ext); }