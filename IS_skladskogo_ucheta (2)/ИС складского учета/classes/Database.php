<?php
class Database {
    private $mysqli;
    private static $instance = null;

    private function __construct() {
        try {
            $this->mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->mysqli->connect_error) {
                throw new Exception("Ошибка подключения к базе данных: " . $this->mysqli->connect_error);
            }
            
            $this->mysqli->set_charset(DB_CHARSET);
        } catch (Exception $e) {
            throw new Exception("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->mysqli;
    }

    public function query($sql, $params = []) {
        try {
            if (empty($params)) {
                $result = $this->mysqli->query($sql);
                if (!$result) {
                    throw new Exception("Ошибка выполнения запроса: " . $this->mysqli->error);
                }
                return $result;
            } else {
                $stmt = $this->mysqli->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Ошибка подготовки запроса: " . $this->mysqli->error);
                }
                
                if (!empty($params)) {
                    $types = '';
                    $values = [];
                    
                    foreach ($params as $param) {
                        if (is_int($param)) {
                            $types .= 'i';
                        } elseif (is_float($param)) {
                            $types .= 'd';
                        } else {
                            $types .= 's';
                        }
                        $values[] = $param;
                    }
                    
                    $stmt->bind_param($types, ...$values);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception("Ошибка выполнения запроса: " . $stmt->error);
                }
                
                return $stmt->get_result();
            }
        } catch (Exception $e) {
            throw new Exception("Ошибка выполнения запроса: " . $e->getMessage());
        }
    }

    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        return [];
    }

    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        if ($result instanceof mysqli_result) {
            return $result->fetch_assoc();
        }
        return null;
    }

    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = str_repeat('?,', count($data) - 1) . '?';

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));

        return $this->mysqli->insert_id;
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = ?";
        }
        $setClause = implode(', ', $set);

        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);

        $this->query($sql, $params);
        return $this->mysqli->affected_rows;
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->query($sql, $params);
        return $this->mysqli->affected_rows;
    }

    public function beginTransaction() {
        return $this->mysqli->begin_transaction();
    }

    public function commit() {
        return $this->mysqli->commit();
    }

    public function rollback() {
        return $this->mysqli->rollback();
    }

    public function escape($string) {
        return $this->mysqli->real_escape_string($string);
    }
}
?>