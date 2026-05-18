<?php
$db_user = 'u82462';
$db_pass = '9164341';
$db_name = 'u82462';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("
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
        GROUP BY a.id
        ORDER BY a.id DESC
    ");
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Ошибка: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сохранённые анкеты</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .table-wrapper {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-top: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }
        th, td {
            border: 1px solid #e1e5e9;
            padding: 12px 15px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .bio-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .back-link {
            text-align: center;
            margin-top: 30px;
        }
        .back-link a {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .back-link a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        .container {
            max-width: 1400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Сохранённые анкеты</h1>

        <?php if (empty($applications)): ?>
            <div class="empty-message">
                <p>Пока нет ни одной сохранённой анкеты.</p>
                <p><a href="index.php" style="color: #667eea;">Заполните первую анкету</a></p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Пол</th>
                            <th>Биография</th>
                            <th>Контракт</th>
                            <th>Любимые ЯП</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= htmlspecialchars($app['id']) ?></td>
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= htmlspecialchars($app['birth_date']) ?></td>
                            <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                            <td class="bio-cell" title="<?= htmlspecialchars($app['bio']) ?>">
                                <?= nl2br(htmlspecialchars($app['bio'] ?: '—')) ?>
                            </td>
                            <td><?= $app['contract_agreed'] ? '✅ Да' : '❌ Нет' ?></td>
                            <td><?= htmlspecialchars($app['languages'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="index.php">← Вернуться к заполнению анкеты</a>
        </div>
    </div>
</body>
</html>