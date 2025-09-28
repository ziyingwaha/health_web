<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/health-platform/public/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $full_name = trim((string)($_POST['full_name'] ?? ''));
    $age = trim((string)($_POST['age'] ?? ''));
    $gender = strtoupper(trim((string)($_POST['gender'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors[] = 'CSRF 驗證失敗，請重新嘗試。';
    }
    if ($username === '' || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = '請輸入 3-50 碼英數或底線的帳號。';
    }
    if (strlen($password) < 6) {
        $errors[] = '密碼長度至少 6 碼。';
    }
    if ($gender !== '' && !in_array($gender, ['M','F'], true)) {
        $errors[] = '性別請輸入 M 或 F。';
    }
    if ($age !== '' && (!ctype_digit($age) || (int)$age < 0 || (int)$age > 130)) {
        $errors[] = '年齡格式不正確。';
    }

    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT username FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = '此帳號已被註冊。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, full_name, age, gender) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$username, $hash, $full_name !== '' ? $full_name : null, $age !== '' ? (int)$age : null, $gender !== '' ? $gender : null]);
            redirect('/health-platform/public/login.php?registered=1');
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1>註冊</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" class="card form">
    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
    <label>帳號 (username)
        <input type="text" name="username" value="<?php echo e($_POST['username'] ?? ''); ?>" required />
    </label>
    <label>密碼
        <input type="password" name="password" required />
    </label>
    <label>姓名 (可選)
        <input type="text" name="full_name" value="<?php echo e($_POST['full_name'] ?? ''); ?>" />
    </label>
    <div class="row">
        <label style="flex:1;">年齡 (可選)
            <input type="number" name="age" value="<?php echo e($_POST['age'] ?? ''); ?>" />
        </label>
        <label style="flex:1;">性別 (M/F，可選)
            <input type="text" name="gender" maxlength="1" value="<?php echo e($_POST['gender'] ?? ''); ?>" />
        </label>
    </div>
    <button class="btn" type="submit">建立帳號</button>
    <p class="muted">已經有帳號了嗎？<a href="/health-platform/public/login.php">登入</a></p>
    </form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

