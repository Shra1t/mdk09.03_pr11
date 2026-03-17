<?php
class Auth {
    private $db;
    private $user = null;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function login($username, $password) {
        try {
            $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
            $user = $this->db->fetchOne($sql, [$username]);

            if ($user && password_verify($password, $user['password'])) {
                if (!function_exists('decryptSensitive')) {
                    require_once __DIR__ . '/../includes/coding.php';
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = decryptSensitive($user['full_name']);

                $user['full_name'] = $_SESSION['full_name'];
                $user['email'] = decryptSensitive($user['email']);
                $this->user = $user;

                return true;
            }

            return false;
        } catch (Exception $e) {
            throw new Exception("Ошибка авторизации: " . $e->getMessage());
        }
    }

    public function logout() {
        session_destroy();
        $this->user = null;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }

    public function isSupervisor() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'supervisor';
    }

    public function isAdminOrSupervisor() {
        return $this->isLoggedIn() && in_array($_SESSION['user_role'], ['admin', 'supervisor']);
    }

    public function getUser() {
        if ($this->user === null && $this->isLoggedIn()) {
            $sql = "SELECT * FROM users WHERE id = ?";
            $this->user = $this->db->fetchOne($sql, [$_SESSION['user_id']]);
            if ($this->user && function_exists('decryptSensitive')) {
                $this->user['full_name'] = decryptSensitive($this->user['full_name']);
                $this->user['email'] = decryptSensitive($this->user['email']);
            }
        }
        return $this->user;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit;
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: employee.php');
            exit;
        }
    }

    public function requireSupervisor() {
        $this->requireLogin();
        if (!$this->isSupervisor()) {
            header('Location: employee.php');
            exit;
        }
    }

    public function requireAdminOrSupervisor() {
        $this->requireLogin();
        if (!$this->isAdminOrSupervisor()) {
            header('Location: employee.php');
            exit;
        }
    }

    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public function getRole() {
        return $_SESSION['user_role'] ?? null;
    }
}
?>
