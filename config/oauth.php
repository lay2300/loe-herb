<?php
$oauth_config = [
    'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'facebook_app_id' => getenv('FACEBOOK_APP_ID') ?: '',
    'facebook_app_secret' => getenv('FACEBOOK_APP_SECRET') ?: '',
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

// Build base URL dynamically so redirect URI matches the current host/port
$oauth_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$oauth_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$oauth_base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($oauth_base_path === '' || $oauth_base_path === '.') {
    $oauth_base_path = '';
}
$oauth_base_url = $oauth_scheme . '://' . $oauth_host . $oauth_base_path;

define('GOOGLE_REDIRECT_URL', $oauth_base_url . '/google_callback.php');

// ตั้งค่า Facebook OAuth (นำข้อมูลมาจาก Facebook Developer)
define('FACEBOOK_APP_ID', $oauth_config['facebook_app_id']);
define('FACEBOOK_APP_SECRET', $oauth_config['facebook_app_secret']);
define('FACEBOOK_REDIRECT_URL', $oauth_base_url . '/facebook_callback.php');

// --- สำหรับ Admin Panel ---
define('ADMIN_GOOGLE_CLIENT_ID', $oauth_config['admin_google_client_id']);
define('ADMIN_GOOGLE_CLIENT_SECRET', $oauth_config['admin_google_client_secret']);
define('ADMIN_GOOGLE_REDIRECT_URL', $oauth_base_url . '/admin/google_callback.php');
?>