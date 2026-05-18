<?php
// db.php – подключение к БД и общие функции

function connectToDatabase() {
    static $db = null;
    if ($db === null) {
        $host = 'localhost';
        $user = 'u82462';
        $pass = '9164341';
        $name = 'u82462';
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        try {
            $db = new PDO($dsn, $user, $pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            exit('Ошибка подключения к БД: ' . $e->getMessage());
        }
    }
    return $db;
}

function getLanguageList() {
    $pdo = connectToDatabase();
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Белые списки и примеры (используются в валидации)
$allowedLanguages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];
$allowedGenders = ['male', 'female'];

$fieldExamples = [
    'full_name' => 'Пример: Иванов Иван Иванович',
    'phone'     => 'Пример: +7 999 123-45-67',
    'email'     => 'Пример: ivanov@mail.ru',
    'birth_date'=> 'ГГГГ-ММ-ДД',
    'gender'    => 'Выберите вариант',
    'languages' => 'Выберите хотя бы один язык',
    'bio'       => 'До 10000 символов',
    'contract_agreed' => 'Требуется подтверждение'
];

// Функция валидации данных формы – возвращает массив ошибок
function validateFormData($formData) {
    global $allowedLanguages, $allowedGenders, $fieldExamples;
    $errors = [];

    // ФИО
    if ($formData['full_name'] === '') {
        $errors['full_name'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $formData['full_name'])) {
        $errors['full_name'] = 'Допустимы только буквы, пробелы и дефис. ' . $fieldExamples['full_name'];
    } elseif (strlen($formData['full_name']) > 150) {
        $errors['full_name'] = 'Максимальная длина — 150 символов.';
    } else {
        preg_match_all('/[a-zA-Zа-яА-ЯёЁ]/u', $formData['full_name'], $letters);
        if (count($letters[0]) < 2) {
            $errors['full_name'] = 'В имени должно быть не менее двух букв.';
        }
    }

    // Телефон
    if ($formData['phone'] === '') {
        $errors['phone'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^\+7[\s\(]*[0-9]{3}[\)\s]*[0-9]{3}[\s\-]*[0-9]{2}[\s\-]*[0-9]{2}$/', $formData['phone'])) {
        $errors['phone'] = 'Недопустимый формат. ' . $fieldExamples['phone'];
    } else {
        $digits = preg_replace('/\D/', '', $formData['phone']);
        if (strlen($digits) !== 11) {
            $errors['phone'] = 'Номер должен содержать ровно 11 цифр.';
        } elseif ($digits[0] !== '7') {
            $errors['phone'] = 'Номер должен начинаться с 7.';
        }
    }

    // Email
    if ($formData['email'] === '') {
        $errors['email'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $formData['email'])) {
        $errors['email'] = 'Некорректный email. ' . $fieldExamples['email'];
    }

    // Дата рождения
    if ($formData['birth_date'] === '') {
        $errors['birth_date'] = 'Поле обязательно для заполнения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['birth_date'])) {
        $errors['birth_date'] = 'Неверный формат. Используйте ГГГГ-ММ-ДД.';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $formData['birth_date']);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $formData['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата.';
        } elseif ($dateObj > new DateTime('today')) {
            $errors['birth_date'] = 'Дата не может быть в будущем.';
        }
    }

    // Пол
    if ($formData['gender'] === '') {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($formData['gender'], $allowedGenders)) {
        $errors['gender'] = 'Недопустимое значение.';
    }

    // Языки
    if (empty($formData['languages'])) {
        $errors['languages'] = 'Необходимо выбрать хотя бы один язык.';
    } else {
        foreach ($formData['languages'] as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Биография
    if (strlen($formData['bio']) > 10000) {
        $errors['bio'] = 'Текст слишком длинный (максимум 10000 символов).';
    }

    // Чекбокс
    if (!$formData['contract_agreed']) {
        $errors['contract_agreed'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    return $errors;
}

// Функция сохранения/обновления анкеты (для администратора)
function saveApplication($id, $formData) {
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("
        UPDATE applications 
        SET full_name = :fn, phone = :ph, email = :em, birth_date = :bd,
            gender = :gen, bio = :bio, contract_agreed = :ca
        WHERE id = :id
    ");
    $stmt->execute([
        ':fn'  => $formData['full_name'],
        ':ph'  => $formData['phone'],
        ':em'  => $formData['email'],
        ':bd'  => $formData['birth_date'],
        ':gen' => $formData['gender'],
        ':bio' => $formData['bio'],
        ':ca'  => $formData['contract_agreed'] ? 1 : 0,
        ':id'  => $id
    ]);

    // Обновление языков
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $langRecords = getLanguageList();
    $languageMap = [];
    foreach ($langRecords as $lang) {
        $languageMap[$lang['name']] = $lang['id'];
    }
    $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($formData['languages'] as $langName) {
        if (isset($languageMap[$langName])) {
            $linkStmt->execute([$id, $languageMap[$langName]]);
        }
    }
    return true;
}

// Функция получения анкеты по ID (для редактирования админом)
function getApplicationById($id) {
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare("
        SELECT id, full_name, phone, email, birth_date, gender, bio, contract_agreed
        FROM applications WHERE id = ?
    ");
    $stmt->execute([$id]);
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) return null;

    $langStmt = $pdo->prepare("
        SELECT pl.name FROM application_languages al
        JOIN programming_languages pl ON al.language_id = pl.id
        WHERE al.application_id = ?
    ");
    $langStmt->execute([$id]);
    $app['languages'] = $langStmt->fetchAll(PDO::FETCH_COLUMN);
    return $app;
}
?>