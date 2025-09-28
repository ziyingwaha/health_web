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

// Compute weekly averages (group by ISO week) and high/low counts
$weekly = [];
$highCount = 0; $lowCount = 0;
foreach ($records as $r) {
    $class = classify_metric_detail($metric, $r);
    if ($class['status'] === 'high') $highCount++;
    if ($class['status'] === 'low') $lowCount++;

    $ts = strtotime((string)$r['recorded_at']);
    $year = (int)date('o', $ts); // ISO year
    $week = (int)date('W', $ts); // ISO week number
    $key = $year . '-W' . str_pad((string)$week, 2, '0', STR_PAD_LEFT);
    if (!isset($weekly[$key])) {
        $weekly[$key] = [
            'count' => 0,
            'systolic_sum' => 0,
            'diastolic_sum' => 0,
            'value_sum' => 0,
        ];
    }
    $weekly[$key]['count']++;
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $weekly[$key]['systolic_sum'] += (int)$r['systolic'];
        $weekly[$key]['diastolic_sum'] += (int)$r['diastolic'];
    } else {
        $weekly[$key]['value_sum'] += (float)$r['value'];
    }
}

ksort($weekly);
$weeklyLabels = array_keys($weekly);
$weeklyAvg1 = [];
$weeklyAvg2 = [];
foreach ($weekly as $w) {
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $weeklyAvg1[] = $w['count'] ? round($w['systolic_sum'] / $w['count'], 1) : null; // systolic
        $weeklyAvg2[] = $w['count'] ? round($w['diastolic_sum'] / $w['count'], 1) : null; // diastolic
    } else {
        $weeklyAvg1[] = $w['count'] ? round($w['value_sum'] / $w['count'], 1) : null; // single value
    }
}

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
        <h2>每週平均 vs 標準範圍</h2>
    </header>
    <div class="card-body">
        <canvas id="weeklyChart" height="110"></canvas>
        <div class="muted small">標準範圍依據醫學建議供參考，實際請諮詢醫師。</div>
    </div>
</section>

