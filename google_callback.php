<?php
session_start();
require_once 'config/db.php';
require_once 'config/oauth.php';
require_once 'process_oauth.php';
require_once 'config/curl_helper.php';

if (isset($_GET['code'])) {
    // ขอ Access Token จาก Google
    $url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];
    
    $token_data = call_curl_api($url, $post_data);
    
    if (isset($token_data['access_token'])) {
        // ดึงข้อมูลผู้ใช้งาน
        $info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_data['access_token'];
        
        $user_data = call_curl_api($info_url);
        
        if (isset($user_data['id'])) {
            $oauth_uid = $user_data['id'];
            $email = $user_data['email'] ?? '';
            $first_name = $user_data['given_name'] ?? ''; // ดึงชื่อจริง
            $last_name = $user_data['family_name'] ?? ''; // ดึงนามสกุล
            $avatar = $user_data['picture'] ?? '';
            $username = 'google_' . substr($oauth_uid, 0, 8);
            
            process_oauth_user($conn, 'google', $oauth_uid, $email, $first_name, $last_name, $username, $avatar);
            
            // เด้งไปยังหน้าเว็บหลักของคุณ
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['oauth_error'] = 'ไม่สามารถดึงข้อมูลผู้ใช้จาก Google ได้';
        }
    } else {
        // แสดงข้อผิดพลาดที่ได้จาก Google เพื่อช่วยในการดีบัก
        $error_description = isset($token_data['error_description']) ? $token_data['error_description'] : 'ไม่ทราบสาเหตุ';
        $_SESSION['oauth_error'] = 'การยืนยันตัวตนกับ Google ล้มเหลว: ' . htmlspecialchars($error_description);
    }
}

header("Location: login.php");
exit();
?>