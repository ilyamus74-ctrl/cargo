<?php
session_start();
require_once __DIR__ . '/request_history_lib.php';
$fileId=(int)($_GET['file_id'] ?? 0);
$token=(string)($_GET['token'] ?? '');
$st=$dbcnx->prepare('SELECT f.*, e.case_id, c.request_id, c.public_token, c.is_active FROM zs_request_files f INNER JOIN zs_request_entries e ON e.id=f.entry_id INNER JOIN zs_request_cases c ON c.id=e.case_id WHERE f.id=? LIMIT 1');
$st->bind_param('i',$fileId); $st->execute(); $file=$st->get_result()->fetch_assoc();
if(!$file || (int)$file['is_active'] !== 1){ http_response_code(404); die('File not found'); }
$allowed = rh_is_admin() || ($token !== '' && hash_equals((string)$file['public_token'], $token));
if(!$allowed){ http_response_code(403); die('Forbidden'); }
$base=realpath(rh_storage_root()); $path=realpath(dirname(__DIR__).'/storage/'.$file['relative_path'].'/'.$file['stored_name']);
if(!$base || !$path || strpos($path,$base)!==0 || !is_file($path)){ http_response_code(404); die('File not found'); }
$size=filesize($path); $start=0; $end=$size-1; header('Content-Type: '.$file['mime_type']); header('X-Content-Type-Options: nosniff'); header('Accept-Ranges: bytes');
if(isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)){
    if($m[1] !== '') $start=(int)$m[1]; if($m[2] !== '') $end=(int)$m[2]; if($end>$size-1) $end=$size-1; if($start>$end){ header('HTTP/1.1 416 Range Not Satisfiable'); header('Content-Range: bytes */'.$size); exit; }
    header('HTTP/1.1 206 Partial Content'); header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
}
$length=$end-$start+1; header('Content-Length: '.$length); $fp=fopen($path,'rb'); fseek($fp,$start); $left=$length; while($left>0 && !feof($fp)){ $chunk=fread($fp,min(8192,$left)); echo $chunk; $left-=strlen($chunk); flush(); } fclose($fp);