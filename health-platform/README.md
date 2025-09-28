## 銀髮族健康守護平台 (PHP + MySQL + Chart.js)

### 功能
- 會員註冊 / 登入 / 登出
- 三大健康指標紀錄：血壓、血糖、心率
- 儀表板顯示最新紀錄與狀態燈（高/低/正常）
- 詳情頁趨勢圖（Chart.js）與 CRUD（新增/編輯/刪除）
- 依狀態提供生活建議

### 環境需求
- PHP 8.1+（啟用 PDO MySQL）
- MySQL 8.0+
- 網頁伺服器（可用 PHP 內建伺服器或 Apache/Nginx）

### 安裝與執行
1. 設定環境變數（可選）：
   - `MYSQL_HOST`（預設 127.0.0.1）
   - `MYSQL_PORT`（預設 3306）
   - `MYSQL_DB`（預設 health_guardian）
   - `MYSQL_USER`（預設 root）
   - `MYSQL_PASS`（預設 空白）

2. 建立資料庫並匯入 schema：
   - 先在 MySQL 建立資料庫：`CREATE DATABASE health_guardian CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
   - 執行腳本：
     ```bash
     php /workspace/health-platform/scripts/setup_db.php
     ```

3. 啟動伺服器（範例：PHP 內建伺服器）：
   ```bash
   php -S 0.0.0.0:8080 -t /workspace/health-platform/public
   ```

4. 造訪網站：`http://localhost:8080`，註冊並登入開始使用。

### 目錄結構
```
config/       設定與資料庫連線
includes/     共用頁首、頁尾、輔助函式
public/       對外路徑（首頁、登入註冊、詳情）
assets/       前端樣式
sql/          資料表 schema
scripts/      安裝腳本
```

### 安全性與注意事項
- 已加上 CSRF Token、密碼雜湊（password_hash）、準確的 PDO 預處理
- 門檻值僅供參考，實際應諮詢專業醫師
- 若部署於公網，請設定 HTTPS 與調整 session/cookie 安全屬性

