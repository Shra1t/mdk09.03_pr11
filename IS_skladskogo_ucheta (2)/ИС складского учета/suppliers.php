<?php
require_once 'includes/config.php';

require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Supplier.php';
require_once 'classes/Delivery.php';

$auth = new Auth();
$auth->requireLogin();

$supplier = new Supplier();
$delivery = new Delivery();

$error = '';
$success = '';

// Обработка форм
if ($_POST) {
    try {
        // Проверка CSRF токена
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Недействительный токен безопасности');
        }

        switch ($_POST['action']) {
            case 'add_supplier':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для добавления поставщика');
                }

                // Валидация данных поставщика
                $supplierName = trim($_POST['supplier_name']);
                if (empty($supplierName) || strlen($supplierName) > 100) {
                    throw new Exception('Название поставщика должно быть от 1 до 100 символов');
                }
                
                $companyName = trim($_POST['company_name'] ?? '');
                if (strlen($companyName) > 100) {
                    throw new Exception('Название компании не должно превышать 100 символов');
                }
                
                $contactPerson = trim($_POST['contact_person']);
                if (empty($contactPerson) || strlen($contactPerson) > 100) {
                    throw new Exception('Контактное лицо должно быть от 1 до 100 символов');
                }
                
                $email = trim($_POST['email']);
                if (!empty($email) && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100)) {
                    throw new Exception('Некорректный email или превышена длина (максимум 100 символов)');
                }
                
                $phone = trim($_POST['phone']);
                if (empty($phone) || strlen($phone) > 20) {
                    throw new Exception('Телефон должен быть от 1 до 20 символов');
                }
                
                $address = trim($_POST['address']);
                if (strlen($address) > 255) {
                    throw new Exception('Адрес не должен превышать 255 символов');
                }
                
                $paymentTerms = trim($_POST['payment_terms']);
                if (strlen($paymentTerms) > 50) {
                    throw new Exception('Условия оплаты не должны превышать 50 символов');
                }
                
                $deliveryTerms = trim($_POST['delivery_terms']);
                if (strlen($deliveryTerms) > 50) {
                    throw new Exception('Условия поставки не должны превышать 50 символов');
                }
                
                $notes = trim($_POST['notes']);
                if (strlen($notes) > 1000) {
                    throw new Exception('Примечания не должны превышать 1000 символов');
                }

                $supplierData = [
                    'name' => $supplierName,
                    'company_name' => $companyName,
                    'contact_person' => $contactPerson,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'payment_terms' => $paymentTerms,
                    'delivery_terms' => $deliveryTerms,
                    'notes' => $notes
                ];

                $supplier->create($supplierData);
                $success = 'Поставщик успешно добавлен';
                break;

            case 'edit_supplier':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для редактирования поставщика');
                }

                $supplierId = (int)$_POST['supplier_id'];
                if ($supplierId <= 0) {
                    throw new Exception('Неверный ID поставщика');
                }
                
                $supplierData = [
                    'name' => trim($_POST['supplier_name']),
                    'company_name' => trim($_POST['company_name'] ?? ''),
                    'contact_person' => trim($_POST['contact_person']),
                    'email' => trim($_POST['email']),
                    'phone' => trim($_POST['phone']),
                    'address' => trim($_POST['address']),
                    'payment_terms' => trim($_POST['payment_terms']),
                    'delivery_terms' => trim($_POST['delivery_terms']),
                    'notes' => trim($_POST['notes'])
                ];

                $supplier->update($supplierId, $supplierData);
                $success = 'Поставщик успешно обновлен';
                break;

            case 'delete_supplier':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для удаления поставщика');
                }

                $supplierId = (int)$_POST['supplier_id'];
                $supplier->delete($supplierId);
                $success = 'Поставщик успешно удален';
                break;

            case 'deactivate_supplier':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для деактивации поставщика');
                }

                $supplierId = (int)$_POST['supplier_id'];
                $supplier->deactivate($supplierId);
                $success = 'Поставщик деактивирован';
                break;

            case 'toggle_status':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для изменения статуса');
                }

                $supplierId = (int)$_POST['supplier_id'];
                if ($supplierId <= 0) {
                    throw new Exception('Неверный ID поставщика');
                }
                
                $supplier->toggleStatus($supplierId);
                $success = 'Статус поставщика изменен';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение данных с фильтрами
