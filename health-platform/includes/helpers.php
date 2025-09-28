<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /health-platform/public/login.php');
        exit;
    }
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    return (string)($_SESSION['csrf_token'] ?? '');
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token);
}

function validate_metric(string $metric): bool
{
    return in_array($metric, METRICS, true);
}

function get_threshold_status(string $metric, array $row): string
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        $sys = isset($row['systolic']) ? (int)$row['systolic'] : null;
        $dia = isset($row['diastolic']) ? (int)$row['diastolic'] : null;
        if ($sys === null || $dia === null) {
            return 'normal';
        }
        if ($sys >= THRESHOLDS[METRIC_BLOOD_PRESSURE]['high']['systolic'] || $dia >= THRESHOLDS[METRIC_BLOOD_PRESSURE]['high']['diastolic']) {
            return 'high';
        }
        if ($sys < THRESHOLDS[METRIC_BLOOD_PRESSURE]['low']['systolic'] || $dia < THRESHOLDS[METRIC_BLOOD_PRESSURE]['low']['diastolic']) {
            return 'low';
        }
        return 'normal';
    }

    if ($metric === METRIC_BLOOD_SUGAR) {
        $val = isset($row['value']) ? (float)$row['value'] : null;
        if ($val === null) {
            return 'normal';
        }
        if ($val >= THRESHOLDS[METRIC_BLOOD_SUGAR]['high']) {
            return 'high';
        }
        if ($val < THRESHOLDS[METRIC_BLOOD_SUGAR]['low']) {
            return 'low';
        }
        return 'normal';
    }

    if ($metric === METRIC_HEART_RATE) {
        $val = isset($row['value']) ? (int)$row['value'] : null;
        if ($val === null) {
            return 'normal';
        }
        if ($val > THRESHOLDS[METRIC_HEART_RATE]['high']) {
            return 'high';
        }
        if ($val < THRESHOLDS[METRIC_HEART_RATE]['low']) {
            return 'low';
        }
        return 'normal';
    }

    return 'normal';
}

function get_suggestions(string $metric, string $status): array
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        if ($status === 'high') {
            return ['減少鈉鹽攝取', '適度運動與放鬆', '保持充足睡眠', '如持續偏高請諮詢醫師'];
        }
        if ($status === 'low') {
            return ['補充水分', '緩慢起身避免暈眩', '必要時增加鹽分攝取', '如不適請就醫'];
        }
    } elseif ($metric === METRIC_BLOOD_SUGAR) {
        if ($status === 'high') {
            return ['控制澱粉與甜食', '分散多餐', '規律運動', '遵循醫囑調整用藥'];
        }
        if ($status === 'low') {
            return ['先補充含糖飲食', '規律飲食避免空腹過久', '留意低血糖症狀'];
        }
    } elseif ($metric === METRIC_HEART_RATE) {
        if ($status === 'high') {
            return ['放慢呼吸、休息', '避免咖啡因與菸酒', '觀察是否伴隨胸悶心悸', '持續不適請就醫'];
        }
        if ($status === 'low') {
            return ['先休息觀察', '若頭暈乏力請就醫', '檢視是否藥物影響'];
        }
    }
    return [];
}

/**
 * Detailed classification with multi-level severity and labels per metric.
 * Returns: [status: high|low|normal, level: severe|moderate|mild|normal, label: string, colorClass: string]
 */
function classify_metric_detail(string $metric, array $row): array
{
    $result = ['status' => 'normal', 'level' => 'normal', 'label' => '正常', 'colorClass' => 'status-normal'];

    if ($metric === METRIC_BLOOD_PRESSURE) {
        $sys = isset($row['systolic']) ? (int)$row['systolic'] : null;
        $dia = isset($row['diastolic']) ? (int)$row['diastolic'] : null;
        if ($sys === null || $dia === null) {
            return $result;
        }
        // Determine category for systolic
        $catSys = 'normal';
        if ($sys < 90) $catSys = 'severe_low';
        elseif ($sys <= 119) $catSys = 'normal';
        elseif ($sys <= 129) $catSys = 'mild_high';
        elseif ($sys <= 139) $catSys = 'moderate_high';
        else $catSys = 'severe_high';

        // Determine category for diastolic
        $catDia = 'normal';
        if ($dia < 60) $catDia = 'severe_low';
        elseif ($dia <= 79) $catDia = 'normal';
        elseif ($dia <= 89) $catDia = 'moderate_high'; // no mild for diastolic per table
        else $catDia = 'severe_high';

        // Pick worst severity among the two
        $priority = ['severe_low' => 4, 'severe_high' => 4, 'moderate_high' => 3, 'mild_high' => 2, 'normal' => 1];
        $picked = $priority[$catSys] >= $priority[$catDia] ? $catSys : $catDia;

        switch ($picked) {
            case 'severe_low':
                return ['status' => 'low', 'level' => 'severe', 'label' => '低血壓（嚴重）', 'colorClass' => 'status-severe'];
            case 'severe_high':
                return ['status' => 'high', 'level' => 'severe', 'label' => '嚴重高血壓', 'colorClass' => 'status-severe'];
            case 'moderate_high':
                return ['status' => 'high', 'level' => 'moderate', 'label' => '中度高血壓', 'colorClass' => 'status-moderate'];
            case 'mild_high':
                return ['status' => 'high', 'level' => 'mild', 'label' => '輕度偏高', 'colorClass' => 'status-mild'];
            default:
                return $result;
        }
    }

    if ($metric === METRIC_BLOOD_SUGAR) {
        $val = isset($row['value']) ? (float)$row['value'] : null;
        if ($val === null) return $result;
        if ($val < 70) return ['status' => 'low', 'level' => 'severe', 'label' => '低血糖（嚴重）', 'colorClass' => 'status-severe'];
        if ($val <= 99) return $result;
        if ($val <= 125) return ['status' => 'high', 'level' => 'mild', 'label' => '輕度偏高', 'colorClass' => 'status-mild'];
        return ['status' => 'high', 'level' => 'severe', 'label' => '嚴重高血糖', 'colorClass' => 'status-severe'];
    }

    if ($metric === METRIC_HEART_RATE) {
        $val = isset($row['value']) ? (int)$row['value'] : null;
        if ($val === null) return $result;
        if ($val < 50) return ['status' => 'low', 'level' => 'severe', 'label' => '低心率（嚴重）', 'colorClass' => 'status-severe'];
        if ($val <= 59) return ['status' => 'low', 'level' => 'mild', 'label' => '輕度低心率', 'colorClass' => 'status-mild'];
        if ($val <= 100) return $result;
        if ($val <= 110) return ['status' => 'high', 'level' => 'mild', 'label' => '輕度偏高', 'colorClass' => 'status-mild'];
        if ($val <= 130) return ['status' => 'high', 'level' => 'moderate', 'label' => '中度偏高', 'colorClass' => 'status-moderate'];
        return ['status' => 'high', 'level' => 'severe', 'label' => '嚴重偏高', 'colorClass' => 'status-severe'];
    }

    return $result;
}

function get_normal_range(string $metric): array
{
    if ($metric === METRIC_BLOOD_PRESSURE) {
        return ['systolic' => [90, 119], 'diastolic' => [60, 79]];
    }
    if ($metric === METRIC_BLOOD_SUGAR) {
        return ['value' => [70.0, 99.0]];
    }
    if ($metric === METRIC_HEART_RATE) {
        return ['value' => [60, 100]];
    }
    return [];
}

?>

