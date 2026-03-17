<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Product.php';
require_once 'classes/Supplier.php';
require_once 'classes/Delivery.php';
require_once 'classes/Category.php';

$auth = new Auth();
$auth->requireAdminOrSupervisor();

$product = new Product();
$supplier = new Supplier();
$delivery = new Delivery();
$category = new Category();

$error = '';
$success = '';

// Получаем статистику
$stats = [
    'total_products' => count($product->getAll()),
    'low_stock_products' => count($product->getLowStockProducts()),
    'total_suppliers' => count($supplier->getAll()),
    'pending_deliveries' => count($delivery->getAll(['status' => 'pending']))
];

// Обработка форм
if ($_POST) {
    try {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Недействительный токен безопасности');
        }

        switch ($_POST['action']) {
            case 'add_product':
                // Валидация данных товара
                $productCode = trim($_POST['product_code']);
                if (empty($productCode) || strlen($productCode) > 20) {
                    throw new Exception('Код товара должен быть от 1 до 20 символов');
                }
                
                $productName = trim($_POST['product_name']);
                if (empty($productName) || strlen($productName) > 100) {
                    throw new Exception('Название товара должно быть от 1 до 100 символов');
                }
                
                $price = (float)$_POST['product_price'];
                if ($price < 0 || $price > 999999999) {
                    throw new Exception('Цена должна быть от 0 до 999,999,999');
                }
                
                $quantity = (int)$_POST['quantity_in_stock'];
                if ($quantity < 0 || $quantity > 999999) {
                    throw new Exception('Количество должно быть от 0 до 999,999');
                }
                
                $minStock = (int)$_POST['min_stock_level'];
                if ($minStock < 0 || $minStock > 999999) {
                    throw new Exception('Минимальный остаток должен быть от 0 до 999,999');
                }
                
                $unit = trim($_POST['unit']);
                if (strlen($unit) > 20) {
                    throw new Exception('Единица измерения не должна превышать 20 символов');
                }

                $productData = [
                    'product_code' => $productCode,
                    'name' => $productName,
                    'description' => trim($_POST['product_description']),
                    'category_id' => (int)$_POST['category_id'],
                    'price' => $price,
                    'quantity_in_stock' => $quantity,
                    'min_stock_level' => $minStock,
                    'unit' => $unit
                ];

                $product->create($productData);
                $success = 'Товар успешно добавлен';
                break;

            case 'delete_product':
                $productId = (int)$_POST['product_id'];
                $product->delete($productId);
                $success = 'Товар успешно удален';
                break;

            case 'add_supplier':
                $supplierData = [
                    'name' => trim($_POST['supplier_name']),
                    'contact_person' => trim($_POST['contact_person']),
                    'email' => trim($_POST['email']),
                    'phone' => trim($_POST['phone']),
                    'address' => trim($_POST['address'])
                ];

                $supplier->create($supplierData);
                $success = 'Поставщик успешно добавлен';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получаем данные для отображения
$products = $product->getAll();
$suppliers = $supplier->getAll();
$categories = $category->getAll();
$lowStockProducts = $product->getLowStockProducts();
$recentDeliveries = $delivery->getAll();
$recentDeliveries = array_slice($recentDeliveries, 0, 5); // Последние 5 поставок

$pageTitle = 'Панель администратора';
$breadcrumb = [
    ['title' => 'Главная', 'url' => 'admin.php'],
    ['title' => 'Панель администратора']
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
                <p class="text-muted mb-0">Обзор системы склада и быстрый доступ к основным функциям</p>
            </div>
            <div class="col-auto">
                <div class="btn-group" role="group">
                    <a href="products.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить товар
                    </a>
                    <a href="deliveries.php" class="btn btn-info">
                        <i class="bi bi-truck"></i> Новая поставка
                    </a>
                    <a href="reports.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-bar-graph"></i> Отчеты
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Статистика -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-number"><?= $stats['total_products'] ?></div>
            <div class="stat-label">Всего товаров</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-exclamation-triangle text-warning"></i>
            </div>
            <div class="stat-number <?= $stats['low_stock_products'] > 0 ? 'text-warning' : '' ?>">
                <?= $stats['low_stock_products'] ?>
            </div>
            <div class="stat-label">Товаров на исходе</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-number"><?= $stats['total_suppliers'] ?></div>
            <div class="stat-label">Поставщиков</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-number"><?= $stats['pending_deliveries'] ?></div>
            <div class="stat-label">Ожидающих поставок</div>
        </div>
    </div>

    <div class="row">
        <!-- Товары с низким остатком -->
        <div class="col-lg-8">
            <?php if (!empty($lowStockProducts)): ?>
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    <h5 class="mb-0">Товары с низким остатком</h5>
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
                                        <div class="btn-group btn-group-sm">
                                            <a href="products.php?edit=<?= $prod['id'] ?>" class="btn btn-outline-primary" title="Редактировать">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="deliveries.php?product=<?= $prod['id'] ?>" class="btn btn-outline-success" title="Заказать">
                                                <i class="bi bi-cart-plus"></i>
                                            </a>
                                        </div>
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
                    <h5 class="mb-0"><i class="bi bi-check-circle text-success"></i> Статус товаров</h5>
                </div>
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h6 class="mt-3">Все товары в достаточном количестве</h6>
                    <p class="text-muted">На складе нет товаров с критически низким остатком</p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Последние поставки -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <i class="bi bi-truck me-2"></i>
                    <h5 class="mb-0">Последние поставки</h5>
                    <a href="deliveries.php" class="btn btn-sm btn-outline-primary ms-auto">
                        Все поставки <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
    <?php if (!empty($recentDeliveries)): ?>
        <div class="list-group list-group-flush">
            <?php foreach ($recentDeliveries as $del): ?>
            <div class="list-group-item px-0">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h6 class="mb-1"><?= htmlspecialchars($del['delivery_code']) ?></h6>
                        <p class="mb-1 text-muted small">
                            <i class="bi bi-building"></i> <?= htmlspecialchars($del['supplier_name']) ?>
                        </p>
                        <small class="text-muted">
                            <i class="bi bi-calendar"></i> <?= date('d.m.Y', strtotime($del['order_date'])) ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-<?= 
                            $del['status'] === 'completed' ? 'success' : 
                            ($del['status'] === 'pending' ? 'warning' : 
                            ($del['status'] === 'in_transit' ? 'info' : 
                            ($del['status'] === 'cancelled' ? 'danger' : 'secondary'))) 
                        ?>">
                            <?= 
                                $del['status'] === 'pending' ? 'Ожидается' : 
                                ($del['status'] === 'completed' ? 'Принята' : 
                                ($del['status'] === 'in_transit' ? 'В пути' : 
                                ($del['status'] === 'cancelled' ? 'Отменена' : htmlspecialchars($del['status'])))) 
                            ?>
                        </span>
                        <div class="text-muted small">
                            <?= number_format($del['total_amount'], 2) ?> ₽
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-4">
            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
            <p class="text-muted mt-2">Нет поставок</p>
        </div>
    <?php endif; ?>
</div>
            </div>
        </div>
    </div>

    <!-- Быстрые действия -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Быстрые действия</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="products.php" class="d-block text-decoration-none">
                                <div class="p-3 bg-primary bg-opacity-10 rounded text-center h-100">
                                    <i class="bi bi-box-seam text-primary" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Управление товарами</h6>
                                    <p class="text-muted small mb-0">Добавить, редактировать товары</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="deliveries.php" class="d-block text-decoration-none">
                                <div class="p-3 bg-info bg-opacity-10 rounded text-center h-100">
                                    <i class="bi bi-truck text-info" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Управление поставками</h6>
                                    <p class="text-muted small mb-0">Создать новую поставку</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="suppliers.php" class="d-block text-decoration-none">
                                <div class="p-3 bg-success bg-opacity-10 rounded text-center h-100">
                                    <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Управление поставщиками</h6>
                                    <p class="text-muted small mb-0">Добавить поставщика</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="reports.php" class="d-block text-decoration-none">
                                <div class="p-3 bg-warning bg-opacity-10 rounded text-center h-100">
                                    <i class="bi bi-file-earmark-bar-graph text-warning" style="font-size: 2rem;"></i>
                                    <h6 class="mt-2">Отчеты</h6>
                                    <p class="text-muted small mb-0">Аналитика и отчеты</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
