<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/health-platform/public/index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors[] = 'CSRF 驗證失敗，請重新嘗試。';
    }
    if ($name === '') {
        $errors[] = '請輸入姓名。';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '請輸入有效的 Email。';
    }
    if (strlen($password) < 6) {
        $errors[] = '密碼長度至少 6 碼。';
    }

    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = '此 Email 已被註冊。';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
            $stmt->execute([$email, $hash, $name]);
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
    <label>姓名
        <input type="text" name="name" value="<?php echo e($_POST['name'] ?? ''); ?>" required />
    </label>
    <label>Email
        <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required />
    </label>
    <label>密碼
        <input type="password" name="password" required />
    </label>
    <button class="btn" type="submit">建立帳號</button>
    <p class="muted">已經有帳號了嗎？<a href="/health-platform/public/login.php">登入</a></p>
    </form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

