<?php
class Delivery {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function decryptDeliveryRow($row) {
        if (!$row) return $row;
        if (!function_exists('decryptSensitive')) {
            require_once __DIR__ . '/../includes/coding.php';
        }
        if (isset($row['supplier_name'])) {
            $row['supplier_name'] = decryptSensitive($row['supplier_name']);
        }
        if (isset($row['company_name'])) {
            $row['company_name'] = decryptSensitive($row['company_name']);
        }
        return $row;
    }

    public function getAll($filters = []) {
        try {
            $sql = "SELECT d.*, s.full_name as supplier_name, s.company_name 
                    FROM deliveries d 
                    LEFT JOIN suppliers s ON d.supplier_id = s.id 
                    WHERE 1=1";
            $params = [];

            if (!empty($filters['supplier_id'])) {
                $sql .= " AND d.supplier_id = ?";
                $params[] = $filters['supplier_id'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND d.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND d.order_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND d.order_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY d.order_date DESC";

            $rows = $this->db->fetchAll($sql, $params);
            return array_map([$this, 'decryptDeliveryRow'], $rows);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения поставок: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $sql = "SELECT d.*, s.full_name as supplier_name, s.company_name 
                    FROM deliveries d 
                    LEFT JOIN suppliers s ON d.supplier_id = s.id 
                    WHERE d.id = ?";
            $row = $this->db->fetchOne($sql, [$id]);
            return $row ? $this->decryptDeliveryRow($row) : null;
        } catch (Exception $e) {
            throw new Exception("Ошибка получения поставки: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            // Добавляем created_by если не указан
            if (!isset($data['created_by'])) {
                $data['created_by'] = $_SESSION['user_id'] ?? 1;
            }
            
            return $this->db->insert('deliveries', $data);
        } catch (Exception $e) {
            throw new Exception("Ошибка создания поставки: " . $e->getMessage());
        }
    }

    public function update($id, $data) {
        try {
            return $this->db->update('deliveries', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления поставки: " . $e->getMessage());
        }
    }

    public function updateStatus($id, $status) {
        try {
            // Валидация статуса
            $allowedStatuses = ['pending', 'in_transit', 'completed', 'cancelled'];
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception("Недопустимый статус: " . $status);
            }
            
            $data = ['status' => $status];
            if ($status === 'completed') {
                $data['actual_date'] = date('Y-m-d');
            }
            return $this->db->update('deliveries', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления статуса: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            return $this->db->delete('deliveries', 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка удаления поставки: " . $e->getMessage());
        }
    }

    public function getCountBySupplier($supplierId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM deliveries WHERE supplier_id = ?";
            $result = $this->db->fetchOne($sql, [$supplierId]);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getTotalAmountBySupplier($supplierId) {
        try {
            $sql = "SELECT SUM(total_amount) as total FROM deliveries WHERE supplier_id = ?";
            $result = $this->db->fetchOne($sql, [$supplierId]);
            return $result['total'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getLastDeliveryBySupplier($supplierId) {
        try {
            $sql = "SELECT * FROM deliveries WHERE supplier_id = ? ORDER BY order_date DESC LIMIT 1";
            return $this->db->fetchOne($sql, [$supplierId]);
        } catch (Exception $e) {
            return null;
        }
    }

    public function getMonthlyStats() {
        try {
            $sql = "SELECT 
                        MONTH(order_date) as month,
                        YEAR(order_date) as year,
                        MONTHNAME(order_date) as month_name,
                        COUNT(*) as deliveries_count,
                        SUM(total_amount) as total_amount,
                        AVG(total_amount) as avg_amount
                    FROM deliveries 
                    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY YEAR(order_date), MONTH(order_date)
                    ORDER BY year DESC, month DESC";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            return [];
        }
    }

    public function addDeliveryItem($itemData) {
        try {
            $sql = "INSERT INTO delivery_items (delivery_id, product_code, product_name, product_description, category_id, quantity, unit_price, received_quantity, unit, min_stock_level, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $itemData['delivery_id'],
                $itemData['product_code'],
                $itemData['product_name'],
                $itemData['product_description'],
                $itemData['category_id'],
                $itemData['quantity_ordered'],
                $itemData['price'],
                $itemData['quantity_received'] ?? 0,
                $itemData['unit'],
                $itemData['min_stock_level'],
                $itemData['notes'] ?? ''
            ];
            
            return $this->db->query($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Ошибка добавления товара в поставку: " . $e->getMessage());
        }
    }

    public function getDeliveryItems($deliveryId) {
        try {
            $sql = "SELECT di.*, c.name as category_name, p.name as existing_product_name
                    FROM delivery_items di 
                    LEFT JOIN categories c ON di.category_id = c.id 
                    LEFT JOIN products p ON di.product_id = p.id
                    WHERE di.delivery_id = ? 
                    ORDER BY di.id";
            return $this->db->fetchAll($sql, [$deliveryId]);
        } catch (Exception $e) {
            return [];
        }
    }

    public function updateDeliveryItem($itemId, $data) {
        try {
            $sql = "UPDATE delivery_items SET 
                    received_quantity = ?, 
                    unit_price = ?
                    WHERE id = ?";
            
            $params = [
                $data['quantity_received'],
                $data['price'],
                $itemId
            ];
            
            return $this->db->query($sql, $params);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления товара поставки: " . $e->getMessage());
        }
    }

    public function markAsReceived($id) {
        try {
            // Обновляем статус поставки
            $this->updateStatus($id, 'completed');
            
            // Получаем товары поставки
            $items = $this->getDeliveryItems($id);
            
            // Создаем объект Product один раз для всех операций
            $product = new Product();
            foreach ($items as $item) {
                // Обновляем количество товара на складе
                $existingProduct = $product->getByCode($item['product_code']);
                
                if ($existingProduct) {
                    // Обновляем существующий товар
                    $newQuantity = $existingProduct['quantity_in_stock'] + $item['quantity'];
                    $product->update($existingProduct['id'], [
                        'quantity_in_stock' => $newQuantity,
                        'order_status' => 'in_stock'
                    ]);
                } else {
                    // Создаем новый товар
                    $productData = [
                        'product_code' => $item['product_code'],
                        'name' => $item['product_name'],
                        'description' => $item['product_description'],
                        'category_id' => $item['category_id'],
                        'price' => $item['unit_price'],
                        'quantity_in_stock' => $item['quantity'],
                        'min_stock_level' => $item['min_stock_level'],
                        'unit' => $item['unit'],
                        'delivery_id' => $id,
                        'order_status' => 'in_stock',
                        'is_active' => 1
                    ];
                    $product->create($productData);
                }
            }
            
            return true;
        } catch (Exception $e) {
            throw new Exception("Ошибка принятия поставки: " . $e->getMessage());
        }
    }
}
?>