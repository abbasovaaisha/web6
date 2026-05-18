<?php
require_once 'db.php';

// HTTP-авторизация (та же, что в admin.php)
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

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('Неверный ID анкеты.');

$errors = [];
$formData = [];

// Если форма отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'full_name'       => trim($_POST['full_name'] ?? ''),
        'phone'           => trim($_POST['phone'] ?? ''),
        'email'           => trim($_POST['email'] ?? ''),
        'birth_date'      => trim($_POST['birth_date'] ?? ''),
        'gender'          => $_POST['gender'] ?? '',
        'bio'             => trim($_POST['bio'] ?? ''),
        'contract_agreed' => isset($_POST['contract_agreed']),
        'languages'       => $_POST['languages'] ?? []
    ];
    $errors = validateFormData($formData);
    if (empty($errors)) {
        saveApplication($id, $formData);
        header('Location: admin.php?msg=updated');
        exit;
    }
} else {
    // Загружаем данные анкеты
    $formData = getApplicationById($id);
    if (!$formData) die('Анкета не найдена.');
    // Приводим contract_agreed к boolean
    $formData['contract_agreed'] = (bool)$formData['contract_agreed'];
}

$languageOptions = getLanguageList();
if (empty($languageOptions)) {
    global $allowedLanguages;
    $languageOptions = array_map(function($name) {
        return ['id' => $name, 'name' => $name];
    }, $allowedLanguages);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование анкеты №<?= $id ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 800px; }
        .field-error { color: #e74c3c; font-size: 0.85rem; margin-top: 5px; }
        .has-error input, .has-error select, .has-error textarea { border-color: #e74c3c; background-color: #fff5f5; }
    </style>
</head>
<body>
<div class="container">
    <h1>✏️ Редактирование анкеты #<?= $id ?></h1>
    <?php if (!empty($errors)): ?>
        <div class="alert error">⚠️ Исправьте ошибки в форме.</div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
            <label>ФИО *</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($formData['full_name']) ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <div class="field-error"><?= $errors['full_name'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
            <label>Телефон *</label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>" required>
            <?php if (isset($errors['phone'])): ?>
                <div class="field-error"><?= $errors['phone'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
            <label>Email *</label>
            <input type="email" name="email" value="<?= htmlspecialchars($formData['email']) ?>" required>
            <?php if (isset($errors['email'])): ?>
                <div class="field-error"><?= $errors['email'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['birth_date']) ? 'has-error' : '' ?>">
            <label>Дата рождения *</label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($formData['birth_date']) ?>" required>
            <?php if (isset($errors['birth_date'])): ?>
                <div class="field-error"><?= $errors['birth_date'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['gender']) ? 'has-error' : '' ?>">
            <label>Пол *</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $formData['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $formData['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="field-error"><?= $errors['gender'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['languages']) ? 'has-error' : '' ?>">
            <label>Любимые языки *</label>
            <select name="languages[]" multiple size="6" required>
                <?php foreach ($languageOptions as $lang): ?>
                    <option value="<?= htmlspecialchars($lang['name']) ?>" <?= in_array($lang['name'], $formData['languages']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <div class="field-error"><?= $errors['languages'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group <?= isset($errors['bio']) ? 'has-error' : '' ?>">
            <label>Биография</label>
            <textarea name="bio" rows="5"><?= htmlspecialchars($formData['bio']) ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="field-error"><?= $errors['bio'] ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group checkbox-group">
            <label><input type="checkbox" name="contract_agreed" <?= $formData['contract_agreed'] ? 'checked' : '' ?> required> С контрактом ознакомлен *</label>
        </div>
        <button type="submit" class="submit-btn">Сохранить изменения</button>
        <a href="admin.php" style="display: inline-block; margin-left: 20px;">Отмена</a>
    </form>
</div>
</body>
</html>