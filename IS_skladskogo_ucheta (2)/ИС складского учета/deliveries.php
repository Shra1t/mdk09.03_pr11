<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Delivery.php';
require_once 'classes/Supplier.php';
require_once 'classes/Product.php';
require_once 'classes/Category.php';

// Функция валидации дат
function validateDate($date, $format = 'Y-m-d') {
    if (empty($date)) return false;
    
    $d = DateTime::createFromFormat($format, $date);
    if (!$d || $d->format($format) !== $date) {
        return false;
    }
    
    // Проверяем разумные границы дат
    $year = (int)$d->format('Y');
    if ($year < 1900 || $year > 2100) {
        return false;
    }
    
    return true;
}

$auth = new Auth();
$auth->requireLogin();

$delivery = new Delivery();
$supplier = new Supplier();
$product = new Product();

$error = '';
$success = '';

// Отладочная информация для проверки прав (удалено для продакшена)

// Обработка форм
if ($_POST) {
    try {
        // Проверка CSRF токена
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Недействительный токен безопасности');
        }

        switch ($_POST['action']) {
            case 'add_delivery':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для создания поставки');
                }
                
                // Отладочная информация (удалено для продакшена)

                // Валидация данных
                $deliveryCode = trim($_POST['delivery_code']);
                if (empty($deliveryCode) || strlen($deliveryCode) > 20) {
                    throw new Exception('Код поставки должен быть от 1 до 20 символов');
                }
                
                $supplierId = (int)$_POST['supplier_id'];
                if ($supplierId <= 0) {
                    throw new Exception('Выберите поставщика');
                }
                
                $orderDate = $_POST['order_date'];
                if (!validateDate($orderDate)) {
                    throw new Exception('Некорректная дата заказа');
                }
                
                $expectedDate = $_POST['expected_date'];
                if (!validateDate($expectedDate)) {
                    throw new Exception('Некорректная ожидаемая дата');
                }
                
                $totalAmount = (float)$_POST['total_amount'];
                if ($totalAmount < 0 || $totalAmount > 999999999) {
                    throw new Exception('Сумма должна быть от 0 до 999,999,999');
                }
                
                $notes = trim($_POST['notes']);
                if (strlen($notes) > 1000) {
                    throw new Exception('Примечания не должны превышать 1000 символов');
                }

                $deliveryData = [
                    'delivery_code' => $deliveryCode,
                    'supplier_id' => $supplierId,
                    'order_date' => $orderDate,
                    'expected_date' => $expectedDate,
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'notes' => $notes,
                    'created_by' => $_SESSION['user_id']
                ];

                $deliveryId = $delivery->create($deliveryData);
                
                // Обрабатываем товары в поставке
                if (isset($_POST['products']) && is_array($_POST['products'])) {
                    $totalCalculated = 0;
                    foreach ($_POST['products'] as $productData) {
                        $quantity = (int)($productData['quantity_ordered'] ?? 0);
                        if ($quantity <= 0 || $quantity > 999999) {
                            throw new Exception('Количество должно быть от 1 до 999,999');
                        }

                        $existingId = (int)($productData['existing_product_id'] ?? 0);
                        if ($existingId > 0) {
                            // Используем существующий товар
                            $existing = $product->getById($existingId);
                            if (!$existing) {
                                throw new Exception('Выбранный товар не найден');
                            }

                            $price = isset($productData['price']) && $productData['price'] !== ''
                                ? (float)$productData['price']
                                : (float)$existing['price'];
                            if ($price < 0 || $price > 999999999) {
                                throw new Exception('Цена товара должна быть от 0 до 999,999,999');
                            }

                            $deliveryItem = [
                                'delivery_id' => $deliveryId,
                                'product_code' => $existing['product_code'],
                                'product_name' => $existing['name'],
                                'product_description' => $existing['description'],
                                'category_id' => $existing['category_id'],
                                'price' => $price,
                                'quantity_ordered' => $quantity,
                                'quantity_received' => 0,
                                'unit' => $existing['unit'],
                                'min_stock_level' => $existing['min_stock_level']
                            ];
                            $delivery->addDeliveryItem($deliveryItem);
                            $totalCalculated += $price * $quantity;
                        } else if (!empty($productData['product_code']) && !empty($productData['product_name'])) {
                            // Создаем новый товар
                            $productCode = trim($productData['product_code']);
                            if (strlen($productCode) > 20) {
                                throw new Exception('Код товара не должен превышать 20 символов');
                            }

                            $productName = trim($productData['product_name']);
                            if (strlen($productName) > 100) {
                                throw new Exception('Название товара не должно превышать 100 символов');
                            }

                            $price = (float)$productData['price'];
                            if ($price < 0 || $price > 999999999) {
                                throw new Exception('Цена товара должна быть от 0 до 999,999,999');
                            }

                            $minStock = (int)($productData['min_stock_level'] ?? 0);
                            if ($minStock < 0 || $minStock > 999999) {
                                throw new Exception('Минимальный остаток должен быть от 0 до 999,999');
                            }

                            $productInfo = [
                                'product_code' => $productCode,
                                'name' => $productName,
                                'description' => trim($productData['product_description'] ?? ''),
                                'category_id' => (int)$productData['category_id'],
                                'price' => $price,
                                'quantity_in_stock' => 0,
                                'min_stock_level' => $minStock,
                                'unit' => trim($productData['unit'] ?? 'шт'),
                                'delivery_id' => $deliveryId,
                                'order_status' => 'ordered',
                                'is_active' => 1
                            ];
                            $product->create($productInfo);

                            $deliveryItem = [
                                'delivery_id' => $deliveryId,
                                'product_code' => $productInfo['product_code'],
                                'product_name' => $productInfo['name'],
                                'product_description' => $productInfo['description'],
                                'category_id' => $productInfo['category_id'],
                                'price' => $productInfo['price'],
                                'quantity_ordered' => $quantity,
                                'quantity_received' => 0,
                                'unit' => $productInfo['unit'],
                                'min_stock_level' => $productInfo['min_stock_level']
                            ];
                            $delivery->addDeliveryItem($deliveryItem);
                            $totalCalculated += $price * $quantity;
                        }
                    }

                    // Обновляем общую сумму поставки
                    $delivery->update($deliveryId, ['total_amount' => $totalCalculated]);
                }
                
                $success = 'Поставка успешно создана с товарами';
                break;

            case 'edit_delivery':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для редактирования поставки');
                }

                $deliveryId = (int)$_POST['delivery_id'];
                $deliveryData = [
                    'delivery_code' => trim($_POST['delivery_code']),
                    'supplier_id' => (int)$_POST['supplier_id'],
                    'order_date' => $_POST['order_date'],
                    'expected_date' => $_POST['expected_date'],
                    'total_amount' => (float)$_POST['total_amount'],
                    'notes' => trim($_POST['notes'])
                ];

                $delivery->update($deliveryId, $deliveryData);
                $success = 'Поставка успешно обновлена';
                break;

            case 'update_status':
                $deliveryId = (int)$_POST['delivery_id'];
                $newStatus = $_POST['new_status'];

                $delivery->updateStatus($deliveryId, $newStatus);
                $success = 'Статус поставки обновлен';
                break;

            case 'receive_delivery':
                $deliveryId = (int)$_POST['delivery_id'];
                $delivery->markAsReceived($deliveryId);
                $success = 'Поставка принята на склад';
                break;

            case 'delete_delivery':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для удаления поставки');
                }

                $deliveryId = (int)$_POST['delivery_id'];
                $delivery->delete($deliveryId);
                $success = 'Поставка успешно удалена';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение данных
