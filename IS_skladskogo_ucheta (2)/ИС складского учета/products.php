<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';
require_once 'classes/Product.php';
require_once 'classes/Category.php';

$auth = new Auth();
$auth->requireLogin();

$product = new Product();
$category = new Category();

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
            case 'add_product':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для добавления товаров');
                }
                
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

            case 'edit_product':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для редактирования товаров');
                }
                
                $productId = (int)$_POST['product_id'];
                $productData = [
                    'product_code' => trim($_POST['product_code']),
                    'name' => trim($_POST['product_name']),
                    'description' => trim($_POST['product_description']),
                    'category_id' => (int)$_POST['category_id'],
                    'price' => (float)$_POST['product_price'],
                    'quantity_in_stock' => (int)$_POST['quantity_in_stock'],
                    'min_stock_level' => (int)$_POST['min_stock_level'],
                    'unit' => trim($_POST['unit'])
                ];

                $product->update($productId, $productData);
                $success = 'Товар успешно обновлен';
                break;

            case 'delete_product':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для удаления товаров');
                }
                
                $productId = (int)$_POST['product_id'];
                $product->delete($productId);
                $success = 'Товар успешно удален';
                break;

            case 'update_stock':
                $productId = (int)$_POST['product_id'];
                $newQuantity = (int)$_POST['new_quantity'];
                
                $product->updateStock($productId, $newQuantity);
                $success = 'Остатки товара обновлены';
                break;

            // Новые обработчики для категорий
            case 'add_category':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для добавления категорий');
                }
                
                $categoryData = [
                    'name' => trim($_POST['category_name']),
                    'description' => trim($_POST['category_description'])
                ];

                $category->create($categoryData);
                $success = 'Категория успешно добавлена';
                break;

            case 'edit_category':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для редактирования категорий');
                }
                
                $categoryId = (int)$_POST['category_id'];
                $categoryData = [
                    'name' => trim($_POST['category_name']),
                    'description' => trim($_POST['category_description'])
                ];

                $category->update($categoryId, $categoryData);
                $success = 'Категория успешно обновлена';
                break;

            case 'delete_category':
                if (!isAdminOrSupervisor()) {
                    throw new Exception('Нет прав для удаления категорий');
                }
                
                $categoryId = (int)$_POST['category_id'];
                $category->delete($categoryId);
                $success = 'Категория успешно удалена';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение данных
$products = $product->getAll();
$categories = $category->getAll();
$lowStockProducts = $product->getLowStockProducts();

// Фильтрация
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';
$searchQuery = $_GET['search'] ?? '';

if ($categoryFilter) {
    $products = array_filter($products, fn($p) => $p['category_id'] == $categoryFilter);
}

if ($stockFilter) {
    switch ($stockFilter) {
        case 'low':
            $products = array_filter($products, fn($p) => $p['quantity_in_stock'] <= $p['min_stock_level']);
            break;
        case 'normal':
            $products = array_filter($products, fn($p) => $p['quantity_in_stock'] > $p['min_stock_level']);
            break;
    }
}

if ($searchQuery) {
    $products = array_filter($products, fn($p) => 
        stripos($p['name'], $searchQuery) !== false || 
        stripos($p['product_code'], $searchQuery) !== false
    );
}

