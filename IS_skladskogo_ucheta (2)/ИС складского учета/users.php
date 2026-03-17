<?php
require_once 'includes/config.php';
require_once 'classes/Database.php';
require_once 'classes/Auth.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireAdminOrSupervisor(); // Только для администраторов и руководителей

$db = Database::getInstance();
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
            case 'create_user':
                // Только руководитель может создавать пользователей
                if (!isSupervisor()) {
                    throw new Exception('Недостаточно прав для создания пользователей');
                }
                
                // Валидация данных пользователя
                $username = trim($_POST['username']);
                if (empty($username) || strlen($username) > 50) {
                    throw new Exception('Имя пользователя должно быть от 1 до 50 символов');
                }
                
                $password = $_POST['password'];
                if (strlen($password) < 6) {
                    throw new Exception('Пароль должен содержать минимум 6 символов');
                }
                
                $fullName = trim($_POST['full_name']);
                if (empty($fullName) || strlen($fullName) > 100) {
                    throw new Exception('Полное имя должно быть от 1 до 100 символов');
                }
                
                $email = trim($_POST['email']);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                    throw new Exception('Некорректный email или превышена длина (максимум 100 символов)');
                }
                
                $role = $_POST['role'];
                if (!in_array($role, ['admin', 'supervisor', 'employee'])) {
                    throw new Exception('Некорректная роль пользователя');
                }
                
                $userData = [
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'full_name' => encryptSensitive($fullName),
                    'email' => encryptSensitive($email),
                    'role' => $role,
                    'is_active' => 1
                ];

                // Проверяем, не существует ли уже пользователь с таким username
                $existingUser = $db->fetchOne("SELECT id FROM users WHERE username = ?", [$username]);
                if ($existingUser) {
                    throw new Exception('Пользователь с таким логином уже существует');
                }

                $db->insert('users', $userData);
                $success = 'Пользователь успешно создан';
                break;

            case 'toggle_user_status':
                // Только руководитель может менять статусы пользователей
                if (!isSupervisor()) {
                    throw new Exception('Недостаточно прав для изменения статуса пользователей');
                }
                
                $userId = (int)$_POST['user_id'];
                $user = $db->fetchOne("SELECT is_active FROM users WHERE id = ?", [$userId]);
                
                if (!$user) {
                    throw new Exception('Пользователь не найден');
                }

                $newStatus = $user['is_active'] ? 0 : 1;
                $db->update('users', ['is_active' => $newStatus], 'id = ?', [$userId]);
                $success = 'Статус пользователя изменен';
                break;

            case 'reset_password':
                // Только руководитель может сбрасывать пароли
                if (!isSupervisor()) {
                    throw new Exception('Недостаточно прав для сброса паролей');
                }
                
                $userId = (int)$_POST['user_id'];
                $newPassword = $_POST['new_password'];
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $db->update('users', ['password' => $hashedPassword], 'id = ?', [$userId]);
                $success = 'Пароль пользователя изменен';
                break;

            case 'delete_user':
                // Только руководитель может удалять пользователей
                if (!isSupervisor()) {
                    throw new Exception('Недостаточно прав для удаления пользователей');
                }
                
                $userId = (int)$_POST['user_id'];
                $currentUserId = $auth->getUserId();
                
                // Нельзя удалить самого себя
                if ($userId == $currentUserId) {
                    throw new Exception('Нельзя удалить самого себя');
                }
                
                // Проверяем, что пользователь существует
                $user = $db->fetchOne("SELECT username FROM users WHERE id = ?", [$userId]);
                if (!$user) {
                    throw new Exception('Пользователь не найден');
                }
                
                // Удаляем пользователя
                $db->delete('users', 'id = ?', [$userId]);
                $success = 'Пользователь "' . $user['username'] . '" удален';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение списка пользователей
try {
    $users = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC");
} catch (Exception $e) {
    $users = $db->fetchAll("SELECT * FROM users ORDER BY id DESC");
}
// Расшифровка персональных данных для отображения
foreach ($users as $k => $u) {
    $users[$k]['full_name'] = decryptSensitive($u['full_name']);
    $users[$k]['email'] = decryptSensitive($u['email']);
}

