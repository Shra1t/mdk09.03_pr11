<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$auth = new Auth();
$auth->logout();

// Перенаправляем на главную страницу
header('Location: index.php');
exit;
?>