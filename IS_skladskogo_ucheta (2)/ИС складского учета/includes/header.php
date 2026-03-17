<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Система склада' ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/validation.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/modal.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <div class="app-layout">
        <!-- Боковое меню -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="<?= BASE_URL ?>img/logo.png" alt="Логотип" class="sidebar-logo">
                    <h4 class="app-title"><?= APP_NAME ?></h4>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <?php if (isAdmin()): ?>
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'admin.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>admin.php" class="nav-link">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span>Панель управления</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'employee.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>employee.php" class="nav-link">
                            <i class="bi bi-speedometer2 nav-icon"></i>
                            <span>Рабочая панель</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'products.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>products.php" class="nav-link">
                            <i class="bi bi-box-seam nav-icon"></i>
                            <span>Товары</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'deliveries.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>deliveries.php" class="nav-link">
                            <i class="bi bi-truck nav-icon"></i>
                            <span>Поставки</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'suppliers.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>suppliers.php" class="nav-link">
                            <i class="bi bi-people nav-icon"></i>
                            <span>Поставщики</span>
                        </a>
                    </li>
                    
                    <?php if (isAdminOrSupervisor()): ?>
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'users.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>users.php" class="nav-link">
                            <i class="bi bi-people nav-icon"></i>
                            <span>Пользователи</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?= basename($_SERVER['SCRIPT_NAME']) == 'reports.php' ? 'active' : '' ?>">
                        <a href="<?= BASE_URL ?>reports.php" class="nav-link">
                            <i class="bi bi-file-earmark-bar-graph nav-icon"></i>
                            <span>Отчеты</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="sidebar-user">
                    <div class="user-info">
                        <i class="bi bi-person-circle user-icon"></i>
                        <div class="user-details">
                            <div class="user-name"><?= htmlspecialchars(getCurrentUser()['full_name']) ?></div>
                            <div class="user-role">
                                <?php 
                                $role = getCurrentUser()['role'];
                                if ($role == 'supervisor') {
                                    echo 'Руководитель';
                                } elseif ($role == 'admin') {
                                    echo 'Администратор';
                                } else {
                                    echo 'Сотрудник';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>logout.php" class="logout-btn" title="Выйти">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </nav>
        </div>
        
        <!-- Основной контент -->
        <div class="main-content">
            <div class="content-header">
                <h1 class="page-title"><?= $pageTitle ?? 'Страница' ?></h1>
                <?php if (isset($breadcrumb)): ?>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['url'])): ?>
                                <li class="breadcrumb-item"><a href="<?= $item['url'] ?>"><?= htmlspecialchars($item['title']) ?></a></li>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?= htmlspecialchars($item['title']) ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
            </div>
            
            <div class="content-body">
    <?php endif; ?>