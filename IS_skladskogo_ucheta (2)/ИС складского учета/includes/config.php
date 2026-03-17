<?php
// Основные настройки приложения
define('APP_NAME', 'Информационная система склада');
define('APP_VERSION', '2.0');
define('BASE_URL', '/warhs05.03_php/');
define('DEBUG_MODE', false); // Установите true для режима разработки

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'warehouse_system');
define('DB_CHARSET', 'utf8mb4');

// Настройки SMTP для отправки email
define('SMTP_HOST', 'smtp.mail.ru');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', ''); // Заполните при необходимости
define('SMTP_PASSWORD', ''); // Заполните при необходимости
define('FROM_EMAIL', 'noreply@example.com');
define('FROM_NAME', 'Система складского учета');

// Настройки сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ключ для шифрования персональных и контактных данных в БД
define('ENCRYPTION_KEY', 'qazxswedcvrftgbn');

require_once __DIR__ . '/coding.php';

// Часовой пояс
date_default_timezone_set('Europe/Moscow');

// Функция для проверки роли администратора
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Функция для проверки роли руководителя
function isSupervisor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supervisor';
}

// Функция для проверки роли администратора или руководителя
function isAdminOrSupervisor() {
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'supervisor']);
}

// Функция для проверки авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Функция для получения данных пользователя
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'employee'
    ];
}

// Функция для генерации CSRF токена
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Функция для проверки CSRF токена
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
