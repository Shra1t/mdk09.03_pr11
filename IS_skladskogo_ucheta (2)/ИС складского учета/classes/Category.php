<?php
class Category {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        try {
            $sql = "SELECT c.*, 
                           COUNT(p.id) as products_count,
                           COALESCE(SUM(p.quantity_in_stock), 0) as total_stock,
                           COALESCE(SUM(p.price * p.quantity_in_stock), 0) as total_value
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения категорий: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $sql = "SELECT c.*, 
                           COUNT(p.id) as products_count
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    WHERE c.id = ? 
                    GROUP BY c.id";
            return $this->db->fetchOne($sql, [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения категории: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            return $this->db->insert('categories', $data);
        } catch (Exception $e) {
            throw new Exception("Ошибка создания категории: " . $e->getMessage());
        }
    }

    public function update($id, $data) {
        try {
            return $this->db->update('categories', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления категории: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            // Проверяем, есть ли товары в этой категории
            $sql = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
            $result = $this->db->fetchOne($sql, [$id]);
            
            if ($result['count'] > 0) {
                throw new Exception("Невозможно удалить категорию: в ней есть товары");
            }
            
            return $this->db->delete('categories', 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка удаления категории: " . $e->getMessage());
        }
    }

    public function getCategoriesWithStats() {
        try {
            $sql = "SELECT c.*, 
                           COUNT(p.id) as products_count,
                           COALESCE(SUM(p.quantity_in_stock), 0) as total_stock,
                           COALESCE(SUM(p.price * p.quantity_in_stock), 0) as total_value
                    FROM categories c 
                    LEFT JOIN products p ON c.id = p.category_id 
                    GROUP BY c.id 
                    ORDER BY c.name";
            return $this->db->fetchAll($sql);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения статистики категорий: " . $e->getMessage());
        }
    }
}
?>