<?php
// ตั้งค่า OAuth ก่อนใช้ล็อกอิน: แนะนำใส่ค่าจริงใน config/oauth.local.php
// (ไฟล์ local นี้ไม่ควรถูก commit) หรือกำหนดเป็น environment variables ตามชื่อด้านล่าง
// ค่าจาก oauth.local.php จะเขียนทับ environment variables หากกำหนดชื่อ key ซ้ำกัน
$oauth_config = [
    // Google สำหรับล็อกอินผู้ใช้: Google Cloud > Credentials > OAuth Client (Web application)
    'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    // Facebook สำหรับล็อกอินผู้ใช้: Meta App Dashboard > App settings / Facebook Login
    'facebook_app_id' => getenv('FACEBOOK_APP_ID') ?: '',
    'facebook_app_secret' => getenv('FACEBOOK_APP_SECRET') ?: '',
    // Google สำหรับล็อกอินผู้ดูแลระบบเท่านั้น; ไม่จำเป็นถ้าไม่ได้ใช้ปุ่ม Google ในหน้า admin/login.php
    'admin_google_client_id' => getenv('ADMIN_GOOGLE_CLIENT_ID') ?: '',
    'admin_google_client_secret' => getenv('ADMIN_GOOGLE_CLIENT_SECRET') ?: '',
];

$local_oauth_file = __DIR__ . '/oauth.local.php';
if (is_file($local_oauth_file)) {
    $local_oauth_config = require $local_oauth_file;
    if (is_array($local_oauth_config)) {
        $oauth_config = array_merge($oauth_config, $local_oauth_config);
    }
}

define('GOOGLE_CLIENT_ID', $oauth_config['google_client_id']);
define('GOOGLE_CLIENT_SECRET', $oauth_config['google_client_secret']);

// Redirect URI สร้างจาก host/path ที่กำลังเปิด ต้องนำ URL ที่ได้ไปเพิ่มใน OAuth app ให้ตรงทุกตัวอักษร
$oauth_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$oauth_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$oauth_base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($oauth_base_path === '' || $oauth_base_path === '.') {
    $oauth_base_path = '';
}
$oauth_base_url = $oauth_scheme . '://' . $oauth_host . $oauth_base_path;

define('GOOGLE_REDIRECT_URL', $oauth_base_url . '/google_callback.php');

// Facebook OAuth configuration
define('FACEBOOK_APP_ID', $oauth_config['facebook_app_id']);
define('FACEBOOK_APP_SECRET', $oauth_config['facebook_app_secret']);
define('FACEBOOK_REDIRECT_URL', $oauth_base_url . '/facebook_callback.php');

// Admin Panel
define('ADMIN_GOOGLE_CLIENT_ID', $oauth_config['admin_google_client_id']);
define('ADMIN_GOOGLE_CLIENT_SECRET', $oauth_config['admin_google_client_secret']);
define('ADMIN_GOOGLE_REDIRECT_URL', $oauth_base_url . '/admin/google_callback.php');
?>
