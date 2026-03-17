<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Product.php';
require_once 'classes/Delivery.php';
require_once 'classes/Supplier.php';

$auth = new Auth();
$auth->requireLogin();

// Только сотрудники могут видеть эту страницу
if ($auth->isAdminOrSupervisor()) {
    header('Location: admin.php');
    exit;
}

$product = new Product();
$delivery = new Delivery();
$supplier = new Supplier();

$error = '';
$success = '';

// Обработка форм
if ($_POST) {
    try {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Недействительный токен безопасности');
        }

        switch ($_POST['action']) {
            case 'update_stock':
                $productId = (int)$_POST['product_id'];
                $newQuantity = (int)$_POST['new_quantity'];
                
                // Валидация данных
                if ($productId <= 0) {
                    throw new Exception('Некорректный ID товара');
                }
                
                if ($newQuantity < 0 || $newQuantity > 999999) {
                    throw new Exception('Количество должно быть от 0 до 999,999');
                }

                $product->updateStock($productId, $newQuantity);
                $success = 'Остатки товара обновлены';
                break;

            case 'receive_delivery':
                $deliveryId = (int)$_POST['delivery_id'];
                
                // Валидация данных
                if ($deliveryId <= 0) {
                    throw new Exception('Некорректный ID поставки');
                }
                
                $delivery->markAsReceived($deliveryId);
                $success = 'Поставка помечена как принятая';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение данных
$products = $product->getAll();
$pendingDeliveries = $delivery->getAll(['status' => 'pending']);
$lowStockProducts = $product->getLowStockProducts();
$suppliers_list = $supplier->getAll();

// Статистика для сотрудника
$stats = [
    'total_products' => count($products),
    'low_stock_products' => count($lowStockProducts),
    'pending_deliveries' => count($pendingDeliveries),
    'in_stock_products' => count(array_filter($products, fn($p) => $p['quantity_in_stock'] > $p['min_stock_level']))
];

$pageTitle = 'Рабочая панель сотрудника';
$breadcrumb = [
    ['title' => 'Главная', 'url' => 'employee.php'],
    ['title' => 'Рабочая панель']
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

    <!-- Добро пожаловать -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h2 class="h4 mb-1">Добро пожаловать, <?= htmlspecialchars(getCurrentUser()['full_name']) ?>!</h2>
                <p class="text-muted mb-0">Рабочая панель сотрудника склада</p>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <a href="products.php" class="btn btn-primary">
                        <i class="bi bi-box-seam"></i> Товары
                    </a>
                    <a href="deliveries.php" class="btn btn-info">
                        <i class="bi bi-truck"></i> Поставки
                    </a>
                    <a href="suppliers.php" class="btn btn-success">
                        <i class="bi bi-building"></i> Поставщики
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['total_products'] ?></h4>
                    <p class="text-muted mb-0">Всего товаров</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['in_stock_products'] ?></h4>
                    <p class="text-muted mb-0">В наличии</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1 <?= $stats['low_stock_products'] > 0 ? 'text-warning' : '' ?>">
                        <?= $stats['low_stock_products'] ?>
                    </h4>
                    <p class="text-muted mb-0">Требует внимания</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['pending_deliveries'] ?></h4>
                    <p class="text-muted mb-0">Ожидают приемки</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Ожидающие поставки -->
        <div class="col-lg-8">
            <?php if (!empty($pendingDeliveries)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-clock text-warning me-2"></i>
                    <h5 class="mb-0">Ожидающие поставки</h5>
                    <span class="badge bg-warning ms-auto"><?= count($pendingDeliveries) ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-upc"></i> Код поставки</th>
                                    <th><i class="bi bi-building"></i> Поставщик</th>
                                    <th><i class="bi bi-calendar"></i> Дата заказа</th>
                                    <th><i class="bi bi-currency-exchange"></i> Сумма</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingDeliveries as $del): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($del['delivery_code']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($del['supplier_name'] ?? 'Неизвестно') ?></strong>
                                    </td>
                                    <td><?= date('d.m.Y', strtotime($del['order_date'])) ?></td>
                                    <td><?= number_format($del['total_amount'], 2) ?> ₽</td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm"
                                                onclick="receiveDelivery(<?= $del['id'] ?>, '<?= htmlspecialchars($del['delivery_code']) ?>')">
                                            <i class="bi bi-check"></i> Принять
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Поставки</h5>
                </div>
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h6 class="mt-3">Нет ожидающих поставок</h6>
                    <p class="text-muted">Все поставки обработаны</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Товары с низким остатком -->
            <?php if (!empty($lowStockProducts)): ?>
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    <h5 class="mb-0">Товары требуют пополнения</h5>
                    <span class="badge bg-warning ms-auto"><?= count($lowStockProducts) ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="bi bi-box-seam"></i> Товар</th>
                                    <th><i class="bi bi-bar-chart"></i> Остаток</th>
                                    <th><i class="bi bi-exclamation-circle"></i> Минимум</th>
                                    <th><i class="bi bi-currency-exchange"></i> Цена</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $prod): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($prod['product_code']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <?= $prod['quantity_in_stock'] ?> <?= htmlspecialchars($prod['unit']) ?>
                                        </span>
                                    </td>
                                    <td><?= $prod['min_stock_level'] ?> <?= htmlspecialchars($prod['unit']) ?></td>
                                    <td><?= number_format($prod['price'], 2) ?> ₽</td>
                                    <td>
                                        <button type="button" class="btn btn-outline-success btn-sm"
                                                onclick="updateStock(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>', <?= $prod['quantity_in_stock'] ?>, '<?= htmlspecialchars($prod['unit']) ?>')"
                                                data-bs-toggle="modal" data-bs-target="#updateStockModal">
                                            <i class="bi bi-arrow-repeat"></i> Обновить
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Состояние товаров</h5>
                </div>
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h6 class="mt-3">Все товары в достаточном количестве</h6>
                    <p class="text-muted">На складе нет товаров с критически низким остатком</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Быстрые действия -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Быстрые действия</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#updateStockModal">
                            <i class="bi bi-arrow-repeat"></i> Обновить остатки
                        </button>
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="bi bi-box-seam"></i> Просмотр товаров
                        </a>
                        <a href="deliveries.php" class="btn btn-outline-info">
                            <i class="bi bi-truck"></i> Просмотр поставок
                        </a>
                        <a href="suppliers.php" class="btn btn-outline-success">
                            <i class="bi bi-building"></i> Поставщики
                        </a>
                    </div>
                </div>
            </div>

            <!-- Последние обновления -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Сводка дня</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-box-seam text-primary me-2"></i>
                                <span>Товаров на складе</span>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= $stats['total_products'] ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-clock text-warning me-2"></i>
                                <span>Ожидают приемки</span>
                            </div>
                            <span class="badge bg-warning text-dark rounded-pill"><?= $stats['pending_deliveries'] ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                                <span>Критический остаток</span>
                            </div>
                            <span class="badge bg-danger rounded-pill"><?= $stats['low_stock_products'] ?></span>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-building text-info me-2"></i>
                                <span>Поставщиков</span>
                            </div>
                            <span class="badge bg-info rounded-pill"><?= count($suppliers_list) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно обновления остатков -->
<div class="modal fade" id="updateStockModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Обновить остатки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="updateStockForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="product_id" id="stock_product_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="form-group mb-3">
                        <label for="stock_product_select" class="form-label">Выберите товар *</label>
                        <select class="form-select" id="stock_product_select" name="product_id" required onchange="updateStockInfo()">
                            <option value="">Выберите товар</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id'] ?>"
                                        data-name="<?= htmlspecialchars($prod['name']) ?>"
                                        data-current="<?= $prod['quantity_in_stock'] ?>"
                                        data-unit="<?= htmlspecialchars($prod['unit']) ?>">
                                    <?= htmlspecialchars($prod['name']) ?>
                                    (<?= $prod['quantity_in_stock'] ?> <?= htmlspecialchars($prod['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="stock_info" class="alert alert-info" style="display: none;">
                        <strong>Товар:</strong> <span id="stock_product_name"></span><br>
                        <strong>Текущий остаток:</strong> <span id="stock_current_quantity"></span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="stock_new_quantity" class="form-label">Новое количество *</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="stock_new_quantity" name="new_quantity"
                                   min="0" required>
                            <span class="input-group-text" id="stock_unit">шт</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Обновить остатки
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Функция обновления остатков
function updateStock(productId, productName, currentQuantity, unit) {
    document.getElementById('stock_product_id').value = productId;
    document.getElementById('stock_product_select').value = productId;
    document.getElementById('stock_product_name').textContent = productName;
    document.getElementById('stock_current_quantity').textContent = currentQuantity + ' ' + unit;
    document.getElementById('stock_new_quantity').value = currentQuantity;
    document.getElementById('stock_unit').textContent = unit;
    document.getElementById('stock_info').style.display = 'block';
}

// Функция обновления информации о товаре при выборе
function updateStockInfo() {
    const select = document.getElementById('stock_product_select');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.value) {
        const productName = selectedOption.getAttribute('data-name');
        const currentQuantity = selectedOption.getAttribute('data-current');
        const unit = selectedOption.getAttribute('data-unit');

        document.getElementById('stock_product_name').textContent = productName;
        document.getElementById('stock_current_quantity').textContent = currentQuantity + ' ' + unit;
        document.getElementById('stock_new_quantity').value = currentQuantity;
        document.getElementById('stock_unit').textContent = unit;
        document.getElementById('stock_info').style.display = 'block';
    } else {
        document.getElementById('stock_info').style.display = 'none';
    }
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
</script>

<?php include 'includes/footer.php'; ?>

