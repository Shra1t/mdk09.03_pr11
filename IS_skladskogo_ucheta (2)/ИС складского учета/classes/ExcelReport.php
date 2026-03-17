<?php
class ExcelReport {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function generateStockReport($filters = []) {
        try {
            $products = $this->getStockData($filters);
            $this->generateExcelReport($products, "stock", "ОТЧЕТ ПО ОСТАТКАМ ТОВАРОВ НА СКЛАДЕ");
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации отчета: " . $e->getMessage());
        }
    }

    public function generateDeliveryReport($filters = []) {
        try {
            $deliveries = $this->getDeliveryData($filters);
            $this->generateExcelReport($deliveries, "delivery", "ОТЧЕТ ПО ПОСТАВКАМ");
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации отчета: " . $e->getMessage());
        }
    }

    public function generateSupplierReport($filters = []) {
        try {
            $suppliers = $this->getSupplierData($filters);
            $this->generateExcelReport($suppliers, "supplier", "ОТЧЕТ ПО ПОСТАВЩИКАМ");
        } catch (Exception $e) {
            throw new Exception("Ошибка генерации отчета: " . $e->getMessage());
        }
    }

    private function generateExcelReport($data, $type, $title) {
        // Устанавливаем заголовки для скачивания Excel файла
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $type . '_report_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        
        // Начинаем вывод Excel-совместимого HTML
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta name="ProgId" content="Excel.Sheet">
    <meta name="Generator" content="Microsoft Excel 11">
    <title>' . htmlspecialchars($title) . '</title>
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>' . htmlspecialchars($title) . '</x:Name>
                    <x:WorksheetOptions>
                        <x:DefaultRowHeight>285</x:DefaultRowHeight>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
</head>
<body>';

        // Заголовок отчета
        echo '<table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td colspan="8" style="background-color: #2c3e50; color: white; font-size: 18px; font-weight: bold; text-align: center; padding: 20px; border: 2px solid #1a252f;">
                    ' . htmlspecialchars($title) . '
                </td>
            </tr>
            <tr>
                <td colspan="8" style="background-color: #34495e; color: white; font-size: 12px; text-align: center; padding: 10px; border-left: 2px solid #1a252f; border-right: 2px solid #1a252f; border-bottom: 2px solid #1a252f;">
                    Сгенерировано ' . date('d.m.Y в H:i') . '
                </td>
            </tr>
            <tr>
                <td colspan="8" style="background-color: #ecf0f1; color: #2c3e50; font-size: 11px; padding: 8px; border-left: 2px solid #1a252f; border-right: 2px solid #1a252f; border-bottom: 1px solid #bdc3c7;">
                    Всего записей: ' . count($data) . ' | Дата генерации: ' . date('d.m.Y H:i') . ' | Система: Информационная система склада
                </td>
            </tr>
        </table>';

        // Пустая строка
        echo '<br>';

        // Заголовки таблицы
        echo '<table border="1" cellpadding="8" cellspacing="0" width="100%" style="border-collapse: collapse;">';
        
        if ($type === 'stock') {
            echo '<tr style="background-color: #2c3e50; color: white; font-weight: bold; font-size: 12px;">
                    <th style="border: 2px solid #1a252f; padding: 12px;">Код товара</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Название</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Категория</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Количество</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Единица</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Цена</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Общая стоимость</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Статус</th>
                  </tr>';
        } elseif ($type === 'delivery') {
            echo '<tr style="background-color: #2c3e50; color: white; font-weight: bold; font-size: 12px;">
                    <th style="border: 2px solid #1a252f; padding: 12px;">Код поставки</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Поставщик</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Дата заказа</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Ожидаемая дата</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Статус</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Сумма</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Примечания</th>
                  </tr>';
        } elseif ($type === 'supplier') {
            echo '<tr style="background-color: #2c3e50; color: white; font-weight: bold; font-size: 12px;">
                    <th style="border: 2px solid #1a252f; padding: 12px;">Название</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Контактное лицо</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Телефон</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Email</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Количество поставок</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Общая сумма</th>
                    <th style="border: 2px solid #1a252f; padding: 12px;">Статус</th>
                  </tr>';
        }

        // Данные
        $rowNum = 0;
        foreach ($data as $item) {
            $rowNum++;
            $bgColor = ($rowNum % 2 == 0) ? '#f8f9fa' : '#ffffff';
            
            echo '<tr style="background-color: ' . $bgColor . ';">';
            
            if ($type === 'stock') {
                $status = 'В наличии';
                $statusColor = '#d4edda';
                $statusTextColor = '#155724';
                if ($item['quantity_in_stock'] == 0) {
                    $status = 'Закончился';
                    $statusColor = '#f8d7da';
                    $statusTextColor = '#721c24';
                } elseif ($item['quantity_in_stock'] <= $item['min_stock_level']) {
                    $status = 'Критический';
                    $statusColor = '#f8d7da';
                    $statusTextColor = '#721c24';
                } elseif ($item['quantity_in_stock'] <= $item['min_stock_level'] * 1.5) {
                    $status = 'Низкий';
                    $statusColor = '#fff3cd';
                    $statusTextColor = '#856404';
                }
                
                echo '<td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['product_code']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['name']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['category_name'] ?? 'Без категории') . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center;">' . $item['quantity_in_stock'] . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center;">' . htmlspecialchars($item['unit']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: right;">' . number_format($item['price'], 2) . ' ₽</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: right;">' . number_format($item['price'] * $item['quantity_in_stock'], 2) . ' ₽</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center; background-color: ' . $statusColor . '; color: ' . $statusTextColor . '; font-weight: bold;">' . $status . '</td>';
                      
            } elseif ($type === 'delivery') {
                $statusText = $item['status'] === 'pending' ? 'Ожидается' : 
                             ($item['status'] === 'completed' ? 'Принята' : 
                             ($item['status'] === 'in_transit' ? 'В пути' : $item['status']));
                
                $statusColor = '#d1ecf1';
                $statusTextColor = '#0c5460';
                if ($item['status'] === 'completed') {
                    $statusColor = '#d4edda';
                    $statusTextColor = '#155724';
                } elseif ($item['status'] === 'pending') {
                    $statusColor = '#fff3cd';
                    $statusTextColor = '#856404';
                }
                
                echo '<td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['delivery_code']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['supplier_name']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . date('d.m.Y', strtotime($item['order_date'])) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . date('d.m.Y', strtotime($item['expected_date'])) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center; background-color: ' . $statusColor . '; color: ' . $statusTextColor . '; font-weight: bold;">' . $statusText . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: right;">' . number_format($item['total_amount'], 2) . ' ₽</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['notes'] ?? '') . '</td>';
                      
            } elseif ($type === 'supplier') {
                $status = $item['is_active'] ? 'Активен' : 'Неактивен';
                $statusColor = $item['is_active'] ? '#d4edda' : '#f8d7da';
                $statusTextColor = $item['is_active'] ? '#155724' : '#721c24';
                
                echo '<td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['name']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['contact_person']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['phone']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px;">' . htmlspecialchars($item['email']) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center;">' . ($item['deliveries_count'] ?? 0) . '</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: right;">' . number_format($item['total_amount'] ?? 0, 2) . ' ₽</td>
                      <td style="border: 1px solid #dee2e6; padding: 8px; font-size: 11px; text-align: center; background-color: ' . $statusColor . '; color: ' . $statusTextColor . '; font-weight: bold;">' . $status . '</td>';
            }
            
            echo '</tr>';
        }

        echo '</table>';

        // Пустая строка
        echo '<br>';

        // Сводка
        echo '<table border="1" cellpadding="10" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: #f8f9fa;">';
        echo '<tr>
                <td colspan="2" style="background-color: #2c3e50; color: white; font-size: 14px; font-weight: bold; text-align: center; padding: 15px; border: 2px solid #1a252f;">
                    СВОДКА';
        
        if ($type === 'stock') {
            echo ' ПО ОСТАТКАМ';
        } elseif ($type === 'delivery') {
            echo ' ПО ПОСТАВКАМ';
        } elseif ($type === 'supplier') {
            echo ' ПО ПОСТАВЩИКАМ';
        }
        
        echo '</td>
              </tr>';

        if ($type === 'stock') {
            $totalValue = array_sum(array_map(function($item) { return $item['price'] * $item['quantity_in_stock']; }, $data));
            $lowStockCount = count(array_filter($data, function($item) { return $item['quantity_in_stock'] <= $item['min_stock_level']; }));
            
            echo '<tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Общая стоимость товаров на складе:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . number_format($totalValue, 2) . ' ₽</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Товаров с низким остатком:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . $lowStockCount . '</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Всего наименований:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . count($data) . '</td>
                  </tr>';
                  
        } elseif ($type === 'delivery') {
            $totalAmount = array_sum(array_map(function($item) { return $item['total_amount']; }, $data));
            $pendingCount = count(array_filter($data, function($item) { return $item['status'] === 'pending'; }));
            
            echo '<tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Общая сумма поставок:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . number_format($totalAmount, 2) . ' ₽</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Ожидаемых поставок:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . $pendingCount . '</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Всего поставок:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . count($data) . '</td>
                  </tr>';
                  
        } elseif ($type === 'supplier') {
            $totalAmount = array_sum(array_map(function($item) { return $item['total_amount'] ?? 0; }, $data));
            $activeCount = count(array_filter($data, function($item) { return $item['is_active']; }));
            
            echo '<tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Общая сумма поставок:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . number_format($totalAmount, 2) . ' ₽</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Активных поставщиков:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . $activeCount . '</td>
                  </tr>
                  <tr>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #2c3e50;">Всего поставщиков:</td>
                    <td style="border: 1px solid #dee2e6; padding: 10px; font-weight: bold; color: #e74c3c; text-align: right;">' . count($data) . '</td>
                  </tr>';
        }

        echo '</table>';

        echo '</body></html>';
        exit;
    }

    private function getStockData($filters) {
        $product = new Product();
        return $product->getAll($filters);
    }

    private function getDeliveryData($filters) {
        $delivery = new Delivery();
        return $delivery->getAll($filters);
    }

    private function getSupplierData($filters) {
        $supplier = new Supplier();
        $suppliers = $supplier->getAll($filters);
        
        // Добавляем статистику - создаем объект Delivery один раз
        $delivery = new Delivery();
        foreach ($suppliers as &$sup) {
            $sup['deliveries_count'] = $delivery->getCountBySupplier($sup['id']);
            $sup['total_amount'] = $delivery->getTotalAmountBySupplier($sup['id']);
        }
        
        return $suppliers;
    }
}
?>