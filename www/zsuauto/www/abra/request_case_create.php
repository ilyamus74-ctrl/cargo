<?php
session_start();
require_once __DIR__ . '/../request_history_lib.php';
if (!rh_is_admin()) { http_response_code(403); die('Forbidden'); }
rh_check_csrf();
$requestId=(int)($_POST['request_id'] ?? $_GET['request_id'] ?? 0);
if(!rh_request($dbcnx,$requestId)){ http_response_code(404); die('Request not found'); }
$case=rh_get_or_create_case($dbcnx,$requestId,rh_admin_id());
header('Location: request_case.php?case_id='.(int)$case['id']);