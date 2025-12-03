<?php
session_start();
declare(strict_types=1);
///print_r($_SERVER);
//print_r($_POST);
require_once __DIR__ . '/../configs/secure.php';

$error = null;

// 1. Обработка логина
// НОРМАЛЬНО: использовать только POST
// ВРЕМЕННО: можем поддержать и GET, раз ты дергаешь ?username=&password=
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['username'], $_GET['password'])) {
    // Берём из POST, если есть, иначе из GET
    $username = trim($_POST['username'] ?? $_GET['username'] ?? '');
    $password = (string)($_POST['password'] ?? $_GET['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Введите логин и пароль';
    } else {
        // ВОТ ТУТ вызывается авторизация
        if (auth_login($username, $password)) {
            // Успешный вход — отправляем на главную рабочую страницу
            header("Location: /main");
            //echo "USPEH";
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}
//    echo "aaaaaaaa";

/*
if (!isset($smarty)) {
    // на случай кривого вызова
    require_once __DIR__ . '/../libs/Smarty.class.php';
    $smarty = class_exists('\\Smarty\\Smarty') ? new \Smarty\Smarty : new Smarty();
    require_once __DIR__ . '/patch.php';
}
*/

$header_data['domainName']=$domainName;
$smarty->assign('header_data', $header_data);
$smarty->assign('login','login');

$smarty->display('cells_login_main.html');


?>