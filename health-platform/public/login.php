<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if (is_logged_in()) {
    redirect('/health-platform/public/index.php');
}

$errors = [];
$registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf_token'] ?? '');

    if (!verify_csrf($csrf)) {
        $errors[] = 'CSRF 驗證失敗，請重新嘗試。';
    }
    if ($username === '') {
        $errors[] = '請輸入帳號。';
    }
    if (!$errors) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT username, password, full_name FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, (string)$user['password'])) {
            $errors[] = '帳號或密碼錯誤。';
        } else {
            $_SESSION['username'] = (string)$user['username'];
            $_SESSION['full_name'] = (string)($user['full_name'] ?? '');
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
    <label>帳號 (username)
        <input type="text" name="username" value="<?php echo e($_POST['username'] ?? ''); ?>" required />
    </label>
    <label>密碼
        <input type="password" name="password" required />
    </label>
    <button class="btn" type="submit">登入</button>
    <p class="muted">還沒有帳號？<a href="/health-platform/public/register.php">註冊</a></p>
    </form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