<section class="card">
    <header class="card-header">
        <h2>超標次數統計</h2>
    </header>
    <div class="card-body">
        <div class="stats">
            <span class="badge status-high">偏高：<?php echo e((string)$highCount); ?> 次</span>
            <span class="badge status-low" style="margin-left:8px;">偏低：<?php echo e((string)$lowCount); ?> 次</span>
            <?php
            $latest = $records[0] ?? null;
            if ($latest) {
                $cls = classify_metric_detail($metric, $latest);
                if ($cls['level'] === 'severe') {
                    echo '<div class="alert alert-danger" style="margin-top:12px;">紅燈提醒：' . e($cls['label']) . '，請儘速注意與就醫。</div>';
                }
            }
            ?>
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
            $detail = classify_metric_detail($metric, $latest);
            $suggestions = get_suggestions($metric, $detail['status']);
            if ($suggestions) {
                echo '<div class="badge ' . e($detail['colorClass']) . '" style="margin-bottom:8px;">' . e($detail['label']) . '</div>';
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

<section class="card">
    <header class="card-header">
        <h2>醫學建議對照表</h2>
    </header>
    <div class="card-body table-responsive">
        <?php if ($metric === METRIC_BLOOD_PRESSURE): ?>
            <table class="table">
                <thead><tr><th>分類</th><th>收縮壓/舒張壓 (mmHg)</th><th>顏色/程度</th><th>建議</th></tr></thead>
                <tbody>
                    <tr><td>低血壓</td><td><90 / <60</td><td>紅色/嚴重</td><td>建議就醫</td></tr>
                    <tr><td>正常</td><td>90–119 / 60–79</td><td>綠色/正常</td><td>維持生活習慣</td></tr>
                    <tr><td>輕度偏高</td><td>120–129 / <80</td><td>黃色/輕度</td><td>減鹽、控制作息</td></tr>
                    <tr><td>中度高血壓</td><td>130–139 / 80–89</td><td>橘色/中度</td><td>減重、監測血壓</td></tr>
                    <tr><td>嚴重高血壓</td><td>≥140 / ≥90</td><td>紅色/嚴重</td><td>儘速就醫</td></tr>
                </tbody>
            </table>
        <?php elseif ($metric === METRIC_BLOOD_SUGAR): ?>
            <table class="table">
                <thead><tr><th>分類</th><th>血糖 (mg/dL)</th><th>顏色/程度</th><th>建議</th></tr></thead>
                <tbody>
                    <tr><td>低血糖</td><td><70</td><td>紅色/嚴重</td><td>立即補充糖分，並就醫</td></tr>
                    <tr><td>正常</td><td>70–99</td><td>綠色/正常</td><td>規律飲食</td></tr>
                    <tr><td>輕度偏高</td><td>100–125</td><td>黃色/輕度</td><td>控制碳水、減少含糖</td></tr>
                    <tr><td>嚴重高血糖</td><td>≥126</td><td>紅色/嚴重</td><td>就醫檢查 HbA1c</td></tr>
                </tbody>
            </table>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>分類</th><th>心率 (bpm)</th><th>顏色/程度</th><th>建議</th></tr></thead>
                <tbody>
                    <tr><td>低心率</td><td><50</td><td>紅色/嚴重</td><td>若有頭暈，儘速就醫</td></tr>
                    <tr><td>輕度低心率</td><td>50–59</td><td>黃色/輕度</td><td>觀察是否運動員體質</td></tr>
                    <tr><td>正常</td><td>60–100</td><td>綠色/正常</td><td>維持運動與睡眠</td></tr>
                    <tr><td>輕度偏高</td><td>101–110</td><td>黃色/輕度</td><td>減咖啡因、減壓</td></tr>
                    <tr><td>中度偏高</td><td>111–130</td><td>橘色/中度</td><td>留意心悸，建議檢查</td></tr>
                    <tr><td>嚴重偏高</td><td>>130</td><td>紅色/嚴重</td><td>立即就醫</td></tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<script>
const ctx = document.getElementById('metricChart');
const metric = <?php echo json_encode($metric); ?>;
const records = <?php echo json_encode($records, JSON_UNESCAPED_UNICODE); ?>;
const weeklyLabels = <?php echo json_encode($weeklyLabels, JSON_UNESCAPED_UNICODE); ?>;
const weeklyAvg1 = <?php echo json_encode($weeklyAvg1); ?>;
const weeklyAvg2 = <?php echo json_encode($weeklyAvg2); ?>;
const normalRange = <?php echo json_encode(get_normal_range($metric)); ?>;

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

// Weekly averages vs normal range
const wctx = document.getElementById('weeklyChart');
function buildWeekly(metric) {
    const datasets = [];
    if (metric === '<?php echo METRIC_BLOOD_PRESSURE; ?>') {
        datasets.push({ label: '每週上壓平均', data: weeklyAvg1, borderColor: '#e11d48', backgroundColor: 'rgba(225,29,72,0.2)' });
        datasets.push({ label: '每週下壓平均', data: weeklyAvg2, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.2)' });
        datasets.push({ label: '上壓標準下限', data: weeklyAvg1.map(()=> normalRange.systolic[0]), borderDash:[6,4], borderColor:'#94a3b8', pointRadius:0 });
        datasets.push({ label: '上壓標準上限', data: weeklyAvg1.map(()=> normalRange.systolic[1]), borderDash:[6,4], borderColor:'#94a3b8', pointRadius:0 });
        datasets.push({ label: '下壓標準下限', data: weeklyAvg1.map(()=> normalRange.diastolic[0]), borderDash:[6,4], borderColor:'#cbd5e1', pointRadius:0 });
        datasets.push({ label: '下壓標準上限', data: weeklyAvg1.map(()=> normalRange.diastolic[1]), borderDash:[6,4], borderColor:'#cbd5e1', pointRadius:0 });
    } else {
        datasets.push({ label: '每週平均', data: weeklyAvg1, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.2)' });
        const range = normalRange.value;
        datasets.push({ label: '標準下限', data: weeklyAvg1.map(()=> range[0]), borderDash:[6,4], borderColor:'#94a3b8', pointRadius:0 });
        datasets.push({ label: '標準上限', data: weeklyAvg1.map(()=> range[1]), borderDash:[6,4], borderColor:'#94a3b8', pointRadius:0 });
    }
    return { labels: weeklyLabels, datasets };
}

new Chart(wctx, {
    type: 'line',
    data: buildWeekly(metric),
    options: { responsive: true, interaction: { intersect:false, mode:'index' }, plugins: { legend: { position: 'top' } } }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

