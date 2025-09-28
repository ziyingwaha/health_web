-- MySQL schema for 銀髮族健康守護平台 (username-based)

-- Drop legacy tables if exist
DROP TABLE IF EXISTS heart_rate_records;
DROP TABLE IF EXISTS blood_sugar_records;
DROP TABLE IF EXISTS blood_pressure_records;
DROP TABLE IF EXISTS users;

-- 使用者表
CREATE TABLE IF NOT EXISTS users (
    username VARCHAR(50) PRIMARY KEY,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    age INT,
    gender CHAR(1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 健康資料表
CREATE TABLE IF NOT EXISTS health_data (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    record_datetime DATETIME NOT NULL,
    systolic_bp INT NULL,
    diastolic_bp INT NULL,
    blood_sugar DECIMAL(5,2) NULL,
    heart_rate INT NULL,
    INDEX idx_user_time (username, record_datetime),
    CONSTRAINT fk_hd_user FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

