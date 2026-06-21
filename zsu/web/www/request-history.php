<?php
session_start();
require_once __DIR__ . '/request_history_lib.php';
$token=(string)($_GET['token'] ?? '');
$case=rh_case_by_token($dbcnx,$token);
if(!$case){ http_response_code(404); die('Історію заявки не знайдено.'); }
$request=rh_request($dbcnx,(int)$case['request_id']); if(!$request){ http_response_code(404); die('Історію заявки не знайдено.'); }
$entries=rh_entries($dbcnx,(int)$case['id']);
?>
<!doctype html>
<html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Історія заявки</title><link href="assets/css/bootstrap.min.css" rel="stylesheet"><style>.rh-photo{max-width:180px;max-height:180px;object-fit:cover;margin:4px}.timeline-entry{border-left:3px solid #0d6efd;padding-left:15px;margin-bottom:22px}.rh-video{max-width:100%;width:420px;margin:6px 0}</style></head><body><main class="container py-4"><h1>Історія заявки</h1><div class="card mb-3"><div class="card-body"><h5 class="card-title"><?=rh_h($request['name'])?></h5><p><strong>Дата заявки:</strong> <?=rh_h($request['date'])?><br><strong>Об'єкт:</strong> <?=rh_h($request['lotImgDir'])?></p></div></div><?php if(!$entries): ?><div class="alert alert-info">Історія роботи із заявкою ще порожня.</div><?php endif; ?><?php foreach($entries as $e): ?><div class="timeline-entry"><div class="text-muted"><?=rh_h($e['created_at'])?></div><?php if($e['entry_text']!==''): ?><div class="mt-2"><?=nl2br(rh_h($e['entry_text']))?></div><?php endif; ?><?php foreach($e['files'] as $f): ?><?php $fileUrl='request_history_file.php?file_id='.(int)$f['id'].'&token='.rawurlencode($token); ?><?php if($f['file_type']==='image'): ?><a href="<?=rh_h($fileUrl)?>" target="_blank"><img class="rh-photo img-thumbnail" src="<?=rh_h($fileUrl)?>" alt="<?=rh_h($f['original_name'])?>"></a><?php else: ?><div><video class="rh-video" controls preload="metadata" src="<?=rh_h($fileUrl)?>"></video></div><?php endif; ?><?php endforeach; ?></div><?php endforeach; ?></main></body></html>