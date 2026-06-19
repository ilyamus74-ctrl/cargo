<?php
declare(strict_types=1);

use App\Forwarder\Config\ForwarderConfig;
use App\Forwarder\Http\ForwarderHttpClient;
use App\Forwarder\Http\ForwarderSessionClient;
use App\Forwarder\Http\SessionManager;
use App\Forwarder\Logging\ForwarderLogger;
use App\Forwarder\Services\LoginService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../../../configs/connectDB.php';

function pd_args(array $argv): array { $r=[]; foreach($argv as $a){ if(!is_string($a)||!str_starts_with($a,'--'))continue; $p=strpos($a,'='); if($p===false){$r[substr($a,2)]='1';}else{$r[substr($a,2,$p-2)]=substr($a,$p+1);} } return $r; }
function pd_arg(array $a,string ...$keys): string { foreach($keys as $k){ if(isset($a[$k])) return trim((string)$a[$k]); } return ''; }
function pd_out(array $p,int $c=0): void { echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT).PHP_EOL; exit($c); }
function pd_env(string $k,string $v): void { if($v==='')return; putenv($k.'='.$v); $_ENV[$k]=$v; }
function pd_base(string $u): string { $u=trim($u); if($u==='')return ''; $p=@parse_url($u); if(!is_array($p)||empty($p['scheme'])||empty($p['host'])) return rtrim($u,'/'); $b=strtolower($p['scheme']).'://'.$p['host']; if(isset($p['port']))$b.=':'.(int)$p['port']; return rtrim($b,'/'); }
function pd_norm_track(string $v): string { $v=trim($v); if(strlen($v)>=2 && (($v[0]==='"'&&substr($v,-1)==='"')||($v[0]==="'"&&substr($v,-1)==="'"))) $v=substr($v,1,-1); return trim($v); }
function pd_connector(mysqli $db,int $id): ?array { $s=$db->prepare('SELECT id,name,countries,base_url,auth_username,auth_password FROM connectors WHERE id=? LIMIT 1'); if(!$s)return null; $s->bind_param('i',$id); $s->execute(); $r=$s->get_result(); $row=$r?$r->fetch_assoc():null; $s->close(); return $row?:null; }
function pd_ensure_clients(mysqli $db): void { $adds=['client_phone'=>'ALTER TABLE connector_clients ADD COLUMN client_phone VARCHAR(64) NULL','client_payload_json'=>'ALTER TABLE connector_clients ADD COLUMN client_payload_json LONGTEXT NULL','details_synced_at'=>'ALTER TABLE connector_clients ADD COLUMN details_synced_at DATETIME NULL','details_source'=>'ALTER TABLE connector_clients ADD COLUMN details_source VARCHAR(64) NULL','details_last_error'=>'ALTER TABLE connector_clients ADD COLUMN details_last_error TEXT NULL']; $cols=[]; if($res=$db->query('SHOW COLUMNS FROM connector_clients')){while($row=$res->fetch_assoc())$cols[strtolower($row['Field'])]=1; $res->free();} foreach($adds as $c=>$sql){ if(!isset($cols[$c])) @$db->query($sql); } }
function pd_upsert_client(mysqli $db,int $connectorId,string $country,array $p,array $raw): void { if($connectorId<=0||trim((string)($p['client_id']??''))==='')return; pd_ensure_clients($db); $clientId=trim((string)$p['client_id']); $name=trim((string)($p['client_name']??$p['client']??'')); $phone=trim((string)($p['client_phone']??'')); $addr=trim((string)($p['client_address']??'')); $payload=json_encode(['package'=>$p,'raw'=>$raw],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $sql="INSERT INTO connector_clients (connector_id,country_code,client_id,client_name,client_phone,client_address,client_payload_json,details_synced_at,details_source,details_last_error) VALUES (?,?,?,?,?,?,?,NOW(),'check-package',NULL) ON DUPLICATE KEY UPDATE client_name=IF(VALUES(client_name)<>'',VALUES(client_name),client_name), client_phone=IF(VALUES(client_phone)<>'',VALUES(client_phone),client_phone), client_address=IF(VALUES(client_address)<>'',VALUES(client_address),client_address), client_payload_json=VALUES(client_payload_json), details_synced_at=NOW(), details_source='check-package', details_last_error=NULL"; $s=$db->prepare($sql); if($s){$s->bind_param('issssss',$connectorId,$country,$clientId,$name,$phone,$addr,$payload); $s->execute(); $s->close();} }
function pd_pick(array $a,string $k): string { return trim((string)($a[$k]??'')); }

$args=pd_args($_SERVER['argv']??[]); $connectorId=(int)pd_arg($args,'connector-id','connector_id'); $track=pd_norm_track(pd_arg($args,'track','tracking-no','tracking_no','number'));
try{
 global $dbcnx; $connector=null; if($connectorId>0 && isset($dbcnx)&&$dbcnx instanceof mysqli){$connector=pd_connector($dbcnx,$connectorId);} $name=strtoupper(trim((string)($connector['name']??'')));
 if($connector){ if(pd_arg($args,'base-url','base_url')==='')$args['base-url']=(string)$connector['base_url']; if(pd_arg($args,'login')==='')$args['login']=(string)$connector['auth_username']; if(pd_arg($args,'password')==='')$args['password']=(string)$connector['auth_password']; }
 if(str_contains($name,'ASER')) pd_out(['status'=>'error','message'=>'package details is not implemented for ASER'],1);
 if($track==='') pd_out(['status'=>'error','message'=>'missing --track'],2);
 pd_env('DEV_COLIBRI_BASE_URL',pd_base(pd_arg($args,'base-url','base_url'))); pd_env('FORWARDER_BASE_URL',pd_base(pd_arg($args,'base-url','base_url'))); pd_env('DEV_COLIBRI_LOGIN',pd_arg($args,'login')); pd_env('FORWARDER_LOGIN',pd_arg($args,'login')); pd_env('DEV_COLIBRI_PASSWORD',pd_arg($args,'password')); pd_env('FORWARDER_PASSWORD',pd_arg($args,'password')); $sf=pd_arg($args,'session-file','session_file'); if($sf===''&&$connectorId>0)$sf=dirname(__DIR__,4).'/storage/forwarder_sessions/connector_'.$connectorId.'.cookie'; pd_env('FORWARDER_SESSION_FILE',$sf);
 $config=new ForwarderConfig(); if(!$config->isConfigured()) pd_out(['status'=>'error','message'=>'missing config (base-url/login/password)'],3);
 $logger=new ForwarderLogger('package-details'); $http=new ForwarderHttpClient($config); $session=new SessionManager($config->sessionCookieFile(),$config->sessionTtlSeconds()); $login=new LoginService($config,$http,$session,$logger); $client=new ForwarderSessionClient($config,$http,$session,$login,$logger);
 $client->ensureSession(); $client->requestWithSession('GET','/collector/packages',[],false); $resp=$client->requestWithSession('POST','/collector/check-package',['number'=>$track],true); if((int)($resp['status_code']??0)===419){$client->requestWithSession('GET','/collector/packages',[],false); $resp=$client->requestWithSession('POST','/collector/check-package',['number'=>$track],true);} $raw=is_array($resp['json']??null)?$resp['json']:json_decode((string)($resp['body']??''),true); if(!is_array($raw)) pd_out(['status'=>'error','message'=>'invalid check-package response','raw_body'=>(string)($resp['body']??'')],1);
 $pkg=is_array($raw['package']??null)?$raw['package']:[]; $out=[]; foreach(['internal_id','track','client_id','client','client_name','client_phone','client_address','category','invoice','invoice_usd','currency','invoice_doc','gross_weight','volume_weight','seller','seller_title','amount','flight_departure','flight_destination','flight_name','destination','title','description','client_comment'] as $k){$out[$k]=pd_pick($pkg,$k);} if($out['client_id']!=='' && empty($out['client_code'])) $out['client_code']='C'.$out['client_id'];
 if(isset($dbcnx)&&$dbcnx instanceof mysqli && strtolower((string)($raw['status']??''))!=='error') pd_upsert_client($dbcnx,$connectorId,strtoupper(substr(trim((string)($connector['countries']??'AZ')),0,2)),$out,$raw);
 pd_out(['status'=>'ok','connector_id'=>$connectorId,'tracking_no'=>$track,'package_exist'=>(bool)($raw['package_exist']??false),'client_exist'=>(bool)($raw['client_exist']??false),'package'=>$out,'raw'=>$raw]);
}catch(Throwable $e){ pd_out(['status'=>'error','message'=>$e->getMessage()],1); }
