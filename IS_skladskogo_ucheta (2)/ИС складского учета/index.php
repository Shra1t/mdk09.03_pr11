<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/EmailSender.php';

$auth = new Auth();

// Если пользователь уже авторизован, перенаправляем
if ($auth->isLoggedIn()) {
    if ($auth->isAdminOrSupervisor()) {
        header('Location: admin.php');
    } else {
        header('Location: employee.php');
    }
    exit;
}

$error = '';
$success = '';

// Обработка формы авторизации
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Недействительный токен безопасности';
    } elseif (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        if ($auth->login($username, $password)) {
            // Успешная авторизация
            if ($auth->isAdminOrSupervisor()) {
                header('Location: admin.php');
            } else {
                header('Location: employee.php');
            }
            exit;
        } else {
            $error = 'Неверное имя пользователя или пароль';
        }
    }
}

// Обработка формы поддержки
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'support') {
    $email = trim($_POST['email'] ?? '');
    $question = trim($_POST['question'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Недействительный токен безопасности';
    } elseif (empty($email) || empty($question)) {
        $error = 'Заполните все поля формы поддержки';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $error = 'Некорректный формат email или превышена длина (максимум 100 символов)';
    } elseif (strlen($question) > 1000) {
        $error = 'Сообщение не должно превышать 1000 символов';
    } else {
        // Отправка email через EmailSender
        $emailSender = new EmailSender();
        
        // Определяем тип сообщения
        $isAccountRequest = stripos($question, 'заявка') !== false || 
                           stripos($question, 'аккаунт') !== false || 
                           stripos($question, 'пользователь') !== false ||
                           stripos($question, 'сотрудник') !== false ||
                           stripos($question, 'администратор') !== false;
        
        $subject = $isAccountRequest ? 'Заявка на создание аккаунта - Система складского учета' : 'Сообщение из системы складского учета';
        $headerTitle = $isAccountRequest ? '👤 Заявка на создание аккаунта' : '📧 Новое сообщение из системы складского учета';
        
        $message = "
        <html>
        <head>
            <title>{$subject}</title>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
                .header { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; border-radius: 0 0 8px 8px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #6b7280; margin-bottom: 5px; }
                .value { padding: 10px; background: white; border-radius: 4px; border-left: 4px solid #8b5cf6; }
                .footer { margin-top: 20px; padding: 10px; background: #e9ecef; border-radius: 4px; font-size: 12px; color: #6c757d; }
                .highlight { background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>{$headerTitle}</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>📧 Email отправителя:</div>
                    <div class='value'>{$email}</div>
                </div>
                <div class='field'>
                    <div class='label'>💬 Сообщение:</div>
                    <div class='value'>" . nl2br(htmlspecialchars($question)) . "</div>
                </div>
                <div class='field'>
                    <div class='label'>🕒 Время отправки:</div>
                    <div class='value'>" . date('d.m.Y H:i:s') . "</div>
                </div>";
        
        if ($isAccountRequest) {
            $message .= "
                <div class='highlight'>
                    <strong>⚠️ ВНИМАНИЕ: Это заявка на создание аккаунта!</strong><br>
                    Для создания аккаунта перейдите в раздел 'Пользователи' в админ-панели системы.
                </div>";
        }
        
        $message .= "
            </div>
            <div class='footer'>
                <p>Это сообщение отправлено автоматически из системы складского учета.</p>
                <p>Для ответа используйте email: {$email}</p>
                " . ($isAccountRequest ? "<p><strong>Для создания аккаунта:</strong> Войдите в систему как администратор и перейдите в раздел 'Пользователи'</p>" : "") . "
            </div>
        </body>
        </html>
        ";
        
        if ($emailSender->sendSupportEmail('Flacko2018@mail.ru', $subject, $message, $email)) {
            if ($isAccountRequest) {
                $success = 'Ваша заявка на создание аккаунта отправлена! Администратор рассмотрит её и создаст аккаунт в ближайшее время.';
            } else {
                $success = 'Ваш вопрос отправлен в службу поддержки! Мы свяжемся с вами в ближайшее время.';
            }
        } else {
            $error = 'Произошла ошибка при отправке сообщения. Попробуйте позже или свяжитесь с нами напрямую по email: Flacko2018@mail.ru';
        }
    }
}

$pageTitle = 'Авторизация';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Сброс отступов и базовые стили */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 50%, #6d28d9 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Анимированный фон */
        .auth-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }
        
        .auth-background::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(168, 85, 247, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(192, 132, 252, 0.3) 0%, transparent 50%);
        }
        
        /* Основная карточка авторизации */
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            padding: 40px 35px;
            width: 100%;
            max-width: 440px;
            transition: transform 0.3s ease;
        }
        
        .auth-card:hover {
            transform: translateY(-5px);
        }
        
        /* Заголовок */
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            border-radius: 16px;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(139, 92, 246, 0.4);
        }
        
        .auth-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .auth-subtitle {
            color: #6b7280;
            font-size: 1rem;
        }
        
        /* Вкладки */
        .auth-tabs {
            display: flex;
            margin-bottom: 25px;
            border-radius: 12px;
            background: #f8fafc;
            padding: 6px;
            border: 1px solid #e5e7eb;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab-btn.active {
            background: #ffffff;
            color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .tab-btn:hover:not(.active) {
            color: #374151;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeInUp 0.3s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Формы */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #ffffff;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .password-input {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            color: #6b7280;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #8b5cf6;
            background: #f3f4f6;
        }
        
        /* Кнопки */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(107, 114, 128, 0.2);
            padding: 10px 16px;
            font-size: 13px;
            min-width: 90px;
        }
        
        .btn-secondary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.3);
        }
        
        /* Тестовые аккаунты */
        .test-accounts {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .test-accounts h6 {
            margin: 0 0 15px 0;
            font-size: 0.95rem;
            color: #374151;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }
        
        .account-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .account-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.9rem;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            gap: 10px;
        }
        
        .account-item:last-child {
            border-bottom: none;
        }
        
        .account-item strong {
            color: #374151;
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 110px;
        }
        
        .account-item span {
            color: #6b7280;
            flex: 1;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            text-align: center;
            background: rgba(0, 0, 0, 0.03);
            padding: 6px 10px;
            border-radius: 6px;
        }
        
        /* Алерты */
        .alert {
            padding: 14px 18px;
            margin: 15px 0;
            border-radius: 10px;
            border: 1px solid transparent;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border-color: #a7f3d0;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border-color: #fecaca;
        }
        
        /* Адаптивность */
        @media (max-width: 480px) {
            body {
                padding: 15px;
                align-items: flex-start;
                padding-top: 40px;
            }
            
            .auth-card {
                padding: 30px 25px;
                max-width: 100%;
            }
            
            .auth-logo {
                width: 60px;
                height: 60px;
                font-size: 1.6rem;
            }
            
            .auth-title {
                font-size: 1.5rem;
            }
            
            .account-item {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                text-align: center;
            }
            
            .account-item strong {
                min-width: auto;
                justify-content: center;
            }
            
            .account-item span {
                order: -1;
            }
            
            .tab-btn {
                padding: 10px 8px;
                font-size: 13px;
            }
        }
        
        /* Анимация появления */
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px) scale(0.98); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Анимированный фон -->
    <div class="auth-background"></div>
    
    <div class="auth-card fade-in">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="bi bi-box-seam"></i>
            </div>
            <h1 class="auth-title"><?= APP_NAME ?></h1>
            <p class="auth-subtitle">Войдите в систему для продолжения работы</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- Вкладки -->
        <div class="auth-tabs">
            <button class="tab-btn active" onclick="switchTab('login')">
                <i class="bi bi-person"></i> Вход
            </button>
            <button class="tab-btn" onclick="switchTab('support')">
                <i class="bi bi-headset"></i> Поддержка
            </button>
        </div>

        <!-- Форма авторизации -->
        <div id="login-tab" class="tab-content active">
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="bi bi-person"></i> Имя пользователя
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-control"
                        required
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        placeholder="Введите имя пользователя"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock"></i> Пароль
                    </label>
                    <div class="password-input">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            required
                            placeholder="Введите пароль"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="bi bi-eye" id="password-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-2">
                    <i class="bi bi-box-arrow-in-right"></i> Войти в систему
                </button>
            </form>

            <!-- Тестовые учетные записи -->
            <div class="test-accounts">
                <h6><i class="bi bi-info-circle"></i> Быстрый доступ </h6>
                <div class="account-info">
                    <div class="account-item">
                        <strong><i class="bi bi-shield-check"></i> Администратор</strong>
                        <span>admin / password</span>
                        <button type="button" class="btn btn-secondary" onclick="fillCredentials('admin', 'password')">
                            Заполнить
                        </button>
                    </div>
                    <div class="account-item">
                        <strong><i class="bi bi-person-workspace"></i> Сотрудник</strong>
                        <span>employee / password</span>
                        <button type="button" class="btn btn-secondary" onclick="fillCredentials('employee', 'password')">
                            Заполнить
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Форма поддержки -->
        <div id="support-tab" class="tab-content">
            <form method="POST" class="auth-form">
                <input type="hidden" name="action" value="support">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope"></i> Email для связи
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        required
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="your@email.com"
                    >
                </div>

                <div class="form-group">
                    <label for="question" class="form-label">
                        <i class="bi bi-chat-text"></i> Описание проблемы или заявка
                    </label>
                    <textarea
                        id="question"
                        name="question"
                        class="form-control"
                        rows="4"
                        required
                        placeholder="Опишите вашу проблему, вопрос или подайте заявку на создание аккаунта (укажите ФИО, должность, отдел и желаемую роль)..."
                    ><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
                    <small class="form-text text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Через эту форму также можно подать заявку на создание аккаунта. Укажите ФИО, должность, отдел и желаемую роль (сотрудник/администратор).
                    </small>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-2">
                    <i class="bi bi-send"></i> Отправить запрос
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Переключение вкладок
        function switchTab(tabName) {
            // Скрыть все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Удалить активный класс с кнопок
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Показать выбранную вкладку
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        // Переключение видимости пароля
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('password-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.className = 'bi bi-eye-slash';
            } else {
                passwordInput.type = 'password';
                passwordIcon.className = 'bi bi-eye';
            }
        }
        
        // Заполнение тестовых учетных данных
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Переключиться на вкладку входа, если мы не на ней
            if (!document.getElementById('login-tab').classList.contains('active')) {
                switchTab('login');
            }
            
            // Показать небольшое подтверждение
            const btn = event.target;
            const originalText = btn.textContent;
            
            btn.textContent = '✓ Заполнено!';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-secondary');
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-secondary');
            }, 1500);
        }
        
        // Анимация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.auth-card').classList.add('fade-in');
        });
    </script>
</body>
</html>