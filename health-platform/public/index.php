<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pdo = get_pdo();
$userId = current_user_id();

function fetch_latest_row(PDO $pdo, int $userId, string $metric): ?array {
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $stmt = $pdo->prepare('SELECT systolic, diastolic, pulse, recorded_at FROM blood_pressure_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        $stmt = $pdo->prepare('SELECT value, recorded_at FROM blood_sugar_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
    if ($metric === METRIC_HEART_RATE) {
        $stmt = $pdo->prepare('SELECT value, recorded_at FROM heart_rate_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

$latest = [
    METRIC_BLOOD_PRESSURE => fetch_latest_row($pdo, $userId, METRIC_BLOOD_PRESSURE),
    METRIC_BLOOD_SUGAR => fetch_latest_row($pdo, $userId, METRIC_BLOOD_SUGAR),
    METRIC_HEART_RATE => fetch_latest_row($pdo, $userId, METRIC_HEART_RATE),
];

include __DIR__ . '/../includes/header.php';
?>

<h1>您好，<?php echo e((string)($_SESSION['user_name'] ?? '')); ?>！</h1>
<p class="muted">在此紀錄與追蹤您的健康數據</p>

<div class="grid">
    <?php
    $cards = [
        METRIC_BLOOD_PRESSURE => ['title' => '血壓', 'desc' => '上壓/下壓，含脈搏'],
        METRIC_BLOOD_SUGAR => ['title' => '血糖', 'desc' => 'mg/dL'],
        METRIC_HEART_RATE => ['title' => '心率', 'desc' => '每分鐘心跳次數'],
    ];

    foreach ($cards as $metric => $meta):
        $row = $latest[$metric];
        $status = $row ? get_threshold_status($metric, $row) : 'normal';
        $statusText = $status === 'high' ? '偏高' : ($status === 'low' ? '偏低' : '正常');
        $colorClass = $status === 'high' ? 'status-high' : ($status === 'low' ? 'status-low' : 'status-normal');
        $detailUrl = '/health-platform/public/metric.php?metric=' . urlencode($metric);
    ?>
        <section class="card metric <?php echo e($colorClass); ?>">
            <header class="card-header">
                <h2><?php echo e($meta['title']); ?></h2>
                <a class="btn btn-small" href="<?php echo e($detailUrl); ?>">前往詳情</a>
            </header>
            <div class="card-body">
                <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
                    <p class="value">
                        <?php if ($row): ?>
                            <?php echo e((string)$row['systolic']); ?>/<?php echo e((string)$row['diastolic']); ?><?php if (!empty($row['pulse'])): ?>，脈搏 <?php echo e((string)$row['pulse']); ?><?php endif; ?>
                        <?php else: ?>
                            尚無紀錄
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="value">
                        <?php echo $row ? e((string)$row['value']) : '尚無紀錄'; ?>
                    </p>
                <?php endif; ?>
                <p class="muted small">
                    <?php echo $row ? e(date('Y-m-d H:i', strtotime((string)$row['recorded_at']))) : ''; ?>
                </p>
            </div>
            <footer class="card-footer">
                <span class="badge <?php echo e($colorClass); ?>"><?php echo e($statusText); ?></span>
                <div class="actions">
                    <a class="btn btn-outline btn-small" href="/health-platform/public/metric.php?metric=<?php echo e(urlencode($metric)); ?>&action=create">新增</a>
                    <a class="btn btn-outline btn-small" href="/health-platform/public/metric.php?metric=<?php echo e(urlencode($metric)); ?>">編輯/刪除</a>
                </div>
            </footer>
        </section>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

