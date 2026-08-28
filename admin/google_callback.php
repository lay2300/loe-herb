<?php
session_start();
require_once '../config/db.php';
require_once '../config/oauth.php';
require_once '../config/curl_helper.php';

// --- Whitelist of authorized admin emails ---
$authorized_admin_emails = [
    'looy2300@gmail.com',
    // 'another.admin@example.com' // สามารถเพิ่มอีเมลแอดมินคนอื่นได้ที่นี่
];

if (isset($_GET['code'])) {
    // 1. Exchange code for access token
    $url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'client_id' => ADMIN_GOOGLE_CLIENT_ID,
        'client_secret' => ADMIN_GOOGLE_CLIENT_SECRET,
        'redirect_uri' => ADMIN_GOOGLE_REDIRECT_URL,
        'grant_type' => 'authorization_code',
        'code' => $_GET['code']
    ];
    
    $token_data = call_curl_api($url, $post_data);

    if (isset($token_data['access_token'])) {
        // 2. Fetch user info
        $info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_data['access_token'];
        $user_data = call_curl_api($info_url);

        if (isset($user_data['email'])) {
            $email = $user_data['email'];

            // 3. Security Check: Is this email authorized to be an admin?
            if (!in_array($email, $authorized_admin_emails)) {
                $_SESSION['admin_login_error'] = 'บัญชี Google ของคุณไม่ได้รับอนุญาตให้เข้าสู่ระบบผู้ดูแล';
                header("Location: login.php");
                exit();
            }

            // 4. Process Admin User (Find or Create)
            $oauth_uid = $user_data['id'];
            $avatar = $user_data['picture'] ?? '';

            $stmt = $conn->prepare("SELECT * FROM admins WHERE oauth_uid = ? AND oauth_provider = 'google'");
            $stmt->execute([$oauth_uid]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                // Create a new admin user if not exists
                
                // --- ตรวจสอบและสร้าง Username ที่ไม่ซ้ำกัน ---
                $original_username = 'admin_' . substr($oauth_uid, 0, 8);
                $username = $original_username;
                $counter = 1;
                while (true) {
                    $stmt_check_username = $conn->prepare("SELECT id FROM admins WHERE username = ?");
                    $stmt_check_username->execute([$username]);
                    if ($stmt_check_username->rowCount() == 0) break;
                    $username = $original_username . '_' . $counter++;
                }

                $sql = "INSERT INTO admins (username, email, password, avatar, oauth_provider, oauth_uid) VALUES (?, ?, '', ?, 'google', ?)";
                $conn->prepare($sql)->execute([$username, $email, $avatar, $oauth_uid]);
                $admin_id = $conn->lastInsertId();
            } else {
                $admin_id = $admin['id'];
                // Optionally update avatar
                if ($avatar) {
                    $conn->prepare("UPDATE admins SET avatar = ? WHERE id = ?")->execute([$avatar, $admin_id]);
                }
            }

            // 5. Create admin session
            $_SESSION['admin_login'] = true;
            $_SESSION['admin_id'] = $admin_id;
            header("Location: index.php");
            exit();
        }
    }
}

$_SESSION['admin_login_error'] = 'การยืนยันตัวตนกับ Google ล้มเหลว';
header("Location: login.php");
exit();
?>