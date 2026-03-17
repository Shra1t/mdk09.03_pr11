<?php
class Supplier {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    private function decryptSupplierRow($row) {
        if (!$row) return $row;
        if (!function_exists('decryptSensitive')) {
            require_once __DIR__ . '/../includes/coding.php';
        }
        $row['full_name'] = decryptSensitive($row['full_name'] ?? '');
        $row['name'] = $row['full_name'];
        $row['company_name'] = decryptSensitive($row['company_name'] ?? '');
        $row['phone'] = decryptSensitive($row['phone'] ?? '');
        $row['email'] = decryptSensitive($row['email'] ?? '');
        $row['address'] = decryptSensitive($row['address'] ?? '');
        $row['contact_person'] = decryptSensitive($row['contact_person'] ?? '');
        return $row;
    }

    public function getAll($filters = []) {
        try {
            $sql = "SELECT *, 
                           CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END as status,
                           full_name as name
                    FROM suppliers WHERE 1=1";
            $params = [];

            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $sql .= " AND is_active = 1";
                } else {
                    $sql .= " AND is_active = 0";
                }
            }
            
            if (!empty($filters['active_only'])) {
                $sql .= " AND is_active = 1";
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND created_at >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND created_at <= ?";
                $params[] = $filters['date_to'];
            }

            switch ($filters['sort'] ?? 'full_name') {
                case 'full_name':
                case 'name':
                    $sql .= " ORDER BY full_name";
                    break;
                case 'company_name':
                    $sql .= " ORDER BY company_name";
                    break;
                default:
                    $sql .= " ORDER BY full_name";
            }