$pageTitle = 'Управление товарами';
$breadcrumb = [
    ['title' => isAdminOrSupervisor() ? 'Панель управления' : 'Рабочая панель', 'url' => isAdminOrSupervisor() ? 'admin.php' : 'employee.php'],
    ['title' => 'Товары']
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
                <i class="bi bi-box-seam text-primary"></i>
                Управление товарами
            </h2>
            <p class="text-muted mb-0">Просмотр и управление товарами на складе</p>
        </div>
        <?php if (isAdminOrSupervisor()): ?>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal">
                <i class="bi bi-tags"></i> Управление категориями
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-circle"></i> Добавить товар
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Статистика товаров -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-box-seam text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($product->getAll()) ?></h4>
                    <p class="text-muted mb-0">Всего товаров</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($product->getAll(), fn($p) => $p['quantity_in_stock'] > $p['min_stock_level'])) ?></h4>
                    <p class="text-muted mb-0">В наличии</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($lowStockProducts) ?></h4>
                    <p class="text-muted mb-0">Критический остаток</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-tags text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($categories) ?></h4>
                    <p class="text-muted mb-0">Категорий</p>
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
                               placeholder="Название или код товара">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Категория</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="stock" class="form-label">Остатки</label>
                    <select class="form-select" id="stock" name="stock">
                        <option value="">Все товары</option>
                        <option value="low" <?= $stockFilter == 'low' ? 'selected' : '' ?>>Критический остаток</option>
                        <option value="normal" <?= $stockFilter == 'normal' ? 'selected' : '' ?>>В наличии</option>
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

    <!-- Таблица товаров -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list"></i> Список товаров 
                <span class="badge bg-secondary"><?= count($products) ?></span>
            </h5>
            <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="view-mode" id="table-view" checked>
                <label class="btn btn-outline-secondary" for="table-view">
                    <i class="bi bi-table"></i>
                </label>
                <input type="radio" class="btn-check" name="view-mode" id="card-view">
                <label class="btn btn-outline-secondary" for="card-view">
                    <i class="bi bi-grid-3x3"></i>
                </label>
            </div>
        </div>
        <div class="card-body">
            <!-- Представление в виде таблицы -->
            <div id="table-view-content">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                 <th><i class="bi bi-image"></i> Изображение</th>
                                <th><i class="bi bi-upc"></i> Код</th>
                                <th><i class="bi bi-box-seam"></i> Название</th>
                                <th><i class="bi bi-tag"></i> Категория</th>
                                <th><i class="bi bi-bar-chart"></i> Остаток</th>
                                <th><i class="bi bi-currency-exchange"></i> Цена</th>
                                <th><i class="bi bi-speedometer2"></i> Статус</th>
                                <?php if (isAdminOrSupervisor()): ?>
                                <th>Действия</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="<?= isAdminOrSupervisor() ? '8' : '7' ?>" class="text-center py-4">
                                    <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2">Товары не найдены</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($products as $prod): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($prod['image_path']) && file_exists($prod['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($prod['image_path']) ?>" 
                                                 alt="<?= htmlspecialchars($prod['name']) ?>" 
                                                 class="img-thumbnail" 
                                                 style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px; border-radius: 4px;">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($prod['product_code']) ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($prod['name']) ?></strong>
                                        <?php if (!empty($prod['description'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($prod['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?= htmlspecialchars($prod['category_name'] ?? 'Без категории') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold"><?= $prod['quantity_in_stock'] ?></span> 
                                        <?= htmlspecialchars($prod['unit']) ?>
                                        <br>
                                        <small class="text-muted">мин: <?= $prod['min_stock_level'] ?></small>
                                    </td>
                                    <td><?= number_format($prod['price'], 2) ?> ₽</td>
                                    <td>
                                        <?php 
                                        // Проверяем статус поставки
                                        if (isset($prod['order_status']) && $prod['order_status'] !== 'in_stock'): 
                                            switch ($prod['order_status']):
                                                case 'ordered': ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-clock"></i> Заказан
                                                    </span>
                                                    <?php break;
                                                case 'in_transit': ?>
                                                    <span class="badge bg-primary">
                                                        <i class="bi bi-truck"></i> В пути
                                                    </span>
                                                    <?php break;
                                                case 'received': ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-box-seam"></i> Получен
                                                    </span>
                                                    <?php break;
                                            endswitch;
                                        else:
                                            // Обычная логика для товаров на складе
                                            if ($prod['quantity_in_stock'] <= $prod['min_stock_level']): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-exclamation-triangle"></i> Критический
                                                </span>
                                            <?php elseif ($prod['quantity_in_stock'] <= $prod['min_stock_level'] * 1.5): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-exclamation-circle"></i> Низкий
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> В наличии
                                                </span>
                                            <?php endif;
                                        endif; ?>
                                    </td>
                                    <?php if (isAdminOrSupervisor()): ?>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editProduct(<?= htmlspecialchars(json_encode($prod)) ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#editProductModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success"
                                                    onclick="updateStock(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>', <?= $prod['quantity_in_stock'] ?>, '<?= htmlspecialchars($prod['unit']) ?>')"
                                                    data-bs-toggle="modal" data-bs-target="#updateStockModal">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="deleteProduct(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>')">
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

            <!-- Представление в виде карточек -->
            <div id="card-view-content" style="display: none;">
                <div class="row">
                    <?php foreach ($products as $prod): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                             <?php if (!empty($prod['image_path']) && file_exists($prod['image_path'])): ?>
                                 <img src="<?= htmlspecialchars($prod['image_path']) ?>" 
                                      alt="<?= htmlspecialchars($prod['name']) ?>" 
                                      class="card-img-top" 
                                      style="height: 200px; object-fit: cover;">
                             <?php endif; ?>
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <code class="text-muted"><?= htmlspecialchars($prod['product_code']) ?></code>
                                <?php 
                                // Проверяем статус поставки
                                if (isset($prod['order_status']) && $prod['order_status'] !== 'in_stock'): 
                                    switch ($prod['order_status']):
                                        case 'ordered': ?>
                                            <span class="badge bg-info">Заказан</span>
                                            <?php break;
                                        case 'in_transit': ?>
                                            <span class="badge bg-primary">В пути</span>
                                            <?php break;
                                        case 'received': ?>
                                            <span class="badge bg-warning">Получен</span>
                                            <?php break;
                                    endswitch;
                                else:
                                    // Обычная логика для товаров на складе
                                    if ($prod['quantity_in_stock'] <= $prod['min_stock_level']): ?>
                                        <span class="badge bg-danger">Критический</span>
                                    <?php elseif ($prod['quantity_in_stock'] <= $prod['min_stock_level'] * 1.5): ?>
                                        <span class="badge bg-warning text-dark">Низкий</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">В наличии</span>
                                    <?php endif;
                                endif; ?>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($prod['name']) ?></h6>
                                <p class="card-text">
                                    <span class="badge bg-light text-dark mb-2">
                                        <?= htmlspecialchars($prod['category_name'] ?? 'Без категории') ?>
                                    </span>
                                </p>
                                <?php if (!empty($prod['description'])): ?>
                                    <p class="card-text text-muted small"><?= htmlspecialchars($prod['description']) ?></p>
                                <?php endif; ?>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h5 class="mb-0"><?= $prod['quantity_in_stock'] ?></h5>
                                            <small class="text-muted"><?= htmlspecialchars($prod['unit']) ?></small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="mb-0"><?= number_format($prod['price'], 2) ?> ₽</h5>
                                        <small class="text-muted">цена</small>
                                    </div>
                                </div>
                            </div>
                            <?php if (isAdminOrSupervisor()): ?>
                            <div class="card-footer">
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                            onclick="editProduct(<?= htmlspecialchars(json_encode($prod)) ?>)"
                                            data-bs-toggle="modal" data-bs-target="#editProductModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm"
                                            onclick="updateStock(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>', <?= $prod['quantity_in_stock'] ?>, '<?= htmlspecialchars($prod['unit']) ?>')"
                                            data-bs-toggle="modal" data-bs-target="#updateStockModal">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm"
                                            onclick="deleteProduct(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['name']) ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isAdminOrSupervisor()): ?>
<!-- Модальное окно управления категориями -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-tags"></i> Управление категориями</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Кнопка добавления категории -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6>Список категорий</h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-circle"></i> Добавить категорию
                    </button>
                </div>

                <!-- Таблица категорий -->
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Товаров</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-primary"><?= $cat['products_count'] ?? 0 ?></span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary"
                                                onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)"
                                                data-bs-toggle="modal" data-bs-target="#editCategoryModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger"
                                                onclick="deleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно добавления категории -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Добавить категорию</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group mb-3">
                        <label for="category_name" class="form-label">Название категории *</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required maxlength="50">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="category_description" class="form-label">Описание</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Добавить категорию
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования категории -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Редактировать категорию</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editCategoryForm" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-group mb-3">
                        <label for="edit_category_name" class="form-label">Название категории *</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required maxlength="50">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="edit_category_description" class="form-label">Описание</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
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

<!-- Остальные модальные окна (товары) остаются без изменений -->
<!-- Модальное окно добавления товара -->
<div class="modal fade" id="addProductModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Добавить товар</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="product_code" class="form-label">Код товара</label>
                            <input type="text" class="form-control" id="product_code" name="product_code" required maxlength="20">
                        </div>
                        <div class="form-group">
                            <label for="product_name" class="form-label">Название товара</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required maxlength="100">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_description" class="form-label">Описание</label>
                        <textarea class="form-control" id="product_description" name="product_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Категория</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">Категория</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_price" class="form-label">Цена</label>
                        <input type="number" class="form-control" id="product_price" name="product_price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="quantity_in_stock" class="form-label">Количество на складе</label>
                        <input type="number" class="form-control" id="quantity_in_stock" name="quantity_in_stock" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="min_stock_level" class="form-label">Минимальный остаток</label>
                        <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="unit" class="form-label">Единица измерения</label>
                        <select class="form-select" id="unit" name="unit" required>
                            <option value="шт">шт (штука)</option>
                            <option value="кг">кг (килограмм)</option>
                            <option value="г">г (грамм)</option>
                            <option value="л">л (литр)</option>
                            <option value="мл">мл (миллилитр)</option>
                            <option value="м">м (метр)</option>
                            <option value="см">см (сантиметр)</option>
                            <option value="м²">м² (квадратный метр)</option>
                            <option value="м³">м³ (кубический метр)</option>
                            <option value="упак">упак (упаковка)</option>
                            <option value="компл">компл (комплект)</option>
                            <option value="пара">пара</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="product_image" class="form-label">Изображение товара</label>
                        <input type="file" class="form-control" id="product_image" name="product_image" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить товар</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования товара -->
<div class="modal fade" id="editProductModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Редактировать товар</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="mb-3">
                        <label for="edit_product_code" class="form-label">Код товара</label>
                        <input type="text" class="form-control" id="edit_product_code" name="product_code" required maxlength="20">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_product_name" class="form-label">Название товара</label>
                        <input type="text" class="form-control" id="edit_product_name" name="product_name" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_product_description" class="form-label">Описание</label>
                        <textarea class="form-control" id="edit_product_description" name="product_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_category_id" class="form-label">Категория</label>
                        <select class="form-select" id="edit_category_id" name="category_id" required>
                            <option value="">Категория</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_product_price" class="form-label">Цена</label>
                        <input type="number" class="form-control" id="edit_product_price" name="product_price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_quantity_in_stock" class="form-label">Количество на складе</label>
                        <input type="number" class="form-control" id="edit_quantity_in_stock" name="quantity_in_stock" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_min_stock_level" class="form-label">Минимальный остаток</label>
                        <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_unit" class="form-label">Единица измерения</label>
                        <select class="form-select" id="edit_unit" name="unit" required>
                            <option value="шт">шт (штука)</option>
                            <option value="кг">кг (килограмм)</option>
                            <option value="г">г (грамм)</option>
                            <option value="л">л (литр)</option>
                            <option value="мл">мл (миллилитр)</option>
                            <option value="м">м (метр)</option>
                            <option value="см">см (сантиметр)</option>
                            <option value="м²">м² (квадратный метр)</option>
                            <option value="м³">м³ (кубический метр)</option>
                            <option value="упак">упак (упаковка)</option>
                            <option value="компл">компл (комплект)</option>
                            <option value="пара">пара</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_product_image" class="form-label">Изображение товара</label>
                        <input type="file" class="form-control" id="edit_product_image" name="product_image" accept="image/*">
                        <div id="current_image_preview" class="mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно обновления остатков -->
<div class="modal fade" id="updateStockModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-md modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-box-arrow-up"></i> Обновить остатки</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_stock">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="product_id" id="update_product_id">
                    
                    <div class="mb-3">
                        <label for="new_quantity" class="form-label">Новое количество</label>
                        <input type="number" class="form-control" id="new_quantity" name="new_quantity" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Обновить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Переключение между видами
    const tableViewBtn = document.getElementById('table-view');
    const cardViewBtn = document.getElementById('card-view');
    const tableViewContent = document.getElementById('table-view-content');
    const cardViewContent = document.getElementById('card-view-content');
    
    tableViewBtn.addEventListener('change', function() {
        if (this.checked) {
            tableViewContent.style.display = 'block';
            cardViewContent.style.display = 'none';
        }
    });
    
    cardViewBtn.addEventListener('change', function() {
        if (this.checked) {
            tableViewContent.style.display = 'none';
            cardViewContent.style.display = 'block';
        }
    });
});

<?php if (isAdminOrSupervisor()): ?>
// Функции для работы с товарами
function editProduct(product) {
    const modal = document.getElementById('editProductModal');
    const setVal = (el, val, trigger = 'input') => {
        if (!el) return;
        el.value = val ?? '';
        el.dispatchEvent(new Event(trigger, { bubbles: true }));
        // Повторно через микротаймер на случай перерисовок/инициализаций
        setTimeout(() => {
            el.value = val ?? '';
            el.dispatchEvent(new Event(trigger, { bubbles: true }));
        }, 0);
    };
    const q = (sel) => modal ? modal.querySelector(sel) : document.querySelector(sel);

    setVal(q('#edit_product_id'), product.id, 'change');
    setVal(q('#edit_product_code'), product.product_code);
    setVal(q('#edit_product_name'), product.name);
    setVal(q('#edit_product_description'), product.description || '');
    // Надёжно выставляем категорию: пробуем через value и явный выбор опции
    const categorySelect = q('#edit_category_id');
    const applyCategory = () => {
        if (!categorySelect) return;
        const targetVal = String(product.category_id ?? '');
        const byValue = Array.from(categorySelect.options).findIndex(o => String(o.value) === targetVal);
        if (byValue >= 0) {
            categorySelect.selectedIndex = byValue;
        } else {
            // fallback: пробуем по названию категории
            const targetText = String(product.category_name || '').trim().toLowerCase();
            const byText = Array.from(categorySelect.options).findIndex(o => o.textContent.trim().toLowerCase() === targetText);
            if (byText >= 0) {
                categorySelect.selectedIndex = byText;
            }
        }
        categorySelect.dispatchEvent(new Event('change', { bubbles: true }));
    };
    // Ставим сразу и повторно после показа модалки — на случай переинициализации DOM
    applyCategory();
    const modalEl = modal;
    if (modalEl) {
        const once = () => {
            // повтор после открытия
            setTimeout(() => {
                applyCategory();
                // Дополнительно повторно проставим числовые поля после анимации
                setVal(q('#edit_product_price'), product.price);
                setVal(q('#edit_quantity_in_stock'), product.quantity_in_stock);
                setVal(q('#edit_min_stock_level'), product.min_stock_level);
                setVal(q('#edit_unit'), product.unit, 'change');
                // Стабилизация: несколько попыток проставить значения, если другой код их сбрасывает
                let attempts = 10;
                const stabilize = () => {
                    attempts--;
                    applyCategory();
                    setVal(q('#edit_product_price'), product.price);
                    setVal(q('#edit_quantity_in_stock'), product.quantity_in_stock);
                    setVal(q('#edit_min_stock_level'), product.min_stock_level);
                    setVal(q('#edit_unit'), product.unit, 'change');
                    if (attempts > 0) setTimeout(stabilize, 50);
                };
                setTimeout(stabilize, 50);
            }, 0);
            modalEl.removeEventListener('shown.bs.modal', once);
        };
        modalEl.addEventListener('shown.bs.modal', once);
    }
    setVal(q('#edit_product_price'), product.price);
    setVal(q('#edit_quantity_in_stock'), product.quantity_in_stock);
    setVal(q('#edit_unit'), product.unit, 'change');
    setVal(q('#edit_min_stock_level'), product.min_stock_level);
     
     // Показываем текущее изображение, если есть
     const imagePreview = q('#current_image_preview');
     if (product.image_path && product.image_path !== '') {
         imagePreview.innerHTML = `
             <small class="text-muted">Текущее изображение:</small><br>
             <img src="${product.image_path}" alt="Текущее изображение" 
                  style="max-width: 100px; max-height: 100px; object-fit: cover;" 
                  class="img-thumbnail">
         `;
     } else {
         imagePreview.innerHTML = '<small class="text-muted">Изображение не загружено</small>';
     }

     // Принудительно обновляем валидацию и счетчики символов для предзаполненных полей
     if (window.FormValidation && typeof window.FormValidation.forceUpdate === 'function') {
         window.FormValidation.forceUpdate([
            q('#edit_product_code'),
            q('#edit_product_name'),
            q('#edit_product_description'),
            q('#edit_category_id'),
            q('#edit_product_price'),
            q('#edit_quantity_in_stock'),
            q('#edit_unit'),
            q('#edit_min_stock_level')
         ]);
     }
}

function updateStock(productId, productName, currentQuantity, unit) {
    document.getElementById('update_product_id').value = productId;
    document.getElementById('new_quantity').value = currentQuantity;

    if (window.FormValidation && typeof window.FormValidation.forceUpdate === 'function') {
        window.FormValidation.forceUpdate([
            document.getElementById('new_quantity')
        ]);
    }
}

function deleteProduct(productId, productName) {
    if (confirm('Вы уверены, что хотите удалить товар "' + productName + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" value="${productId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Функции для работы с категориями
function editCategory(category) {
    const modal = document.getElementById('editCategoryModal');
    const q = (sel) => modal ? modal.querySelector(sel) : document.querySelector(sel);
    q('#edit_category_id').value = category.id;
    q('#edit_category_name').value = category.name;
    q('#edit_category_description').value = category.description || '';

    if (window.FormValidation && typeof window.FormValidation.forceUpdate === 'function') {
        window.FormValidation.forceUpdate([
            q('#edit_category_name'),
            q('#edit_category_description')
        ]);
    }
}

function deleteCategory(categoryId, categoryName) {
    if (confirm('Вы уверены, что хотите удалить категорию "' + categoryName + '"?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="${categoryId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>

