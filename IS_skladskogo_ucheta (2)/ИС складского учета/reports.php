<?php
require_once 'includes/config.php';

require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Product.php';
require_once 'classes/Delivery.php';
require_once 'classes/Supplier.php';
require_once 'classes/Category.php';
require_once 'classes/ExcelReport.php';

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
$auth->requireAdminOrSupervisor(); // Отчеты для администраторов и руководителей

$product = new Product();
$delivery = new Delivery();
$supplier = new Supplier();
$category = new Category();
$excelReport = new ExcelReport();

$error = '';
$success = '';

// Обработка запросов на генерацию отчетов
if ($_POST) {
    try {
        // Проверка CSRF токена
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!verifyCSRFToken($csrfToken)) {
            throw new Exception('Недействительный токен безопасности');
        }

        switch ($_POST['action']) {
            case 'generate_stock_report':
                // Валидация дат
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                
                if (!empty($dateFrom) && !validateDate($dateFrom)) {
                    throw new Exception('Некорректная дата "с"');
                }
                
                if (!empty($dateTo) && !validateDate($dateTo)) {
                    throw new Exception('Некорректная дата "по"');
                }
                
                if (!empty($dateFrom) && !empty($dateTo) && $dateFrom > $dateTo) {
                    throw new Exception('Дата "с" не может быть больше даты "по"');
                }
                
                $filters = [
                    'category_id' => $_POST['category_id'] ?? '',
                    'stock_status' => $_POST['stock_status'] ?? '',
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ];

                $excelReport->generateStockReport($filters);
                exit;

            case 'generate_delivery_report':
                // Валидация дат
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                
                if (!empty($dateFrom) && !validateDate($dateFrom)) {
                    throw new Exception('Некорректная дата "с"');
                }
                
                if (!empty($dateTo) && !validateDate($dateTo)) {
                    throw new Exception('Некорректная дата "по"');
                }
                
                if (!empty($dateFrom) && !empty($dateTo) && $dateFrom > $dateTo) {
                    throw new Exception('Дата "с" не может быть больше даты "по"');
                }
                
                $filters = [
                    'supplier_id' => $_POST['supplier_id'] ?? '',
                    'status' => $_POST['status'] ?? '',
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ];

                $excelReport->generateDeliveryReport($filters);
                exit;

            case 'generate_supplier_report':
                // Валидация дат
                $dateFrom = $_POST['date_from'] ?? '';
                $dateTo = $_POST['date_to'] ?? '';
                
                if (!empty($dateFrom) && !validateDate($dateFrom)) {
                    throw new Exception('Некорректная дата "с"');
                }
                
                if (!empty($dateTo) && !validateDate($dateTo)) {
                    throw new Exception('Некорректная дата "по"');
                }
                
                if (!empty($dateFrom) && !empty($dateTo) && $dateFrom > $dateTo) {
                    throw new Exception('Дата "с" не может быть больше даты "по"');
                }
                
                $filters = [
                    'active_only' => isset($_POST['active_only']),
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo
                ];

                $excelReport->generateSupplierReport($filters);
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получаем данные для отчетов
$categories = $category->getAll();
$suppliers = $supplier->getAll();
$products = $product->getAll();
$deliveries = $delivery->getAll();

// Получаем статистику
$stats = [
    'total_products' => count($products),
    'low_stock_products' => count($product->getLowStockProducts()),
    'total_suppliers' => count($suppliers),
    'pending_deliveries' => count(array_filter($deliveries, fn($d) => $d['status'] == 'pending')),
    'total_deliveries' => count($deliveries),
    'total_value' => array_sum(array_column($deliveries, 'total_amount'))
];

$pageTitle = 'Отчеты и аналитика';
$breadcrumb = [
    ['title' => 'Панель управления', 'url' => 'admin.php'],
    ['title' => 'Отчеты']
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

    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1">
                <i class="bi bi-graph-up text-primary"></i>
                Отчеты и аналитика
            </h2>
            <p class="text-muted mb-0">Аналитические отчеты и статистика по складу</p>
        </div>
    </div>

    <!-- Общая статистика -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['total_products'] ?></h4>
                    <p class="text-muted mb-0">Товаров</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['low_stock_products'] ?></h4>
                    <p class="text-muted mb-0">Критический остаток</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-building text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['total_suppliers'] ?></h4>
                    <p class="text-muted mb-0">Поставщиков</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-truck text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['total_deliveries'] ?></h4>
                    <p class="text-muted mb-0">Поставок</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary bg-opacity-10 border-secondary">
                <div class="card-body text-center">
                    <i class="bi bi-clock text-secondary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= $stats['pending_deliveries'] ?></h4>
                    <p class="text-muted mb-0">Ожидают</p>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-dark bg-opacity-10 border-dark">
                <div class="card-body text-center">
                    <i class="bi bi-currency-exchange text-dark" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= number_format($stats['total_value'], 0) ?> ₽</h4>
                    <p class="text-muted mb-0">Оборот</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Генерация отчетов -->
    <div class="row">
        <!-- Отчет по остаткам -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-box-seam text-primary"></i>
                        Отчет по остаткам
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Подробная информация о количестве товаров на складе, их стоимости и статусе остатков.</p>

                    <form method="POST" target="_blank">
                        <input type="hidden" name="action" value="generate_stock_report">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="form-group mb-3">
                            <label for="stock_category_id" class="form-label">Категория</label>
                            <select class="form-select" id="stock_category_id" name="category_id">
                                <option value="">Все категории</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="stock_status" class="form-label">Статус остатков</label>
                            <select class="form-select" id="stock_status" name="stock_status">
                                <option value="">Все товары</option>
                                <option value="low">Низкие остатки</option>
                                <option value="normal">Нормальные остатки</option>
                                <option value="out">Закончились</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="stock_date_from" class="form-label">Дата с</label>
                                    <input type="date" class="form-control" id="stock_date_from" name="date_from">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="stock_date_to" class="form-label">Дата по</label>
                                    <input type="date" class="form-control" id="stock_date_to" name="date_to">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Сформировать Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Отчет по поставкам -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-truck text-info"></i>
                        Отчет по поставкам
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Информация о всех поставках, их статусах, суммах и поставщиках за выбранный период.</p>

                    <form method="POST" target="_blank">
                        <input type="hidden" name="action" value="generate_delivery_report">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="form-group mb-3">
                            <label for="delivery_supplier_id" class="form-label">Поставщик</label>
                            <select class="form-select" id="delivery_supplier_id" name="supplier_id">
                                <option value="">Все поставщики</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label for="delivery_status" class="form-label">Статус</label>
                            <select class="form-select" id="delivery_status" name="status">
                                <option value="">Все статусы</option>
                                <option value="pending">Ожидается</option>
                                <option value="in_transit">В пути</option>
                                <option value="completed">Принята</option>
                                <option value="cancelled">Отменена</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="delivery_date_from" class="form-label">Дата с</label>
                                    <input type="date" class="form-control" id="delivery_date_from" name="date_from"
                                           value="<?= date('Y-m-01') ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="delivery_date_to" class="form-label">Дата по</label>
                                    <input type="date" class="form-control" id="delivery_date_to" name="date_to"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Сформировать Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Отчет по поставщикам -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-building text-success"></i>
                        Отчет по поставщикам
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Анализ работы с поставщиками: количество поставок, общие суммы, надежность.</p>

                    <form method="POST" target="_blank">
                        <input type="hidden" name="action" value="generate_supplier_report">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                        <div class="form-group mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="active_only" name="active_only" value="1" checked>
                                <label class="form-check-label" for="active_only">
                                    Только активные поставщики
                                </label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="supplier_date_from" class="form-label">Период с</label>
                                    <input type="date" class="form-control" id="supplier_date_from" name="date_from"
                                           value="<?= date('Y-01-01') ?>">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group mb-3">
                                    <label for="supplier_date_to" class="form-label">Период по</label>
                                    <input type="date" class="form-control" id="supplier_date_to" name="date_to"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-excel"></i> Сформировать Excel
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Топ товары и поставщики -->
    <div class="row">
        <!-- Топ товары -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy text-warning"></i>
                        Топ товары по остаткам
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Сортируем товары по остаткам
                    $topProducts = $products;
                    usort($topProducts, fn($a, $b) => $b['quantity_in_stock'] <=> $a['quantity_in_stock']);
                    $topProducts = array_slice($topProducts, 0, 10);
                    ?>

                    <?php if (!empty($topProducts)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Место</th>
                                        <th>Товар</th>
                                        <th>Остаток</th>
                                        <th>Цена</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $index => $prod): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $medals = ['🥇', '🥈', '🥉'];
                                            echo $medals[$index] ?? ($index + 1);
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($prod['product_code']) ?></small>
                                        </td>
                                        <td><?= $prod['quantity_in_stock'] ?> <?= htmlspecialchars($prod['unit']) ?></td>
                                        <td><?= number_format($prod['price'], 2) ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">Нет данных по товарам</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Топ поставщики -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-award text-warning"></i>
                        Топ поставщики по поставкам
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    // Подсчитываем количество поставок для каждого поставщика
                    $supplierStats = [];
                    foreach ($suppliers as $sup) {
                        $supplierDeliveries = array_filter($deliveries, fn($d) => $d['supplier_id'] == $sup['id']);
                        $supplierStats[] = [
                            'name' => $sup['name'] ?? 'Без названия',
                            'deliveries_count' => count($supplierDeliveries),
                            'total_amount' => array_sum(array_column($supplierDeliveries, 'total_amount'))
                        ];
                    }

                    // Сортируем по количеству поставок
                    usort($supplierStats, fn($a, $b) => $b['deliveries_count'] <=> $a['deliveries_count']);
                    $topSuppliers = array_slice($supplierStats, 0, 10);
                    ?>

                    <?php if (!empty($topSuppliers)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Место</th>
                                        <th>Поставщик</th>
                                        <th>Поставок</th>
                                        <th>Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topSuppliers as $index => $sup): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $medals = ['🥇', '🥈', '🥉'];
                                            echo $medals[$index] ?? ($index + 1);
                                            ?>
                                        </td>
                                        <td><?= htmlspecialchars($sup['name'] ?? 'Без названия') ?></td>
                                        <td><?= $sup['deliveries_count'] ?></td>
                                        <td><?= number_format($sup['total_amount'], 2) ?> ₽</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2">Нет данных по поставщикам</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
