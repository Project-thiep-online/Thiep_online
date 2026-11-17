<?php

/*
|--------------------------------------------------------------------------
| THYU_NHUN WEDDINGS - DATABASE CONFIG
|--------------------------------------------------------------------------
| Local XAMPP vẫn giữ nguyên thông tin cũ.
| Khi lên hosting, ưu tiên đặt biến môi trường:
| WEDDING_APP_ENV, WEDDING_BASE_URL,
| WEDDING_DB_HOST, WEDDING_DB_NAME, WEDDING_DB_USER, WEDDING_DB_PASS
|
| Nếu hosting không hỗ trợ biến môi trường, chỉ cần sửa 5 giá trị
| trong phần PRODUCTION bên dưới.
|--------------------------------------------------------------------------
*/

$is_local_request = in_array(
    strtolower((string)($_SERVER['HTTP_HOST'] ?? '')),
    ['localhost', '127.0.0.1', '[::1]'],
    true
);

$app_env = getenv('WEDDING_APP_ENV');
if ($app_env === false || trim($app_env) === '') {
    $app_env = $is_local_request ? 'local' : 'production';
}
$app_env = strtolower(trim($app_env));

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
| Có thể ghi đè bằng WEDDING_BASE_URL trên hosting.
| Nếu không có, hệ thống tự xác định domain hiện tại.
|--------------------------------------------------------------------------
*/
function wedding_detect_base_url(): string
{
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);

    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));

    if ($host === '') {
        $host = 'localhost';
    }

    /*
     * Tìm thư mục gốc project khi file đang được gọi từ /admin, /customer
     * hoặc /auth. Ví dụ /ThiepOnl/customer/guests.php -> /ThiepOnl.
     */
    if (preg_match('#^(.*/)(?:admin|customer|auth)/[^/]+$#i', $script, $m)) {
        $base_path = rtrim($m[1], '/');
    } else {
        $base_path = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($base_path === '.' || $base_path === '/') {
            $base_path = '';
        }
    }

    return $scheme . '://' . $host . ($base_path !== '' ? $base_path : '');
}

$env_base_url = getenv('WEDDING_BASE_URL');

if ($env_base_url !== false && trim($env_base_url) !== '') {
    $base_url = rtrim(trim($env_base_url), '/');
} elseif ($app_env === 'local') {
    $base_url = 'http://localhost/ThiepOnl';
} else {
    $base_url = wedding_detect_base_url();
}

define('APP_ENV', $app_env);
define('BASE_URL', $base_url);

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
| LOCAL: giữ nguyên DB XAMPP hiện tại.
|
| HOSTING: nếu không dùng biến môi trường, sửa 5 dòng PRODUCTION này.
|--------------------------------------------------------------------------
*/
$production_db_host = 'YOUR_HOSTING_DB_HOST';
$production_db_name = 'YOUR_HOSTING_DB_NAME';
$production_db_user = 'YOUR_HOSTING_DB_USER';
$production_db_pass = 'YOUR_HOSTING_DB_PASSWORD';

if (APP_ENV === 'local') {
    $db_host = 'localhost';
    $db_name = 'wedding_system';
    $db_user = 'root';
    $db_pass = '';
} else {
    $db_host = trim((string)(getenv('WEDDING_DB_HOST') ?: $production_db_host));
    $db_name = trim((string)(getenv('WEDDING_DB_NAME') ?: $production_db_name));
    $db_user = trim((string)(getenv('WEDDING_DB_USER') ?: $production_db_user));
    $db_pass = (string)(getenv('WEDDING_DB_PASS') ?: $production_db_pass);
}

define('DB_HOST', $db_host);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('Wedding system database connection failed: ' . $conn->connect_error);

    if (APP_ENV === 'local') {
        die('Kết nối database thất bại: ' . htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8'));
    }

    http_response_code(500);
    die('Hệ thống đang gặp sự cố. Vui lòng kiểm tra cấu hình database hoặc thử lại sau.');
}

$conn->set_charset('utf8mb4');