            $rows = $this->db->fetchAll($sql, $params);
            $out = [];
            foreach ($rows as $row) {
                $row = $this->decryptSupplierRow($row);
                if (!empty($filters['search'])) {
                    $term = mb_strtolower($filters['search']);
                    $match = (stripos($row['full_name'], $term) !== false)
                        || (stripos($row['company_name'], $term) !== false)
                        || (stripos($row['contact_person'], $term) !== false)
                        || (stripos($row['email'], $term) !== false);
                    if (!$match) continue;
                }
                $out[] = $row;
            }
            return $out;
        } catch (Exception $e) {
            throw new Exception("Ошибка получения поставщиков: " . $e->getMessage());
        }
    }

    public function getById($id) {
        try {
            $sql = "SELECT *, 
                           CASE WHEN is_active = 1 THEN 'active' ELSE 'inactive' END as status,
                           full_name as name
                    FROM suppliers WHERE id = ?";
            $row = $this->db->fetchOne($sql, [$id]);
            return $row ? $this->decryptSupplierRow($row) : null;
        } catch (Exception $e) {
            throw new Exception("Ошибка получения поставщика: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            if (!function_exists('encryptSensitive')) {
                require_once __DIR__ . '/../includes/coding.php';
            }
            if (!isset($data['supplier_code'])) {
                $data['supplier_code'] = 'SUP' . str_pad($this->getNextId(), 3, '0', STR_PAD_LEFT);
            }
            
            $dbData = [
                'supplier_code' => $data['supplier_code'],
                'full_name' => encryptSensitive($data['name'] ?? ''),
                'company_name' => $data['company_name'] !== null && $data['company_name'] !== '' ? encryptSensitive($data['company_name']) : null,
                'contact_person' => $data['contact_person'] !== null && $data['contact_person'] !== '' ? encryptSensitive($data['contact_person']) : null,
                'email' => $data['email'] !== null && $data['email'] !== '' ? encryptSensitive($data['email']) : null,
                'phone' => $data['phone'] !== null && $data['phone'] !== '' ? encryptSensitive($data['phone']) : null,
                'address' => $data['address'] !== null && $data['address'] !== '' ? encryptSensitive($data['address']) : null,
                'payment_terms' => $data['payment_terms'] ?? 'prepayment',
                'delivery_terms' => $data['delivery_terms'] ?? 'pickup',
                'is_active' => 1
            ];
            
            return $this->db->insert('suppliers', $dbData);
        } catch (Exception $e) {
            throw new Exception("Ошибка создания поставщика: " . $e->getMessage());
        }
    }

    public function update($id, $data) {
        try {
            if (!function_exists('encryptSensitive')) {
                require_once __DIR__ . '/../includes/coding.php';
            }
            $dbData = [
                'full_name' => encryptSensitive($data['name'] ?? ''),
                'company_name' => $data['company_name'] !== null && $data['company_name'] !== '' ? encryptSensitive($data['company_name']) : null,
                'contact_person' => $data['contact_person'] !== null && $data['contact_person'] !== '' ? encryptSensitive($data['contact_person']) : null,
                'email' => $data['email'] !== null && $data['email'] !== '' ? encryptSensitive($data['email']) : null,
                'phone' => $data['phone'] !== null && $data['phone'] !== '' ? encryptSensitive($data['phone']) : null,
                'address' => $data['address'] !== null && $data['address'] !== '' ? encryptSensitive($data['address']) : null,
                'payment_terms' => $data['payment_terms'] ?? 'prepayment',
                'delivery_terms' => $data['delivery_terms'] ?? 'pickup'
            ];
            
            return $this->db->update('suppliers', $dbData, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка обновления поставщика: " . $e->getMessage());
        }
    }

    public function delete($id) {
        try {
            // Проверяем, есть ли связанные поставки
            $deliveryCount = $this->getDeliveryCount($id);
            if ($deliveryCount > 0) {
                throw new Exception("Нельзя удалить поставщика, у которого есть поставки. Сначала удалите все поставки или сделайте поставщика неактивным.");
            }
            
            return $this->db->delete('suppliers', 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка удаления поставщика: " . $e->getMessage());
        }
    }

    private function getDeliveryCount($supplierId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM deliveries WHERE supplier_id = ?";
            $result = $this->db->fetchOne($sql, [$supplierId]);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public function toggleStatus($id) {
        try {
            // Валидация ID
            $id = (int)$id;
            if ($id <= 0) {
                throw new Exception("Неверный ID поставщика");
            }
            
            $supplier = $this->getById($id);
            if (!$supplier) {
                throw new Exception("Поставщик не найден");
            }

            $newStatus = $supplier['is_active'] ? 0 : 1;
            $data = ['is_active' => $newStatus];
            
            // Логируем операцию для отладки
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Changing supplier ID {$id} status from {$supplier['is_active']} to {$newStatus}");
            }
            
            return $this->db->update('suppliers', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка изменения статуса: " . $e->getMessage());
        }
    }

    public function deactivate($id) {
        try {
            $data = ['is_active' => 0];
            return $this->db->update('suppliers', $data, 'id = ?', [$id]);
        } catch (Exception $e) {
            throw new Exception("Ошибка деактивации поставщика: " . $e->getMessage());
        }
    }

    public function getTopByAmount($limit = 10) {
        try {
            $sql = "SELECT s.*, 
                           COALESCE(COUNT(d.id), 0) as deliveries_count,
                           COALESCE(SUM(d.total_amount), 0) as total_amount,
                           MAX(d.order_date) as last_delivery_date
                    FROM suppliers s 
                    LEFT JOIN deliveries d ON s.id = d.supplier_id 
                    GROUP BY s.id 
                    ORDER BY total_amount DESC 
                    LIMIT ?";
            $rows = $this->db->fetchAll($sql, [$limit]);
            return array_map([$this, 'decryptSupplierRow'], $rows);
        } catch (Exception $e) {
            throw new Exception("Ошибка получения топ поставщиков: " . $e->getMessage());
        }
    }

    private function getNextId() {
        try {
            $sql = "SELECT MAX(id) + 1 as next_id FROM suppliers";
            $result = $this->db->fetchOne($sql);
            return $result['next_id'] ?? 1;
        } catch (Exception $e) {
            return 1;
        }
    }

    // Новый метод для получения доступных условий оплаты
    public function getPaymentTermsOptions() {
        return [
            'prepayment' => 'Предоплата 100%',
            '50_50' => '50% предоплата + 50% по факту',
            'postpayment' => 'Оплата по факту',
            'credit_30' => 'Отсрочка 30 дней',
            'credit_60' => 'Отсрочка 60 дней'
        ];
    }

    // Новый метод для получения доступных условий доставки
    public function getDeliveryTermsOptions() {
        return [
            'pickup' => 'Самовывоз',
            'delivery_paid' => 'Доставка платная',
            'delivery_free' => 'Доставка бесплатная',
            'delivery_conditional' => 'Доставка при условии'
        ];
    }
}
?>