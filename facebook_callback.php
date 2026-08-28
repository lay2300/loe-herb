<?php
require_once 'bootstrap.php';

if (isset($_GET['code'])) {
    // ขอ Access Token จาก Facebook
    $token_url = "https://graph.facebook.com/v19.0/oauth/access_token?client_id=" . FACEBOOK_APP_ID . "&redirect_uri=" . urlencode(FACEBOOK_REDIRECT_URL) . "&client_secret=" . FACEBOOK_APP_SECRET . "&code=" . $_GET['code'];
    
    $token_data = call_curl_api($token_url);
    
    if (isset($token_data['access_token'])) {
        // ดึงข้อมูลผู้ใช้งาน
        $info_url = "https://graph.facebook.com/me?fields=id,first_name,last_name,email,picture.type(large)&access_token=" . $token_data['access_token'];
        
        $user_data = call_curl_api($info_url);
        
        if (isset($user_data['id'])) {
            $oauth_uid = $user_data['id'];
            $email = $user_data['email'] ?? '';
            $first_name = $user_data['first_name'] ?? '';
            $last_name = $user_data['last_name'] ?? '';
            $avatar = $user_data['picture']['data']['url'] ?? '';
            $username = 'fb_' . substr($oauth_uid, 0, 8);
            
            process_oauth_user($conn, 'facebook', $oauth_uid, $email, $first_name, $last_name, $username, $avatar);
            
            // เด้งไปยังหน้าเว็บหลักของคุณ
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['oauth_error'] = 'ไม่สามารถดึงข้อมูลผู้ใช้จาก Facebook ได้';
        }
    } else {
        $error_description = isset($token_data['error']['message']) ? $token_data['error']['message'] : 'ไม่ทราบสาเหตุ';
        $_SESSION['oauth_error'] = 'การยืนยันตัวตนกับ Facebook ล้มเหลว: ' . htmlspecialchars($error_description);
    }
}

header("Location: login.php");
exit();
?>