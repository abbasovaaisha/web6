<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Анкета – Задание 6</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-panel {
            background: #f0f4ff;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .auth-form input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .auth-form button {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
        }
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
        }
        .credentials-box {
            background: #d4edda;
            border-left: 5px solid #28a745;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .nav-links {
            margin-top: 30px;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .nav-links a {
            display: inline-block;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .nav-links a:hover {
            background-color: #2980b9;
        }
        .field-error {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .has-error input, .has-error select, .has-error textarea {
            border-color: #e74c3c !important;
            background-color: #fff5f5;
        }
        .field-hint {
            color: #666;
            font-size: 0.8rem;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Заполните анкету</h1>

    <?php if ($loginError): ?>
        <div class="alert error">❌ <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>

    <?php if ($credentialsMessage): ?>
        <div class="credentials-box">
            🔐 <strong>Ваши учётные данные для редактирования:</strong><br>
            <?= $credentialsMessage ?>
        </div>
    <?php endif; ?>

    <?php if ($successMessage !== ''): ?>
        <div class="alert success">✅ <?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <?php if (isset($errorList['database'])): ?>
        <div class="alert error">❌ <?= htmlspecialchars($errorList['database']) ?></div>
    <?php endif; ?>

    <?php if ($isAuthenticated): ?>
        <div class="auth-panel">
            <span>👋 Вы вошли как <strong><?= htmlspecialchars($_SESSION['login']) ?></strong></span>
            <a href="?logout=1" class="logout-btn">🚪 Выйти</a>
        </div>
    <?php else: ?>
        <div class="auth-panel">
            <form method="post" class="auth-form">
                <input type="hidden" name="action" value="login">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Войти для редактирования</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="application-form">
        <div class="form-group <?= isset($errorList['full_name']) ? 'has-error' : '' ?>">
            <label for="full_name">ФИО *</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($formInput['full_name']) ?>" maxlength="150" required>
            <?php if (isset($errorList['full_name'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['full_name']) ?></div>
            <?php else: ?>
                <div class="field-hint"><?= $fieldExamples['full_name'] ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['phone']) ? 'has-error' : '' ?>">
            <label for="phone">Телефон *</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($formInput['phone']) ?>" required>
            <?php if (isset($errorList['phone'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['phone']) ?></div>
            <?php else: ?>
                <div class="field-hint"><?= $fieldExamples['phone'] ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['email']) ? 'has-error' : '' ?>">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($formInput['email']) ?>" required>
            <?php if (isset($errorList['email'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['email']) ?></div>
            <?php else: ?>
                <div class="field-hint"><?= $fieldExamples['email'] ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['birth_date']) ? 'has-error' : '' ?>">
            <label for="birth_date">Дата рождения *</label>
            <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($formInput['birth_date']) ?>" required>
            <?php if (isset($errorList['birth_date'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['birth_date']) ?></div>
            <?php else: ?>
                <div class="field-hint"><?= $fieldExamples['birth_date'] ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['gender']) ? 'has-error' : '' ?>">
            <label>Пол *</label>
            <div class="radio-group">
                <label class="radio-label"><input type="radio" name="gender" value="male" <?= $formInput['gender'] === 'male' ? 'checked' : '' ?> required> Мужской</label>
                <label class="radio-label"><input type="radio" name="gender" value="female" <?= $formInput['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errorList['gender'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['languages']) ? 'has-error' : '' ?>">
            <label for="languages">Любимые языки программирования *</label>
            <select id="languages" name="languages[]" multiple size="6" required>
                <?php foreach ($languageOptions as $langOption): ?>
                    <?php $selected = in_array($langOption['name'], $formInput['languages']); ?>
                    <option value="<?= htmlspecialchars($langOption['name']) ?>" <?= $selected ? 'selected' : '' ?>><?= htmlspecialchars($langOption['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Для выбора нескольких пунктов удерживайте Ctrl (или Cmd на Mac)</small>
            <?php if (isset($errorList['languages'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['languages']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group <?= isset($errorList['bio']) ? 'has-error' : '' ?>">
            <label for="bio">Биография</label>
            <textarea id="bio" name="bio" rows="5"><?= htmlspecialchars($formInput['bio']) ?></textarea>
            <?php if (isset($errorList['bio'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['bio']) ?></div>
            <?php else: ?>
                <div class="field-hint"><?= $fieldExamples['bio'] ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group checkbox-group <?= isset($errorList['contract_agreed']) ? 'has-error' : '' ?>">
            <label class="checkbox-label">
                <input type="checkbox" name="contract_agreed" <?= $formInput['contract_agreed'] ? 'checked' : '' ?> required>
                С контрактом ознакомлен(а) *
            </label>
            <?php if (isset($errorList['contract_agreed'])): ?>
                <div class="field-error">⚠️ <?= htmlspecialchars($errorList['contract_agreed']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="submit-btn"><?= $isAuthenticated ? 'Обновить данные' : 'Сохранить' ?></button>
    </form>

    <div class="nav-links">
        <a href="view.php">📋 Просмотр сохранённых анкет</a>
        <a href="admin.php" style="background-color:#e67e22;">🔐 Админ-панель</a>
    </div>
</div>
</body>
</html>