$filters = [];
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['sort'])) {
    $filters['sort'] = $_GET['sort'];
}

$suppliers = $supplier->getAll($filters);

// Получаем статистику по поставщикам
foreach ($suppliers as $key => $sup) {
    $suppliers[$key]['deliveries_count'] = $delivery->getCountBySupplier($sup['id']);
    $suppliers[$key]['total_amount'] = $delivery->getTotalAmountBySupplier($sup['id']);
    $suppliers[$key]['last_delivery'] = $delivery->getLastDeliveryBySupplier($sup['id']);
}

// Фильтрация (дополнительная на стороне PHP для совместимости)
$statusFilter = $_GET['status'] ?? '';
$sortFilter = $_GET['sort'] ?? 'name';
$searchQuery = $_GET['search'] ?? '';

if ($statusFilter && empty($filters['status'])) {
    $suppliers = array_filter($suppliers, fn($s) => $s['status'] == $statusFilter);
}

if ($searchQuery && empty($filters['search'])) {
    $suppliers = array_filter($suppliers, fn($s) =>
        stripos($s['name'], $searchQuery) !== false ||
        stripos($s['contact_person'], $searchQuery) !== false ||
        stripos($s['email'], $searchQuery) !== false ||
        stripos($s['company_name'], $searchQuery) !== false
    );
}

// Сортировка
switch ($sortFilter) {
    case 'deliveries_count':
        usort($suppliers, fn($a, $b) => $b['deliveries_count'] <=> $a['deliveries_count']);
        break;
    case 'total_amount':
        usort($suppliers, fn($a, $b) => $b['total_amount'] <=> $a['total_amount']);
        break;
    case 'created_at':
        usort($suppliers, fn($a, $b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));
        break;
    default: // name
        usort($suppliers, fn($a, $b) => strcasecmp($a['name'] ?? '', $b['name'] ?? ''));
        break;
}

$pageTitle = 'Управление поставщиками';
$breadcrumb = [
    ['title' => isAdminOrSupervisor() ? 'Панель управления' : 'Рабочая панель', 'url' => isAdminOrSupervisor() ? 'admin.php' : 'employee.php'],
    ['title' => 'Поставщики']
];

include 'includes/header.php';

// Опции для условий оплаты и доставки
$paymentTermsOptions = [
    'prepayment' => 'Предоплата 100%',
    '50_50' => '50% предоплата + 50% по факту',
    'postpayment' => 'Оплата по факту',
    'credit_30' => 'Отсрочка 30 дней',
    'credit_60' => 'Отсрочка 60 дней'
];

$deliveryTermsOptions = [
    'pickup' => 'Самовывоз',
    'delivery_paid' => 'Доставка платная',
    'delivery_free' => 'Доставка бесплатная',
    'delivery_conditional' => 'Доставка при условии'
];
?>

