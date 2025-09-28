<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

require_login();

$pdo = get_pdo();
$userId = current_user_id();

$metric = (string)($_GET['metric'] ?? '');
if (!validate_metric($metric)) {
    redirect('/health-platform/public/index.php');
}

$action = (string)($_GET['action'] ?? '');

function fetch_records(PDO $pdo, int $userId, string $metric): array
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $stmt = $pdo->prepare('SELECT id, systolic, diastolic, pulse, recorded_at FROM blood_pressure_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        $stmt = $pdo->prepare('SELECT id, value, recorded_at FROM blood_sugar_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    if ($metric === METRIC_HEART_RATE) {
        $stmt = $pdo->prepare('SELECT id, value, recorded_at FROM heart_rate_records WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 100');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    return [];
}

function insert_record(PDO $pdo, int $userId, string $metric, array $data): void
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $stmt = $pdo->prepare('INSERT INTO blood_pressure_records (user_id, systolic, diastolic, pulse, recorded_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$userId, (int)$data['systolic'], (int)$data['diastolic'], $data['pulse'] !== '' ? (int)$data['pulse'] : null, $data['recorded_at']]);
        return;
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        $stmt = $pdo->prepare('INSERT INTO blood_sugar_records (user_id, value, recorded_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, (float)$data['value'], $data['recorded_at']]);
        return;
    }
    if ($metric === METRIC_HEART_RATE) {
        $stmt = $pdo->prepare('INSERT INTO heart_rate_records (user_id, value, recorded_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, (int)$data['value'], $data['recorded_at']]);
        return;
    }
}

function update_record(PDO $pdo, int $userId, string $metric, int $id, array $data): void
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $stmt = $pdo->prepare('UPDATE blood_pressure_records SET systolic = ?, diastolic = ?, pulse = ?, recorded_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$data['systolic'], (int)$data['diastolic'], $data['pulse'] !== '' ? (int)$data['pulse'] : null, $data['recorded_at'], $id, $userId]);
        return;
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        $stmt = $pdo->prepare('UPDATE blood_sugar_records SET value = ?, recorded_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([(float)$data['value'], $data['recorded_at'], $id, $userId]);
        return;
    }
    if ($metric === METRIC_HEART_RATE) {
        $stmt = $pdo->prepare('UPDATE heart_rate_records SET value = ?, recorded_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$data['value'], $data['recorded_at'], $id, $userId]);
        return;
    }
}

function delete_record(PDO $pdo, int $userId, string $metric, int $id): void
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $stmt = $pdo->prepare('DELETE FROM blood_pressure_records WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return;
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        $stmt = $pdo->prepare('DELETE FROM blood_sugar_records WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return;
    }
    if ($metric === METRIC_HEART_RATE) {
        $stmt = $pdo->prepare('DELETE FROM heart_rate_records WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return;
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'CSRF 驗證失敗，請重新嘗試。';
    } else {
        $formAction = (string)($_POST['form_action'] ?? '');
        if ($formAction === 'create') {
            $data = $_POST;
            insert_record($pdo, $userId, $metric, $data);
        } elseif ($formAction === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $data = $_POST;
            update_record($pdo, $userId, $metric, $id, $data);
        } elseif ($formAction === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            delete_record($pdo, $userId, $metric, $id);
        }
        redirect('/health-platform/public/metric.php?metric=' . urlencode($metric));
    }
}

$records = fetch_records($pdo, $userId, $metric);

include __DIR__ . '/../includes/header.php';

$pageTitle = $metric === METRIC_BLOOD_PRESSURE ? '血壓' : ($metric === METRIC_BLOOD_SUGAR ? '血糖' : '心率');
?>

<a class="btn btn-outline" href="/health-platform/public/index.php">← 返回首頁</a>
<h1><?php echo e($pageTitle); ?>詳情</h1>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="card">
    <header class="card-header">
        <h2>趨勢圖</h2>
    </header>
    <div class="card-body">
        <canvas id="metricChart" height="120"></canvas>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2>新增紀錄</h2>
    </header>
    <div class="card-body">
        <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
            <input type="hidden" name="form_action" value="create" />
            <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
                <div class="row">
                    <label>上壓
                        <input type="number" name="systolic" required />
                    </label>
                    <label>下壓
                        <input type="number" name="diastolic" required />
                    </label>
                    <label>脈搏
                        <input type="number" name="pulse" />
                    </label>
                </div>
            <?php else: ?>
                <label>數值
                    <input type="number" name="value" step="0.1" required />
                </label>
            <?php endif; ?>
            <label>紀錄時間
                <input type="datetime-local" name="recorded_at" value="<?php echo e(date('Y-m-d\TH:i')); ?>" required />
            </label>
            <button class="btn" type="submit">新增</button>
        </form>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2>歷史紀錄</h2>
    </header>
    <div class="card-body table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>時間</th>
                    <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
                        <th>上壓</th>
                        <th>下壓</th>
                        <th>脈搏</th>
                    <?php else: ?>
                        <th>數值</th>
                    <?php endif; ?>
                    <th>狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $rec): ?>
                    <?php $status = get_threshold_status($metric, $rec); ?>
                    <tr>
                        <td><?php echo e(date('Y-m-d H:i', strtotime((string)$rec['recorded_at']))); ?></td>
                        <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
                            <td><?php echo e((string)$rec['systolic']); ?></td>
                            <td><?php echo e((string)$rec['diastolic']); ?></td>
                            <td><?php echo e(isset($rec['pulse']) ? (string)$rec['pulse'] : ''); ?></td>
                        <?php else: ?>
                            <td><?php echo e((string)$rec['value']); ?></td>
                        <?php endif; ?>
                        <td>
                            <span class="badge <?php echo e($status === 'high' ? 'status-high' : ($status === 'low' ? 'status-low' : 'status-normal')); ?>">
                                <?php echo e($status === 'high' ? '偏高' : ($status === 'low' ? '偏低' : '正常')); ?>
                            </span>
                        </td>
                        <td>
                            <details>
                                <summary>編輯/刪除</summary>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                                    <input type="hidden" name="form_action" value="update" />
                                    <input type="hidden" name="id" value="<?php echo e((string)$rec['id']); ?>" />
                                    <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
                                        <input type="number" name="systolic" value="<?php echo e((string)$rec['systolic']); ?>" />
                                        <input type="number" name="diastolic" value="<?php echo e((string)$rec['diastolic']); ?>" />
                                        <input type="number" name="pulse" value="<?php echo e(isset($rec['pulse']) ? (string)$rec['pulse'] : ''); ?>" />
                                    <?php else: ?>
                                        <input type="number" step="0.1" name="value" value="<?php echo e((string)$rec['value']); ?>" />
                                    <?php endif; ?>
                                    <input type="datetime-local" name="recorded_at" value="<?php echo e(date('Y-m-d\TH:i', strtotime((string)$rec['recorded_at']))); ?>" />
                                    <button class="btn btn-small" type="submit">更新</button>
                                </form>
                                <form method="post" class="inline" onsubmit="return confirm('確定要刪除嗎？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>" />
                                    <input type="hidden" name="form_action" value="delete" />
                                    <input type="hidden" name="id" value="<?php echo e((string)$rec['id']); ?>" />
                                    <button class="btn btn-outline btn-small" type="submit">刪除</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2>生活建議</h2>
    </header>
    <div class="card-body">
        <?php
        $latest = $records[0] ?? null;
        if ($latest) {
            $status = get_threshold_status($metric, $latest);
            $suggestions = get_suggestions($metric, $status);
            if ($suggestions) {
                echo '<ul class="suggest">';
                foreach ($suggestions as $s) {
                    echo '<li>' . e($s) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="muted">目前狀態正常，持續保持良好生活習慣。</p>';
            }
        } else {
            echo '<p class="muted">尚無資料，請先新增紀錄。</p>';
        }
        ?>
    </div>
</section>

<script>
const ctx = document.getElementById('metricChart');
const metric = <?php echo json_encode($metric); ?>;
const records = <?php echo json_encode($records, JSON_UNESCAPED_UNICODE); ?>;

function buildDatasets(metric, records) {
    const labels = records.map(r => r.recorded_at).reverse();
    if (metric === '<?php echo METRIC_BLOOD_PRESSURE; ?>') {
        const systolic = records.map(r => Number(r.systolic)).reverse();
        const diastolic = records.map(r => Number(r.diastolic)).reverse();
        return {
            labels,
            datasets: [
                {label: '上壓', data: systolic, borderColor: '#e11d48', backgroundColor: 'rgba(225,29,72,0.2)'},
                {label: '下壓', data: diastolic, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.2)'}
            ]
        };
    }
    const values = records.map(r => Number(r.value)).reverse();
    return { labels, datasets: [{ label: metric === '<?php echo METRIC_BLOOD_SUGAR; ?>' ? '血糖' : '心率', data: values, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.2)'}] };
}

const data = buildDatasets(metric, records);
new Chart(ctx, {
    type: 'line',
    data,
    options: {
        responsive: true,
        scales: {
            x: { ticks: { maxRotation: 0 } },
            y: { beginAtZero: false }
        },
        interaction: { intersect: false, mode: 'index' },
        plugins: { legend: { position: 'top' } }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

