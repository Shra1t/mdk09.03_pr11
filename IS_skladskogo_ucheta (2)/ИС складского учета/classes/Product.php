<?php
class Product {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll($filters = []) {
        try {
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE 1=1";
            $params = [];

            if (!empty($filters['search'])) {
                $sql .= " AND p.name LIKE ?";
                $params[] = '%' . $filters['search'] . '%';
            }

            if (!empty($filters['category_id'])) {
                $sql .= " AND p.category_id = ?";
                $params[] = $filters['category_id'];
            }

            if (!empty($filters['stock_status'])) {
                switch ($filters['stock_status']) {
                    case 'low':
                        $sql .= " AND p.quantity_in_stock <= p.min_stock_level";
                        break;
                    case 'normal':
                        $sql .= " AND p.quantity_in_stock > p.min_stock_level";
                        break;
                    case 'out':
                        $sql .= " AND p.quantity_in_stock = 0";
                        break;
                }
            }

            $sql .= " ORDER BY p.name";

            return $this->db->fetchAll($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения товаров: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.id = ?";
            return $this->db->fetchOne($sql, [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения товара: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            // Обработка загрузки изображения только если файл действительно загружен
            if (isset($_FILES['product_image']) && 
                $_FILES['product_image']['error'] === UPLOAD_ERR_OK && 
                !empty($_FILES['product_image']['tmp_name'])) {
                $imagePath = $this->uploadImage($_FILES['product_image']);
                $data['image_path'] = $imagePath;
            }
            
            return $this->db->insert('products', $data);
        } catch (Exception $e) {
            throw new Exception("Ошибка создания товара: " . $e->getMessage());
        }
    }

    public function update($id, $data) {
        try {
            // Обработка загрузки изображения только если файл действительно загружен
            if (isset($_FILES['product_image']) && 
                $_FILES['product_image']['error'] === UPLOAD_ERR_OK && 
                !empty($_FILES['product_image']['tmp_name'])) {
                // Получаем старый товар для удаления старого изображения
                $oldProduct = $this->getById($id);
                if ($oldProduct && !empty($oldProduct['image_path'])) {
                    $this->deleteImage($oldProduct['image_path']);
                }
                
                $imagePath = $this->uploadImage($_FILES['product_image']);
                $data['image_path'] = $imagePath;
            }
            
            return $this->db->update('products', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления товара: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            return $this->db->delete('products', 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка удаления товара: " . $e->getMessage());
        }
    }

    public function updateStock($id, $newQuantity) {
        try {
            $data = ['quantity_in_stock' => $newQuantity];
            return $this->db->update('products', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления остатков: " . $e->getMessage());
        }
    }

    public function getLowStockProducts() {
        try {
            $sql = "SELECT p.*, c.name as category_name 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    WHERE p.quantity_in_stock <= p.min_stock_level 
                    ORDER BY p.quantity_in_stock ASC";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения товаров с низким остатком: " . $e->getMessage());
        }
    }

    public function getCategories() {
        try {
            $sql = "SELECT * FROM categories ORDER BY name";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения категорий: " . $e->getMessage());
        }
    }

    public function getTopByMovement($limit = 10) {
        try {
            // Это заглушка - нужно создать таблицу для движения товаров
            $sql = "SELECT p.*, c.name as category_name, 
                           0 as movement_count, 
                           (p.price * p.quantity_in_stock) as turnover
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id 
                    ORDER BY turnover DESC 
                    LIMIT ?";
            return $this->db->fetchAll($sql, [$limit]);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения топ товаров: " . $e->getMessage());
        }
    }

    /**
     * Загрузка изображения товара
     */
    public function uploadImage($file, $productId = null) {
        try {
            // Проверяем тип файла
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Недопустимый тип файла. Разрешены только: JPEG, PNG, GIF, WebP');
            }

            // Проверяем размер файла (максимум 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Размер файла не должен превышать 5MB');
            }

            // Создаем папку для изображений, если её нет
            $uploadDir = 'uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Генерируем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;

            // Перемещаем файл
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Ошибка загрузки файла');
            }

            return $filePath;
        } catch (Exception $e) {
            throw new Exception("Ошибка загрузки изображения: " . $e->getMessage());
        }
    }

    /**
     * Удаление изображения товара
     */
    public function deleteImage($imagePath) {
        try {
            if (!empty($imagePath) && file_exists($imagePath)) {
                unlink($imagePath);
            }
        } catch (Exception $e) {
            // Логируем ошибку только в режиме разработки
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Ошибка удаления изображения: " . $e->getMessage());
            }
        }
    }

    public function getByCode($productCode) {
        try {
            $sql = "SELECT * FROM products WHERE product_code = ?";
            return $this->db->fetchOne($sql, [$productCode]);
        } catch (Exception $e) {
            return null;
        }
    }
}
?>