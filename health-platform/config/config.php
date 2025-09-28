<?php
// Start session and set base configuration

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application constants
define('APP_NAME', '銀髮族健康守護平台');

// Base path resolution
define('BASE_PATH', dirname(__DIR__));

// Database configuration via environment variables with sane defaults
$dbHost = getenv('MYSQL_HOST') ?: '127.0.0.1';
$dbPort = getenv('MYSQL_PORT') ?: '3306';
$dbName = getenv('MYSQL_DB') ?: 'health_guardian';
$dbUser = getenv('MYSQL_USER') ?: 'root';
$dbPass = getenv('MYSQL_PASS') ?: '';

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

// CSRF token helper bootstrap
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Metric constants
const METRIC_BLOOD_PRESSURE = 'blood_pressure';
const METRIC_BLOOD_SUGAR = 'blood_sugar';
const METRIC_HEART_RATE = 'heart_rate';

const METRICS = [
    METRIC_BLOOD_PRESSURE,
    METRIC_BLOOD_SUGAR,
    METRIC_HEART_RATE,
];

// Thresholds for visual alerts
// Note: Values are general references; users should consult healthcare professionals for personalized ranges.
const THRESHOLDS = [
    METRIC_BLOOD_PRESSURE => [
        'high' => ['systolic' => 140, 'diastolic' => 90],
        'low' => ['systolic' => 90, 'diastolic' => 60],
    ],
    METRIC_BLOOD_SUGAR => [
        // Fasting guideline
        'high' => 126, // mg/dL
        'low' => 70,
    ],
    METRIC_HEART_RATE => [
        'high' => 100, // bpm
        'low' => 60,
    ],
];

?>