$pageTitle = 'Управление пользователями';
$breadcrumb = [
    ['title' => 'Панель управления', 'url' => 'admin.php'],
    ['title' => 'Пользователи']
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
                <i class="bi bi-people text-primary"></i>
                Управление пользователями
            </h2>
            <p class="text-muted mb-0">Создание и управление учетными записями пользователей</p>
        </div>
        <div>
            <?php if (isSupervisor()): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                <i class="bi bi-person-plus"></i> Создать пользователя
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Статистика пользователей -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary bg-opacity-10 border-primary">
                <div class="card-body text-center">
                    <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count($users) ?></h4>
                    <p class="text-muted mb-0">Всего пользователей</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success bg-opacity-10 border-success">
                <div class="card-body text-center">
                    <i class="bi bi-shield-check text-success" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($users, fn($u) => $u['role'] == 'admin')) ?></h4>
                    <p class="text-muted mb-0">Администраторов</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info bg-opacity-10 border-info">
                <div class="card-body text-center">
                    <i class="bi bi-person-workspace text-info" style="font-size: 2rem;"></i>
                    <h4 class="mt-2 mb-1"><?= count(array_filter($users, fn($u) => $u['role'] == 'employee')) ?></h4>
                    <p class="text-muted mb-0">Сотрудников</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Список пользователей -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-list"></i> Список пользователей
                <span class="badge bg-secondary"><?= count($users) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person"></i> Пользователь</th>
                            <th><i class="bi bi-envelope"></i> Email</th>
                            <th><i class="bi bi-shield"></i> Роль</th>
                            <th><i class="bi bi-toggle-on"></i> Статус</th>
                            <th><i class="bi bi-calendar"></i> Создан</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="bi bi-inbox text-muted" style="font-size: 2rem;"></i>
                                <p class="text-muted mt-2">Пользователи не найдены</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                        <br><small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if ($user['role'] == 'supervisor'): ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-crown"></i> Руководитель
                                        </span>
                                    <?php elseif ($user['role'] == 'admin'): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-shield-check"></i> Администратор
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-person-workspace"></i> Сотрудник
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Активен</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Неактивен</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= isset($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : 'Не указано' ?></td>
                                <td>
                                    <?php if (isSupervisor()): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-warning"
                                                onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>', <?= $user['is_active'] ?>)"
                                                title="Изменить статус">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-info"
                                                onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')"
                                                data-bs-toggle="modal" data-bs-target="#resetPasswordModal"
                                                title="Сбросить пароль">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <?php if ($user['id'] != $auth->getUserId()): ?>
                                        <button type="button" class="btn btn-outline-danger"
                                                onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')"
                                                title="Удалить пользователя">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Только для руководителя</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно создания пользователя -->
    <div class="modal fade" id="createUserModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Создать пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="form-group mb-3">
                        <label for="full_name" class="form-label">ФИО *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="100">
                    </div>

                    <div class="form-group mb-3">
                        <label for="username" class="form-label">Логин *</label>
                        <input type="text" class="form-control" id="username" name="username" required maxlength="50">
                    </div>

                    <div class="form-group mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required maxlength="100">
                    </div>

                    <div class="form-group mb-3">
                        <label for="password" class="form-label">Пароль *</label>
                        <input type="password" class="form-control" id="password" name="password" required 
                               data-min-length="6" placeholder="Минимум 6 символов">
                    </div>

                    <div class="form-group mb-3">
                        <label for="role" class="form-label">Роль *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="employee">Сотрудник</option>
                            <option value="admin">Администратор</option>
                            <?php if (isSupervisor()): ?>
                            <option value="supervisor">Руководитель</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Создать пользователя
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно сброса пароля -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key"></i> Сброс пароля</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="resetPasswordForm" class="validated-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                    <div class="alert alert-info">
                        <strong>Пользователь:</strong> <span id="reset_user_name"></span>
                    </div>

                    <div class="form-group mb-3">
                        <label for="new_password" class="form-label">Новый пароль *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required 
                               data-min-length="6" placeholder="Минимум 6 символов">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-key"></i> Изменить пароль
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Функция изменения статуса пользователя
function toggleUserStatus(userId, userName, currentStatus) {
    const newStatus = currentStatus ? 'неактивным' : 'активным';
    if (confirm('Сделать пользователя "' + userName + '" ' + newStatus + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_user_status">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Функция сброса пароля
function resetPassword(userId, userName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_user_name').textContent = userName;
}

// Функция удаления пользователя
function deleteUser(userId, userName) {
    if (confirm('Вы уверены, что хотите удалить пользователя "' + userName + '"?\n\nЭто действие нельзя отменить!')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