<div class="container-fluid">
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Заголовок с кнопками действий -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1">
                <i class="bi bi-building text-primary"></i>
                Управление поставщиками
            </h2>
            <p class="text-muted mb-0">Просмотр и управление поставщиками товаров</p>
        </div>
        <?php if (isAdminOrSupervisor()): ?>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="bi bi-plus-circle"></i> Добавить поставщика
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Статистика поставщиков -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-building text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($suppliers) ?></h4>
                    <p class="text-muted mb-0">Всего поставщиков</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($suppliers, fn($s) => ($s['status'] ?? '') == 'active')) ?></h4>
                    <p class="text-muted mb-0">Активные</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= array_sum(array_column($suppliers, 'deliveries_count')) ?></h4>
                    <p class="text-muted mb-0">Всего поставок</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-currency-exchange text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= number_format(array_sum(array_column($suppliers, 'total_amount')), 0) ?> ₽</h4>
                    <p class="text-muted mb-0">Общий оборот</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Фильтры и поиск -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-funnel"></i> Фильтры и поиск</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Поиск</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?= htmlspecialchars($searchQuery) ?>"
                               placeholder="Название, контакт, email или компания">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Статус</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Все статусы</option>
                        <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Активные</option>
                        <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Неактивные</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Сортировка</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="name" <?= $sortFilter == 'name' ? 'selected' : '' ?>>По названию</option>
                        <option value="deliveries_count" <?= $sortFilter == 'deliveries_count' ? 'selected' : '' ?>>По кол-ву поставок</option>
                        <option value="total_amount" <?= $sortFilter == 'total_amount' ? 'selected' : '' ?>>По сумме сделок</option>
                        <option value="created_at" <?= $sortFilter == 'created_at' ? 'selected' : '' ?>>По дате добавления</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Найти
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Список поставщиков -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list"></i> Список поставщиков
                <span class="badge bg-secondary"><?= count($suppliers) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($suppliers)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">Поставщики не найдены</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($suppliers as $sup): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0"><?= htmlspecialchars($sup['name'] ?? 'Без названия') ?></h6>
                                    <?php if (!empty($sup['company_name'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars($sup['company_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if (($sup['status'] ?? '') == 'active'): ?>
                                        <span class="badge bg-success">Активен</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Неактивен</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <strong><i class="bi bi-person"></i> Контакт:</strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($sup['contact_person']) ?></small>
                                </div>
                                
                                <div class="mb-2">
                                    <strong><i class="bi bi-credit-card"></i> Условия оплаты:</strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($paymentTermsOptions[$sup['payment_terms'] ?? 'prepayment'] ?? 'Не указано') ?>
                                    </small>
                                </div>
                                
                                <div class="mb-2">
                                    <strong><i class="bi bi-truck"></i> Условия доставки:</strong><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($deliveryTermsOptions[$sup['delivery_terms'] ?? 'pickup'] ?? 'Не указано') ?>
                                    </small>
                                </div>

                                <div class="mb-2">
                                    <strong><i class="bi bi-telephone"></i> Телефон:</strong><br>
                                    <small><a href="tel:<?= htmlspecialchars($sup['phone']) ?>"><?= htmlspecialchars($sup['phone']) ?></a></small>
                                </div>
                                <div class="mb-2">
                                    <strong><i class="bi bi-envelope"></i> Email:</strong><br>
                                    <small><a href="mailto:<?= htmlspecialchars($sup['email']) ?>"><?= htmlspecialchars($sup['email']) ?></a></small>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="bi bi-geo-alt"></i> Адрес:</strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($sup['address'] ?? 'Не указан') ?></small>
                                </div>

                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border-end">
                                            <h6 class="mb-0"><?= $sup['deliveries_count'] ?></h6>
                                            <small class="text-muted">Поставок</small>
                                        </div>
                                    </div>
                                    <div class="col-8">
                                        <h6 class="mb-0"><?= number_format($sup['total_amount'], 2) ?> ₽</h6>
                                        <small class="text-muted">Общая сумма</small>
                                    </div>
                                </div>

                                <?php if ($sup['last_delivery']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> Последняя поставка:
                                        <?= date('d.m.Y', strtotime($sup['last_delivery']['order_date'])) ?>
                                    </small>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($sup['notes'])): ?>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="bi bi-chat-text"></i> <?= htmlspecialchars($sup['notes']) ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (isAdminOrSupervisor()): ?>
                            <div class="card-footer">
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                            onclick="editSupplier(<?= htmlspecialchars(json_encode($sup)) ?>)"
                                            data-bs-toggle="modal" data-bs-target="#editSupplierModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm"
                                            onclick="toggleStatus(<?= (int)($sup['id'] ?? 0) ?>, '<?= htmlspecialchars($sup['name'] ?? '') ?>', '<?= $sup['status'] ?? '' ?>')">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="deleteSupplier(<?= (int)($sup['id'] ?? 0) ?>, '<?= htmlspecialchars($sup['name'] ?? '') ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isAdminOrSupervisor()): ?>
<!-- Модальное окно добавления поставщика -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Добавить поставщика</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_supplier">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="supplier_name" class="form-label">ФИО поставщика *</label>
                                <input type="text" class="form-control" id="supplier_name" name="supplier_name" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="company_name" class="form-label">Название компании</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="contact_person" class="form-label">Контактное лицо *</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="phone" class="form-label">Телефон *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required maxlength="20">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="address" class="form-label">Адрес</label>
                                <textarea class="form-control" id="address" name="address" rows="2" maxlength="255"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="payment_terms" class="form-label">Условия оплаты</label>
                                <select class="form-select" id="payment_terms" name="payment_terms">
                                    <?php foreach ($paymentTermsOptions as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="delivery_terms" class="form-label">Условия доставки</label>
                                <select class="form-select" id="delivery_terms" name="delivery_terms">
                                    <?php foreach ($deliveryTermsOptions as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Примечания</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000"
                                  placeholder="Дополнительная информация о поставщике..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить поставщика
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования поставщика -->
<div class="modal fade" id="editSupplierModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Редактировать поставщика</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editSupplierForm" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_supplier">
                    <input type="hidden" name="supplier_id" id="edit_supplier_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_supplier_name" class="form-label">ФИО поставщика *</label>
                                <input type="text" class="form-control" id="edit_supplier_name" name="supplier_name" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_company_name" class="form-label">Название компании</label>
                                <input type="text" class="form-control" id="edit_company_name" name="company_name" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_contact_person" class="form-label">Контактное лицо *</label>
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_phone" class="form-label">Телефон *</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" required maxlength="20">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="edit_email" name="email" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_address" class="form-label">Адрес</label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2" maxlength="255"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_payment_terms" class="form-label">Условия оплаты</label>
                                <select class="form-select" id="edit_payment_terms" name="payment_terms">
                                    <?php foreach ($paymentTermsOptions as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_delivery_terms" class="form-label">Условия доставки</label>
                                <select class="form-select" id="edit_delivery_terms" name="delivery_terms">
                                    <?php foreach ($deliveryTermsOptions as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="edit_notes" class="form-label">Примечания</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (isAdminOrSupervisor()): ?>
// Функция редактирования поставщика
function editSupplier(supplier) {
    // Проверяем, что supplier является объектом и имеет ID
    if (!supplier || !supplier.id) {
        console.error('Invalid supplier data:', supplier);
        alert('Ошибка: неверные данные поставщика');
        return;
    }
    
    console.log('Editing supplier:', supplier); // Для отладки
    
    document.getElementById('edit_supplier_id').value = supplier.id;
    document.getElementById('edit_supplier_name').value = supplier.name || '';
    document.getElementById('edit_company_name').value = supplier.company_name || '';
    document.getElementById('edit_contact_person').value = supplier.contact_person || '';
    document.getElementById('edit_email').value = supplier.email || '';
    document.getElementById('edit_phone').value = supplier.phone || '';
    document.getElementById('edit_address').value = supplier.address || '';
    document.getElementById('edit_payment_terms').value = supplier.payment_terms || 'prepayment';
    document.getElementById('edit_delivery_terms').value = supplier.delivery_terms || 'pickup';
    document.getElementById('edit_notes').value = supplier.notes || '';
}

// Функция изменения статуса
function toggleStatus(supplierId, supplierName, currentStatus) {
    // Проверяем, что supplierId является числом
    if (!supplierId || isNaN(supplierId)) {
        alert('Ошибка: неверный ID поставщика');
        return;
    }
    
    const newStatus = currentStatus === 'active' ? 'неактивным' : 'активным';
    const confirmMessage = `Сделать поставщика "${supplierName}" (ID: ${supplierId}) ${newStatus}?`;
    
    if (confirm(confirmMessage)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="supplier_id" value="${parseInt(supplierId)}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Функция удаления поставщика
function deleteSupplier(supplierId, supplierName) {
    const message = `Вы уверены, что хотите удалить поставщика "${supplierName}"?\n\n` +
                   `Внимание: Если у поставщика есть поставки, удаление будет невозможно.\n` +
                   `В таком случае поставщик будет деактивирован.`;
    
    if (confirm(message)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_supplier">
            <input type="hidden" name="supplier_id" value="${supplierId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>