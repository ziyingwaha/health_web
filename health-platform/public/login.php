<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/health-platform/public/index.php');
}

$errors = [];
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors[] = 'CSRF 驗證失敗，請重新嘗試。';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '請輸入有效的 Email。';
    }
    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, password_hash, name FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $errors[] = 'Email 或密碼錯誤。';
        } else {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = (string)$user['name'];
            redirect('/health-platform/public/index.php');
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<h1>登入</h1>

<?php if ($registered): ?>
    <div class="alert alert-success">註冊成功，請登入。</div>
<?php endif; ?>

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
    <label>Email
        <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>" required />
    </label>
    <label>密碼
        <input type="password" name="password" required />
    </label>
    <button class="btn" type="submit">登入</button>
    <p class="muted">還沒有帳號？<a href="/health-platform/public/register.php">註冊</a></p>
    </form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

