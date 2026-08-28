<?php
session_start();
if (isset($_SESSION['user_login'])) {
    header("Location: index.php");
    exit();
}
require_once 'config/db.php';
require_once 'config/oauth.php';

$google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online';

$facebook_login_url = 'https://www.facebook.com/v19.0/dialog/oauth?client_id=' . FACEBOOK_APP_ID . '&redirect_uri=' . urlencode(FACEBOOK_REDIRECT_URL) . '&response_type=code&scope=public_profile,email&auth_type=rerequest';

// สร้างตาราง users หากยังไม่มี เพื่อให้พร้อมใช้งานทันที
// รวมการสร้างตารางทั้งหมดไว้ที่นี่เพื่อความสมบูรณ์ของ Schema
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `first_name` VARCHAR(100) NULL,
        `last_name` VARCHAR(100) NULL,
        `phone` VARCHAR(20) NULL,
        `avatar` VARCHAR(255) NULL,
        `oauth_provider` VARCHAR(50) NULL,
        `oauth_uid` VARCHAR(255) NULL,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `last_login` TIMESTAMP NULL DEFAULT NULL,
        INDEX `oauth_idx` (`oauth_provider`, `oauth_uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sender ENUM('admin', 'user') DEFAULT 'admin',
        message TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->exec("CREATE TABLE IF NOT EXISTS chat_status (
        user_id INT PRIMARY KEY,
        admin_typing TINYINT(1) DEFAULT 0,
        user_typing TINYINT(1) DEFAULT 0,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- ตรวจสอบและซ่อมแซมคอลัมน์ที่จำเป็นในตาราง users (Self-healing) ---
    try {
        $requiredColumns = [
            'first_name' => 'VARCHAR(100) NULL',
            'last_name' => 'VARCHAR(100) NULL',
            'phone' => 'VARCHAR(20) NULL',
            'avatar' => 'VARCHAR(255) NULL',
            'oauth_provider' => 'VARCHAR(50) NULL',
            'oauth_uid' => 'VARCHAR(255) NULL',
            'last_login' => 'TIMESTAMP NULL DEFAULT NULL'
        ];

        foreach ($requiredColumns as $column => $definition) {
            $row = $conn->query("SHOW COLUMNS FROM users LIKE '$column'")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $conn->exec("ALTER TABLE users ADD COLUMN $column $definition");
            }
        }
    } catch (PDOException $e) {
        // ไม่ต้องทำอะไรถ้าเกิดข้อผิดพลาด (เช่น ตาราง users ยังไม่มี)
        // เพราะโค้ดสร้างตารางหลักด้านบนจะจัดการให้
    }

} catch (PDOException $e) { /* ทำงานต่อไปถ้ามีข้อผิดพลาด */ }

$error = '';
$success = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!isset($_POST['agree_privacy'])) {
        $error = "กรุณายอมรับนโยบายความเป็นส่วนตัวก่อนสมัครสมาชิกค่ะ";
    } else if (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษรค่ะ";
    } else if ($password !== $confirm_password) {
        $error = "รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกันค่ะ";
    } else {
        // ตรวจสอบว่ามีผู้ใช้นี้อยู่แล้วหรือไม่
        $stmt_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user->execute([$username]);

        $stmt_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt_email->execute([$email]);
        
        if ($stmt_user->rowCount() > 0) {
            $error = "ขออภัยค่ะ ชื่อผู้ใช้งาน (Username) นี้มีในระบบแล้ว";
        } else if ($stmt_email->rowCount() > 0) {
            $error = "ขออภัยค่ะ อีเมลนี้ถูกใช้สมัครสมาชิกไปแล้ว";
        } else {
            // เข้ารหัสรหัสผ่านก่อนบันทึก
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            
            if ($insert->execute([$username, $email, $hashed_password])) {
                $success = "สมัครสมาชิกสำเร็จ! คุณสามารถเข้าสู่ระบบได้เลยค่ะ";
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php if($success): ?>
        <meta http-equiv="refresh" content="3;url=login.php">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen relative overflow-hidden">
    
    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')] opacity-10 pointer-events-none"></div>

    <div class="bg-white p-8 md:p-10 rounded-3xl shadow-xl w-full max-w-md relative z-10 border border-green-100">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block text-3xl text-green-800 font-bold tracking-tight mb-2">🌿 LoeiHerb</a>
            <h2 class="text-xl text-gray-600">สมัครสมาชิกสำหรับผู้ชมทั่วไป</h2>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-100"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-50 text-green-700 p-3 rounded-lg text-sm mb-4 border border-green-100 text-center font-medium">
                <?php echo $success; ?><br>
                <p class="mt-2 text-xs text-gray-500">กำลังพาคุณไปยังหน้าเข้าสู่ระบบใน 3 วินาที...</p>
            </div>
        <?php else: ?>
            <form id="register-form" action="" method="POST" class="space-y-5">
                <div>
                    <input type="text" name="username" required placeholder="ชื่อผู้ใช้งาน (Username)" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <input type="email" name="email" required placeholder="อีเมล (Email)" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <input type="password" name="password" required minlength="6" placeholder="รหัสผ่าน (อย่างน้อย 6 ตัวอักษร)" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
                </div>
                <div>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="ยืนยันรหัสผ่านอีกครั้ง" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
                </div>
                <div class="flex items-center">
                    <input id="agree_privacy" name="agree_privacy" type="checkbox" required class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                    <label for="agree_privacy" class="ml-2 block text-sm text-gray-700">ฉันได้อ่านและยอมรับ <a href="privacy_policy.php" target="_blank" class="font-medium text-green-600 hover:text-green-800 underline">นโยบายความเป็นส่วนตัว</a></label>
                </div>
                <button type="submit" name="register" class="w-full bg-green-600 text-white py-3 rounded-xl hover:bg-green-700 font-bold shadow-md hover:shadow-lg transition duration-200">สมัครสมาชิกเลย</button>
            </form>

            <div id="social-login-section" class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">หรือสมัครด้วย</span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <a href="<?php echo $google_login_url; ?>" class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                        <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-5 w-5 mr-2" alt="Google">
                        Google
                    </a>
                    <a href="<?php echo $facebook_login_url; ?>" class="w-full flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#1877F2] hover:bg-[#166FE5] transition">
                        <img src="https://www.svgrepo.com/show/475647/facebook-color.svg" class="h-5 w-5 mr-2" alt="Facebook">
                        Facebook
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-6 text-center text-sm text-gray-500">
            มีบัญชีอยู่แล้วใช่ไหม? <a href="login.php" class="text-green-600 hover:text-green-800 font-bold underline">เข้าสู่ระบบ</a>
        </div>
    </div>
</body>
</html>