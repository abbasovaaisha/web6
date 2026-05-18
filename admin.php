<?php
require_once 'db.php';

// HTTP-авторизация
$auth_realm = 'Admin Panel';
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.0 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="' . $auth_realm . '"');
    echo 'Требуется авторизация';
    exit;
}

$login = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];

$pdo = connectToDatabase();
$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = ?");
$stmt->execute([$login]);
$admin = $stmt->fetch();
if (!$admin || !password_verify($password, $admin['password_hash'])) {
    header('HTTP/1.0 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="' . $auth_realm . '"');
    echo 'Неверный логин или пароль';
    exit;
}

// Удаление анкеты
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
    header('Location: admin.php?msg=deleted');
    exit;
}

// Получение всех анкет – исправленный GROUP BY
$applications = $pdo->query("
    SELECT 
        a.id,
        a.full_name,
        a.phone,
        a.email,
        a.birth_date,
        a.gender,
        a.bio,
        a.contract_agreed,
        GROUP_CONCAT(pl.name SEPARATOR ', ') AS languages
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY 
        a.id, a.full_name, a.phone, a.email, a.birth_date, a.gender, a.bio, a.contract_agreed
    ORDER BY a.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Статистика по языкам
$langStats = $pdo->query("
    SELECT pl.name, COUNT(al.application_id) AS cnt
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id
    ORDER BY cnt DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Функция для безопасного обрезания биографии (без mbstring)
function truncateBio($bio, $length = 100) {
    $clean = htmlspecialchars($bio);
    if (strlen($clean) > $length) {
        $clean = substr($clean, 0, $length) . '...';
    }
    return nl2br($clean);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 1400px; background: white; }
        .stats { background: #e9ecef; padding: 15px; border-radius: 10px; margin-bottom: 30px; }
        .stats h3 { margin-top: 0; }
        .stats ul { columns: 3; list-style: none; padding-left: 0; }
        .stats li { padding: 5px 0; }
        .actions a { margin-right: 10px; text-decoration: none; }
        .edit-btn { color: #3498db; }
        .delete-btn { color: #e74c3c; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; }
        .msg { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .bio-cell { max-width: 250px; word-break: break-word; }
    </style>
</head>
<body>
<div class="container">
    <h1>👑 Админ-панель</h1>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="msg">✅ Анкета успешно удалена.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="msg">✅ Анкета успешно обновлена.</div>
    <?php endif; ?>

    <div class="stats">
        <h3>📊 Статистика по языкам программирования</h3>
        <ul>
            <?php foreach ($langStats as $stat): ?>
                <li><strong><?= htmlspecialchars($stat['name']) ?>:</strong> <?= $stat['cnt'] ?> пользователей</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <h2>📋 Все анкеты (<?= count($applications) ?>)</h2>
    <?php if (empty($applications)): ?>
        <p>Нет ни одной анкеты.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Биография</th><th>Языки</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= htmlspecialchars($app['full_name']) ?></td>
                        <td><?= htmlspecialchars($app['phone']) ?></td>
                        <td><?= htmlspecialchars($app['email']) ?></td>
                        <td><?= htmlspecialchars($app['birth_date']) ?></td>
                        <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                        <td class="bio-cell"><?= truncateBio($app['bio'] ?? '') ?></td>
                        <td><?= htmlspecialchars($app['languages'] ?? '—') ?></td>
                        <td class="actions">
                            <a href="edit.php?id=<?= $app['id'] ?>" class="edit-btn">✏️ Редактировать</a>
                            <a href="admin.php?delete=<?= $app['id'] ?>" class="delete-btn" onclick="return confirm('Удалить анкету №<?= $app['id'] ?>?')">🗑️ Удалить</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="nav-links" style="margin-top: 30px;">
        <a href="index.php">← Вернуться к анкете</a>
    </div>
</div>
</body>
</html>