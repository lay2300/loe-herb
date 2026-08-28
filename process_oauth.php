<?php
require_once __DIR__ . '/config/curl_helper.php';
require_once __DIR__ . '/config/schema_helpers.php';
require_once __DIR__ . '/config/app_log.php';

function process_oauth_user($conn, $provider, $oauth_uid, $email, $first_name, $last_name, $username, $avatar_url) {
    // Ensure users table has required columns for social login
    try {
        ensure_user_columns($conn);
    } catch (Exception $e) {
        app_log('process_oauth_user: ensure_user_columns failed - ' . $e->getMessage());
        // allow caller to see a clear message rather than a raw SQL exception
        echo '<h2>เกิดข้อผิดพลาดในระบบ social login</h2>';
        echo '<p>ไม่สามารถเตรียมตาราง users ให้รองรับ OAuth ได้ กรุณาติดต่อผู้ดูแลระบบ</p>';
        exit();
    }

    // --- ดาวน์โหลดและบันทึกรูปโปรไฟล์ ---
    $avatar_filename = '';
    if (!empty($avatar_url)) {
        $upload_dir = 'uploads/';
        // ตรวจสอบและสร้างโฟลเดอร์ uploads หากยังไม่มี
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // เรียกใช้ฟังก์ชันกลางสำหรับดาวน์โหลดไฟล์
        $download_result = download_file_curl($avatar_url);
        
        if ($download_result['http_code'] === 200 && $download_result['data']) {
            $avatar_filename = 'oauth_' . $provider . '_' . $oauth_uid . '_' . time() . '.jpg';
            file_put_contents($upload_dir . $avatar_filename, $download_result['data']);
        }
    }

    // --- ตรวจสอบและจัดการข้อมูลผู้ใช้ ---
    $stmt = $conn->prepare("SELECT * FROM users WHERE oauth_uid = ? AND oauth_provider = ?");
    $stmt->execute([$oauth_uid, $provider]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // ผู้ใช้ใหม่: ตรวจสอบก่อนว่าอีเมลนี้ถูกใช้สมัครแบบปกติไปแล้วหรือยัง
        if (!empty($email)) {
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->rowCount() > 0) {
                // ถ้าอีเมลซ้ำ ให้หยุดและแจ้งผู้ใช้
                $_SESSION['oauth_error'] = 'อีเมลนี้ถูกใช้สมัครสมาชิกแบบปกติไปแล้ว กรุณาล็อกอินด้วยรหัสผ่านเดิมของท่าน';
                // ใช้ JavaScript เพื่อหน่วงเวลาและเปลี่ยนหน้า
                echo "<script>alert('อีเมลนี้ถูกใช้สมัครสมาชิกแบบปกติไปแล้ว กรุณาล็อกอินด้วยรหัสผ่านเดิมของท่าน'); window.location.href='login.php';</script>";
                exit();
            }
        }

        // --- ตรวจสอบและสร้าง Username ที่ไม่ซ้ำกัน ---
        $original_username = $username;
        $counter = 1;
        while (true) {
            $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt_check_username->execute([$username]);
            if ($stmt_check_username->rowCount() == 0) {
                break; // ถ้าไม่ซ้ำ ให้ออกจาก loop
            }
            $username = $original_username . '_' . $counter++; // ถ้าซ้ำ ให้ลองเติมตัวเลขท้าย
        }
        
        // สร้างผู้ใช้ใหม่ในระบบ
        $sql = "INSERT INTO users (username, email, first_name, last_name, password, avatar, oauth_provider, oauth_uid) 
                VALUES (?, ?, ?, ?, '', ?, ?, ?)";
        try {
            $conn->prepare($sql)->execute([$username, $email, $first_name, $last_name, $avatar_filename, $provider, $oauth_uid]);
            $user_id = $conn->lastInsertId();
        } catch (Exception $e) {
            app_log('process_oauth_user INSERT users failed: ' . $e->getMessage());
            // show friendly message
            echo '<h2>ไม่สามารถสร้างบัญชีของคุณได้ กรุณาลองใหม่อีกครั้งหรือติดต่อผู้ดูแลระบบ</h2>';
            exit();
        }
    } else {
        // ผู้ใช้เก่า: อัปเดตข้อมูล
        $user_id = $user['id'];
        $username = $user['username'];
        // อัปเดตรูปโปรไฟล์ใหม่ถ้ามีการเปลี่ยนแปลง
        if (!empty($avatar_filename)) {
            try {
                $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$avatar_filename, $user_id]);
            } catch (Exception $e) {
                app_log('process_oauth_user UPDATE avatar failed: ' . $e->getMessage());
            }
        }
        // อัปเดตชื่อ-นามสกุล หากมีการเปลี่ยนแปลงจาก Social Media
        if (($user['first_name'] != $first_name && !empty($first_name)) || ($user['last_name'] != $last_name && !empty($last_name))) {
            try {
                $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?")
                     ->execute([$first_name, $last_name, $user_id]);
            } catch (Exception $e) {
                app_log('process_oauth_user UPDATE name failed: ' . $e->getMessage());
            }
        }
    }
    
    // --- สร้าง Session และล็อกอิน ---
    try {
        $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user_id]);
    } catch (Exception $e) {
        app_log('process_oauth_user UPDATE last_login failed: ' . $e->getMessage());
    }
    
    $_SESSION['user_login'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
}
?>