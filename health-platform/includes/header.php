<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="/health-platform/assets/css/styles.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <div class="logo">🏥 <?php echo e(APP_NAME); ?></div>
            <nav class="nav">
                <?php if (is_logged_in()): ?>
                    <a class="btn" href="/health-platform/public/index.php">首頁</a>
                    <form class="inline" method="post" action="/health-platform/public/logout.php">
                        <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                        <button class="btn btn-outline" type="submit">登出</button>
                    </form>
                <?php else: ?>
                    <a class="btn" href="/health-platform/public/login.php">登入</a>
                    <a class="btn btn-outline" href="/health-platform/public/register.php">註冊</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">