$deliveries = $delivery->getAll();
$suppliers = $supplier->getAll();

// Получаем категории для форм
$category = new Category();
$categories = $category->getAll();
$allProducts = $product->getAll();

// Фильтрация
$supplierFilter = $_GET['supplier'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFromFilter = $_GET['date_from'] ?? '';
$dateToFilter = $_GET['date_to'] ?? '';
$searchQuery = $_GET['search'] ?? '';

if ($supplierFilter) {
    $deliveries = array_filter($deliveries, fn($d) => $d['supplier_id'] == $supplierFilter);
}

if ($statusFilter) {
    $deliveries = array_filter($deliveries, fn($d) => $d['status'] == $statusFilter);
}

if ($dateFromFilter) {
    $deliveries = array_filter($deliveries, fn($d) => strtotime($d['order_date']) >= strtotime($dateFromFilter));
}

if ($dateToFilter) {
    $deliveries = array_filter($deliveries, fn($d) => strtotime($d['order_date']) <= strtotime($dateToFilter));
}

if ($searchQuery) {
    $deliveries = array_filter($deliveries, fn($d) =>
        stripos($d['delivery_code'], $searchQuery) !== false ||
        stripos($d['supplier_name'], $searchQuery) !== false
    );
}

$pageTitle = 'Управление поставками';
$breadcrumb = [
    ['title' => isAdminOrSupervisor() ? 'Панель управления' : 'Рабочая панель', 'url' => isAdminOrSupervisor() ? 'admin.php' : 'employee.php'],
    ['title' => 'Поставки']
];

include 'includes/header.php';
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
                <i class="bi bi-truck text-primary"></i>
                Управление поставками
            </h2>
            <p class="text-muted mb-0">Просмотр и управление поставками товаров</p>
        </div>
        <?php if (isAdminOrSupervisor()): ?>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeliveryModal">
                <i class="bi bi-plus-circle"></i> Создать поставку
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Статистика поставок -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($deliveries) ?></h4>
                    <p class="text-muted mb-0">Всего поставок</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($deliveries, fn($d) => $d['status'] == 'pending')) ?></h4>
                    <p class="text-muted mb-0">Ожидаются</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($deliveries, fn($d) => $d['status'] == 'in_transit')) ?></h4>
                    <p class="text-muted mb-0">В пути</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($deliveries, fn($d) => $d['status'] == 'completed')) ?></h4>
                    <p class="text-muted mb-0">Приняты</p>
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
                <div class="col-md-3">
                    <label for="search" class="form-label">Поиск</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="search" name="search"
                               value="<?= htmlspecialchars($searchQuery) ?>"
                               placeholder="Код поставки или поставщик">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="supplier" class="form-label">Поставщик</label>
                    <select class="form-select" id="supplier" name="supplier">
                        <option value="">Все поставщики</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>" <?= $supplierFilter == $sup['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sup['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Статус</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Все статусы</option>
                        <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Ожидается</option>
                        <option value="in_transit" <?= $statusFilter == 'in_transit' ? 'selected' : '' ?>>В пути</option>
                        <option value="completed" <?= $statusFilter == 'completed' ? 'selected' : '' ?>>Принята</option>
                        <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Отменена</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Дата с</label>
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFromFilter) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Дата по</label>
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="<?= htmlspecialchars($dateToFilter) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Таблица поставок -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list"></i> Список поставок
                <span class="badge bg-secondary"><?= count($deliveries) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="bi bi-upc"></i> Код поставки</th>
                            <th><i class="bi bi-building"></i> Поставщик</th>
                            <th><i class="bi bi-calendar"></i> Дата заказа</th>
                            <th><i class="bi bi-calendar-check"></i> Ожидаемая дата</th>
                            <th><i class="bi bi-speedometer2"></i> Статус</th>
                            <th><i class="bi bi-currency-exchange"></i> Сумма</th>
                            <?php if (isAdminOrSupervisor()): ?>
                            <th>Действия</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deliveries)): ?>
                        <tr>
                            <td colspan="<?= isAdminOrSupervisor() ? '7' : '6' ?>" class="text-center py-4">
                                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2">Поставки не найдены</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($deliveries as $del): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($del['delivery_code']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($del['supplier_name'] ?? 'Неизвестно') ?></strong>
                                    <?php if (!empty($del['contact_person'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($del['contact_person']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y', strtotime($del['order_date'])) ?></td>
                                <td><?= date('d.m.Y', strtotime($del['expected_date'])) ?></td>
                                <td>
                                    <?php
                                    $statusConfig = [
                                        'pending' => ['class' => 'bg-warning text-dark', 'icon' => 'clock', 'text' => 'Ожидается'],
                                        'in_transit' => ['class' => 'bg-info', 'icon' => 'truck', 'text' => 'В пути'],
                                        'completed' => ['class' => 'bg-success', 'icon' => 'check-circle', 'text' => 'Принята'],
                                        'cancelled' => ['class' => 'bg-danger', 'icon' => 'x-circle', 'text' => 'Отменена']
                                    ];
                                    $config = $statusConfig[$del['status']] ?? ['class' => 'bg-secondary', 'icon' => 'question', 'text' => $del['status']];
                                    ?>
                                    <span class="badge <?= $config['class'] ?>">
                                        <i class="bi bi-<?= $config['icon'] ?>"></i> <?= $config['text'] ?>
                                    </span>
                                </td>
                                <td><?= number_format($del['total_amount'], 2) ?> ₽</td>
                                <?php if (isAdminOrSupervisor()): ?>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary"
                                                onclick="editDelivery(<?= htmlspecialchars(json_encode($del)) ?>)"
                                                data-bs-toggle="modal" data-bs-target="#editDeliveryModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info"
                                                onclick="updateStatus(<?= $del['id'] ?>, '<?= htmlspecialchars($del['delivery_code']) ?>', '<?= $del['status'] ?>')"
                                                data-bs-toggle="modal" data-bs-target="#statusModal">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <?php if ($del['status'] != 'received'): ?>
                                        <button type="button" class="btn btn-outline-success"
                                                onclick="receiveDelivery(<?= $del['id'] ?>, '<?= htmlspecialchars($del['delivery_code']) ?>')">
                                            <i class="bi bi-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-danger"
                                                onclick="deleteDelivery(<?= $del['id'] ?>, '<?= htmlspecialchars($del['delivery_code']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (isAdminOrSupervisor()): ?>
<!-- Модальное окно добавления поставки -->
<div class="modal fade" id="addDeliveryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Создать поставку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_delivery">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="delivery_code" class="form-label">Код поставки *</label>
                                <input type="text" class="form-control" id="delivery_code" name="delivery_code"
                                       value="DEL-<?= date('Ymd') ?>-<?= str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) ?>" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="supplier_id" class="form-label">Поставщик *</label>
                                <select class="form-select" id="supplier_id" name="supplier_id" required>
                                    <option value="">Поставщик</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="order_date" class="form-label">Дата заказа *</label>
                                <input type="date" class="form-control" id="order_date" name="order_date"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="expected_date" class="form-label">Ожидаемая дата *</label>
                                <input type="date" class="form-control" id="expected_date" name="expected_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="total_amount" class="form-label">Общая сумма (₽) *</label>
                        <input type="number" class="form-control" id="total_amount" name="total_amount"
                               step="0.01" min="0" required>
                    </div>

                    <div class="form-group mb-3">
                        <label for="notes" class="form-label">Примечания</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="1000"
                                  placeholder="Дополнительная информация о поставке..."></textarea>
                    </div>

                    <!-- Секция товаров -->
                    <div class="form-group mb-3">
                        <label class="form-label">Товары в поставке</label>
                        <div id="products-container">
                            <div class="product-item border rounded p-3 mb-3">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label class="form-label">Выбрать существующий товар</label>
                                        <select class="form-select existing-product-select" name="products[0][existing_product_id]">
                                            <option value="">— Новый товар —</option>
                                            <?php foreach ($allProducts as $p): ?>
                                                <option value="<?= $p['id'] ?>"
                                                        data-code="<?= htmlspecialchars($p['product_code']) ?>"
                                                        data-name="<?= htmlspecialchars($p['name']) ?>"
                                                        data-category_id="<?= (int)$p['category_id'] ?>"
                                                        data-price="<?= (float)$p['price'] ?>"
                                                        data-unit="<?= htmlspecialchars($p['unit']) ?>"
                                                        data-min_stock_level="<?= (int)$p['min_stock_level'] ?>"
                                                        data-description="<?= htmlspecialchars($p['description']) ?>">
                                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Код товара *</label>
                                        <input type="text" class="form-control" name="products[0][product_code]" required maxlength="20">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Название *</label>
                                        <input type="text" class="form-control" name="products[0][product_name]" required maxlength="100">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Категория *</label>
                                        <select class="form-select" name="products[0][category_id]" required>
                                            <option value="">Категория</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Количество *</label>
                                        <input type="number" class="form-control" name="products[0][quantity_ordered]" min="1" required>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Цена за единицу *</label>
                                        <input type="number" class="form-control price-input" name="products[0][price]" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Единица измерения</label>
                                        <select class="form-select" name="products[0][unit]">
                                            <option value="шт">шт</option>
                                            <option value="кг">кг</option>
                                            <option value="г">г</option>
                                            <option value="л">л</option>
                                            <option value="мл">мл</option>
                                            <option value="м">м</option>
                                            <option value="см">см</option>
                                            <option value="м²">м²</option>
                                            <option value="м³">м³</option>
                                            <option value="упак">упак</option>
                                            <option value="компл">компл</option>
                                            <option value="набор">набор</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Минимальный остаток</label>
                                        <input type="number" class="form-control" name="products[0][min_stock_level]" value="10" min="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Описание</label>
                                        <input type="text" class="form-control" name="products[0][product_description]" placeholder="Описание товара">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <button type="button" class="btn btn-sm btn-danger remove-product">
                                            <i class="bi bi-trash"></i> Удалить товар
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-success" id="add-product">
                            <i class="bi bi-plus"></i> Добавить товар
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Создать поставку
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования поставки -->
<div class="modal fade" id="editDeliveryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Редактировать поставку</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editDeliveryForm" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_delivery">
                    <input type="hidden" name="delivery_id" id="edit_delivery_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_delivery_code" class="form-label">Код поставки *</label>
                                <input type="text" class="form-control" id="edit_delivery_code" name="delivery_code" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_supplier_id" class="form-label">Поставщик *</label>
                                <select class="form-select" id="edit_supplier_id" name="supplier_id" required>
                                    <option value="">Поставщик</option>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_order_date" class="form-label">Дата заказа *</label>
                                <input type="date" class="form-control" id="edit_order_date" name="order_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_expected_date" class="form-label">Ожидаемая дата *</label>
                                <input type="date" class="form-control" id="edit_expected_date" name="expected_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="edit_total_amount" class="form-label">Общая сумма (₽) *</label>
                        <input type="number" class="form-control" id="edit_total_amount" name="total_amount"
                               step="0.01" min="0" required>
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

<!-- Модальное окно изменения статуса -->
<div class="modal fade" id="statusModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Изменить статус</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="statusForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="delivery_id" id="status_delivery_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="alert alert-info">
                        <strong>Поставка:</strong> <span id="status_delivery_code"></span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="status_new_status" class="form-label">Новый статус *</label>
                        <select class="form-select" id="status_new_status" name="new_status" required>
                            <option value="pending">Ожидается</option>
                            <option value="in_transit">В пути</option>
                            <option value="completed">Принята</option>
                            <option value="cancelled">Отменена</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Изменить статус
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (isAdminOrSupervisor()): ?>
// Функция редактирования поставки
function editDelivery(delivery) {
    document.getElementById('edit_delivery_id').value = delivery.id;
    document.getElementById('edit_delivery_code').value = delivery.delivery_code;
    document.getElementById('edit_supplier_id').value = delivery.supplier_id;
    document.getElementById('edit_order_date').value = delivery.order_date;
    document.getElementById('edit_expected_date').value = delivery.expected_date;
    document.getElementById('edit_total_amount').value = delivery.total_amount;
    document.getElementById('edit_notes').value = delivery.notes || '';
}

// Функция изменения статуса
function updateStatus(deliveryId, deliveryCode, currentStatus) {
    document.getElementById('status_delivery_id').value = deliveryId;
    document.getElementById('status_delivery_code').textContent = deliveryCode;
    document.getElementById('status_new_status').value = currentStatus;
}

// Функция принятия поставки
function receiveDelivery(deliveryId, deliveryCode) {
    if (confirm('Подтвердить принятие поставки "' + deliveryCode + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="receive_delivery">
            <input type="hidden" name="delivery_id" value="${deliveryId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Функция удаления поставки
function deleteDelivery(deliveryId, deliveryCode) {
    if (confirm('Вы уверены, что хотите удалить поставку "' + deliveryCode + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_delivery">
            <input type="hidden" name="delivery_id" value="${deliveryId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
<?php endif; ?>

// Управление товарами в форме поставки
let productIndex = 1;

document.getElementById('add-product').addEventListener('click', function() {
    const container = document.getElementById('products-container');
    const newProduct = document.createElement('div');
    newProduct.className = 'product-item border rounded p-3 mb-3';
    newProduct.innerHTML = `
        <div class="row">
            <div class="col-md-12">
                <label class="form-label">Выбрать существующий товар</label>
                <select class="form-select existing-product-select" name="products[${productIndex}][existing_product_id]">
                    <option value="">— Новый товар —</option>
                    <?php foreach ($allProducts as $p): ?>
                        <option value="<?= $p['id'] ?>"
                                data-code="<?= htmlspecialchars($p['product_code']) ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-category_id="<?= (int)$p['category_id'] ?>"
                                data-price="<?= (float)$p['price'] ?>"
                                data-unit="<?= htmlspecialchars($p['unit']) ?>"
                                data-min_stock_level="<?= (int)$p['min_stock_level'] ?>"
                                data-description="<?= htmlspecialchars($p['description']) ?>">
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['product_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <label class="form-label">Код товара *</label>
                <input type="text" class="form-control" name="products[${productIndex}][product_code]" required maxlength="20">
            </div>
            <div class="col-md-3">
                <label class="form-label">Название *</label>
                <input type="text" class="form-control" name="products[${productIndex}][product_name]" required maxlength="100">
            </div>
            <div class="col-md-3">
                <label class="form-label">Категория *</label>
                <select class="form-select" name="products[${productIndex}][category_id]" required>
                    <option value="">Категория</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Количество *</label>
                <input type="number" class="form-control" name="products[${productIndex}][quantity_ordered]" min="1" required>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-3">
                <label class="form-label">Цена за единицу *</label>
                <input type="number" class="form-control price-input" name="products[${productIndex}][price]" step="0.01" min="0" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Единица измерения</label>
                <select class="form-select" name="products[${productIndex}][unit]">
                    <option value="шт">шт</option>
                    <option value="кг">кг</option>
                    <option value="г">г</option>
                    <option value="л">л</option>
                    <option value="мл">мл</option>
                    <option value="м">м</option>
                    <option value="см">см</option>
                    <option value="м²">м²</option>
                    <option value="м³">м³</option>
                    <option value="упак">упак</option>
                    <option value="компл">компл</option>
                    <option value="набор">набор</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Минимальный остаток</label>
                <input type="number" class="form-control" name="products[${productIndex}][min_stock_level]" value="10" min="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Описание</label>
                <input type="text" class="form-control" name="products[${productIndex}][product_description]" placeholder="Описание товара">
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-12">
                <button type="button" class="btn btn-sm btn-danger remove-product">
                    <i class="bi bi-trash"></i> Удалить товар
                </button>
            </div>
        </div>
    `;
    container.appendChild(newProduct);
    
    // Применяем валидацию к новым полям
    if (typeof formValidator !== 'undefined') {
        const newInputs = newProduct.querySelectorAll('input, textarea, select');
        newInputs.forEach(input => {
            // Проверяем, что валидация еще не применена
            if (!input.hasAttribute('data-validated')) {
                formValidator.setupInput(input);
                input.setAttribute('data-validated', 'true');
            }
        });
    }
    
    productIndex++;
    
    // Добавляем обработчик для удаления
    newProduct.querySelector('.remove-product').addEventListener('click', function() {
        newProduct.remove();
    });
    
    // Добавляем обработчик для пересчета суммы
    newProduct.querySelectorAll('.price-input').forEach(input => {
        input.addEventListener('input', calculateTotal);
    });
    
    // Обработчик выбора существующего товара
    const select = newProduct.querySelector('.existing-product-select');
    if (select) {
        select.addEventListener('change', onExistingProductChange);
    }
});

// Обработчики для удаления товаров
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-product')) {
        e.target.closest('.product-item').remove();
    }
});

// Пересчет общей суммы при изменении цен или количества
function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.product-item').forEach(item => {
        const price = parseFloat(item.querySelector('.price-input').value) || 0;
        const quantity = parseFloat(item.querySelector('input[name*="[quantity_ordered]"]').value) || 0;
        total += price * quantity;
    });
    document.getElementById('total_amount').value = total.toFixed(2);
}

// Добавляем обработчики для пересчета суммы
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('price-input') || e.target.name.includes('[quantity_ordered]')) {
        calculateTotal();
    }
});

// Автозаполнение полей при выборе существующего товара
function onExistingProductChange(e) {
    const select = e.target;
    const option = select.options[select.selectedIndex];
    const container = select.closest('.product-item');
    if (!option || !container) return;
    const code = option.getAttribute('data-code') || '';
    const name = option.getAttribute('data-name') || '';
    const categoryId = option.getAttribute('data-category_id') || '';
    const price = option.getAttribute('data-price') || '';
    const unit = option.getAttribute('data-unit') || '';
    const minStock = option.getAttribute('data-min_stock_level') || '';
    const description = option.getAttribute('data-description') || '';

    const codeInput = container.querySelector('input[name*="[product_code]"]');
    const nameInput = container.querySelector('input[name*="[product_name]"]');
    const categorySelect = container.querySelector('select[name*="[category_id]"]');
    const priceInput = container.querySelector('input[name*="[price]"]');
    const unitSelect = container.querySelector('select[name*="[unit]"]');
    const minStockInput = container.querySelector('input[name*="[min_stock_level]"]');
    const descInput = container.querySelector('input[name*="[product_description]"]');

    if (codeInput) codeInput.value = code;
    if (nameInput) nameInput.value = name;
    if (categorySelect && categoryId) categorySelect.value = categoryId;
    if (priceInput && price !== '') priceInput.value = price;
    if (unitSelect && unit) unitSelect.value = unit;
    if (minStockInput && minStock !== '') minStockInput.value = minStock;
    if (descInput) descInput.value = description;

    // Триггерим обновление счетчиков и валидации сразу
    [codeInput, nameInput, priceInput, unitSelect, minStockInput, descInput, categorySelect]
        .filter(Boolean)
        .forEach(el => {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });

    calculateTotal();
}

// Навешиваем обработчик на уже существующий селект в первой форме, если есть
document.querySelectorAll('.existing-product-select').forEach(sel => {
    sel.addEventListener('change', onExistingProductChange);
});
</script>

<?php include 'includes/footer.php'; ?>
