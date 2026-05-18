<?php
require_once 'db.php';
session_start();

// Функции для cookies (те же)
function setJsonCookie($name, $data, $expire = 0) {
    setcookie($name, json_encode($data, JSON_UNESCAPED_UNICODE), $expire, '/', '', false, true);
}
function getJsonCookie($name) {
    if (isset($_COOKIE[$name])) {
        $data = json_decode($_COOKIE[$name], true);
        if (is_array($data)) return $data;
    }
    return null;
}
function deleteCookie($name) {
    setcookie($name, '', time() - 3600, '/');
}
function setSessionFlash($key, $data) {
    $_SESSION['flash'][$key] = $data;
}
function getSessionFlash($key) {
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['SCRIPT_NAME']);
    exit;
}

// Обработка входа пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $error = null;
    if ($login === '' || $password === '') {
        $error = 'Заполните оба поля.';
    } else {
        $pdo = connectToDatabase();
        $stmt = $pdo->prepare("SELECT id, login, password_hash FROM applications WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['authenticated'] = true;
            $_SESSION['app_id'] = $user['id'];
            $_SESSION['login'] = $user['login'];
            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
    setSessionFlash('login_error', $error);
    header('Location: ' . $_SERVER['SCRIPT_NAME']);
    exit;
}

// Обработка отправки формы (новой анкеты или обновления авторизованным пользователем)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'login')) {
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
    $isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

    if (!empty($errors)) {
        if ($isAuthenticated) {
            setSessionFlash('auth_errors', $errors);
            setSessionFlash('auth_input', $formData);
        } else {
            setJsonCookie('form_errors', $errors, 0);
            setJsonCookie('sticky_form_data', $formData, 0);
        }
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    }

    // Успешная валидация
    try {
        $pdo = connectToDatabase();
        if ($isAuthenticated) {
            // Обновление существующей записи
            $appId = $_SESSION['app_id'];
            saveApplication($appId, $formData);
            setSessionFlash('success_message', 'Данные успешно обновлены!');
            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        } else {
            // Новая анкета
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO applications 
                (full_name, phone, email, birth_date, gender, bio, contract_agreed)
                VALUES (:fn, :ph, :em, :bd, :gen, :bio, :ca)
            ");
            $stmt->execute([
                ':fn'  => $formData['full_name'],
                ':ph'  => $formData['phone'],
                ':em'  => $formData['email'],
                ':bd'  => $formData['birth_date'],
                ':gen' => $formData['gender'],
                ':bio' => $formData['bio'],
                ':ca'  => $formData['contract_agreed'] ? 1 : 0
            ]);
            $applicationId = $pdo->lastInsertId();

            // Языки
            $langRecords = getLanguageList();
            $languageMap = [];
            foreach ($langRecords as $lang) {
                $languageMap[$lang['name']] = $lang['id'];
            }
            $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($formData['languages'] as $langName) {
                if (isset($languageMap[$langName])) {
                    $linkStmt->execute([$applicationId, $languageMap[$langName]]);
                }
            }

            // Генерация логина/пароля
            $login = 'user_' . $applicationId . '_' . bin2hex(random_bytes(4));
            $plainPassword = bin2hex(random_bytes(6));
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $updStmt = $pdo->prepare("UPDATE applications SET login = ?, password_hash = ? WHERE id = ?");
            $updStmt->execute([$login, $passwordHash, $applicationId]);

            $pdo->commit();

            setJsonCookie('new_credentials', ['login' => $login, 'password' => $plainPassword], 0);
            unset($formData['contract_agreed']);
            setJsonCookie('default_form_data', $formData, time() + 365*24*3600);
            setJsonCookie('success_flash', ['message' => 'Анкета сохранена!'], 0);
            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        }
    } catch (Exception $e) {
        if (!$isAuthenticated) $pdo->rollBack();
        $errorMsg = 'Ошибка сохранения: ' . $e->getMessage();
        if ($isAuthenticated) {
            setSessionFlash('auth_errors', ['database' => $errorMsg]);
            setSessionFlash('auth_input', $formData);
        } else {
            setJsonCookie('form_errors', ['database' => $errorMsg], 0);
            setJsonCookie('sticky_form_data', $formData, 0);
        }
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    }
}

// GET-запрос – подготовка данных для формы
$formInput = [
    'full_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
    'gender' => '', 'bio' => '', 'contract_agreed' => false, 'languages' => []
];
$errorList = [];
$successMessage = '';
$credentialsMessage = null;
$loginError = getSessionFlash('login_error');
$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

if ($isAuthenticated) {
    $authErrors = getSessionFlash('auth_errors');
    $authInput = getSessionFlash('auth_input');
    if ($authErrors !== null && $authInput !== null) {
        $errorList = $authErrors;
        $formInput = $authInput;
    } else {
        $appId = $_SESSION['app_id'];
        $pdo = connectToDatabase();
        $stmt = $pdo->prepare("
            SELECT full_name, phone, email, birth_date, gender, bio, contract_agreed
            FROM applications WHERE id = ?
        ");
        $stmt->execute([$appId]);
        $dbData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dbData) {
            $formInput['full_name'] = $dbData['full_name'];
            $formInput['phone'] = $dbData['phone'];
            $formInput['email'] = $dbData['email'];
            $formInput['birth_date'] = $dbData['birth_date'];
            $formInput['gender'] = $dbData['gender'];
            $formInput['bio'] = $dbData['bio'];
            $formInput['contract_agreed'] = (bool)$dbData['contract_agreed'];

            $langStmt = $pdo->prepare("
                SELECT pl.name FROM application_languages al
                JOIN programming_languages pl ON al.language_id = pl.id
                WHERE al.application_id = ?
            ");
            $langStmt->execute([$appId]);
            $formInput['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    $successMessage = getSessionFlash('success_message') ?? '';
} else {
    $stickyErrors = getJsonCookie('form_errors');
    $stickyData = getJsonCookie('sticky_form_data');
    if ($stickyErrors !== null && $stickyData !== null) {
        $errorList = $stickyErrors;
        $formInput = $stickyData;
        deleteCookie('form_errors');
        deleteCookie('sticky_form_data');
    } else {
        $defaultData = getJsonCookie('default_form_data');
        if ($defaultData !== null) {
            $formInput = array_merge($formInput, $defaultData);
        }
    }
    $successMessage = getJsonCookie('success_flash')['message'] ?? '';
    if ($successMessage) deleteCookie('success_flash');

    $creds = getJsonCookie('new_credentials');
    if ($creds && isset($creds['login'], $creds['password'])) {
        $credentialsMessage = "Ваш логин: {$creds['login']}<br>Пароль: {$creds['password']}<br><strong>Сохраните их для редактирования!</strong>";
        deleteCookie('new_credentials');
    }
}

$languageOptions = getLanguageList();
if (empty($languageOptions)) {
    global $allowedLanguages;
    $languageOptions = array_map(function($name) {
        return ['id' => $name, 'name' => $name];
    }, $allowedLanguages);
}

require 'anketa.php';
?>