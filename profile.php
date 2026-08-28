<?php
session_start();
if (!isset($_SESSION['user_login'])) {
    header("Location: login.php");
    exit();
}
require_once 'config/db.php';
require_once 'config/schema_helpers.php';
require_once 'config/app_log.php';

// Ensure users table has required columns (safe to call on every request)
try {
    ensure_user_columns($conn);
} catch (Exception $e) {
    app_log('profile.php: ensure_user_columns failed - ' . $e->getMessage());
    // continue gracefully; updates may still fail but we logged the problem
}
// Ensure notifications schema also has fields we expect
try {
    ensure_notifications_columns($conn);
} catch (Exception $e) {
    app_log('profile.php: ensure_notifications_columns failed - ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// อัปเดตสถานะกำลังพิมพ์
if (isset($_POST['update_typing_profile'])) {
    $is_typing = (int)$_POST['is_typing'];
    $conn->prepare("INSERT INTO chat_status (user_id, user_typing) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_typing = ?, last_active = NOW()")->execute([$user_id, $is_typing, $is_typing]);
    exit();
}

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ดึงข้อความแจ้งเตือนทั้งหมดของผู้ใช้ (เรียงลำดับเวลาให้เหมือนแชท)
$stmt_notif = $conn->prepare("SELECT * FROM (SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50) sub ORDER BY created_at ASC");
$stmt_notif->execute([$user_id]);
$notifications = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);

// เมื่อผู้ใช้เข้ามาหน้านี้ ให้อัปเดตข้อความที่ยังไม่อ่านให้เป็น "อ่านแล้ว" ทันที
if (count($notifications) > 0) {
    $update_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
    $update_read->execute([$user_id]);
}

// จัดการส่งข้อความตอบกลับ
if (isset($_POST['send_reply'])) {
    try {
    $reply_msg = trim($_POST['reply_message']);
    $image_path = null;
    if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] == 0) {
        $ext = pathinfo($_FILES['chat_image']['name'], PATHINFO_EXTENSION);
        $image_path = "chat_" . $user_id . "_" . time() . "_" . uniqid() . "." . $ext;
        if (!file_exists("uploads/")) mkdir("uploads/", 0777, true);
        move_uploaded_file($_FILES['chat_image']['tmp_name'], "uploads/" . $image_path);
    }

    if (!empty($reply_msg) || $image_path) {
        $insert_reply = $conn->prepare("INSERT INTO notifications (user_id, sender, message, image_path) VALUES (?, 'user', ?, ?)");
        if ($insert_reply->execute([$user_id, $reply_msg, $image_path])) {
            $new_id = $conn->lastInsertId();
            $conn->prepare("UPDATE chat_status SET user_typing = 0 WHERE user_id = ?")->execute([$user_id]);
            if (isset($_POST['ajax'])) {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'id' => $new_id]);
                exit();
            }
        }
    }
    } catch (Exception $e) {
        app_log('profile.php send_reply error: ' . $e->getMessage());
        if (isset($_POST['ajax'])) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error']);
            exit();
        }
    }
    if (isset($_POST['ajax'])) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error']);
        exit();
    }
}

// สำหรับการดึงข้อความใหม่ (AJAX Polling) ในหน้า Profile
if (isset($_POST['fetch_profile_chat'])) {
    $last_id = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;
    
    $stmt_new = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC");
    $stmt_new->execute([$user_id, $last_id]);
    $new_msgs = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
    
    $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND sender = 'admin' AND is_read = 0")->execute([$user_id]);
    
    $stmt_typing = $conn->prepare("SELECT admin_typing FROM chat_status WHERE user_id = ? AND last_active > NOW() - INTERVAL 10 SECOND");
    $stmt_typing->execute([$user_id]);
    $admin_typing = $stmt_typing->fetchColumn() ?: 0;

    header('Content-Type: application/json');
    echo json_encode(['messages' => $new_msgs, 'admin_typing' => $admin_typing]);
    exit();
}

// อัปเดตข้อมูล (อีเมล)
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $avatar_path = $user['avatar'];
    
    // --- ตรวจสอบข้อมูลซ้ำซ้อน ---
    $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_email->execute([$email, $user_id]);
    
    $check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_username->execute([$new_username, $user_id]);

    if (empty($new_username)) {
        $error = "ชื่อผู้ใช้งานห้ามเป็นค่าว่างค่ะ";
    } else if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
        $error = "ชื่อผู้ใช้งานสามารถใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ (a-z, A-Z), ตัวเลข (0-9) และเครื่องหมาย _ เท่านั้นค่ะ";
    } else if ($check_username->rowCount() > 0) {
        $error = "ขออภัยค่ะ ชื่อผู้ใช้งานนี้มีในระบบแล้ว";
    } else if ($check_email->rowCount() > 0) {
        $error = "อีเมลนี้มีผู้ใช้งานอื่นในระบบแล้วค่ะ";
    } else {
        // จัดการอัปโหลดรูปโปรไฟล์
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $upload_dir = "uploads/";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_name = "avatar_" . $user_id . "_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_name)) {
                // ลบรูปเก่าถ้ามี
                if ($avatar_path && file_exists($upload_dir . $avatar_path)) {
                    unlink($upload_dir . $avatar_path);
                }
                $avatar_path = $new_name;
            }

        }

        // ตรวจสอบและสร้างคอลัมน์ `phone` หากยังไม่มี เพื่อไม่ให้เกิด SQL error บน schema เก่า
        try {
            $row = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $conn->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL");
            }
        } catch (PDOException $e) {
            // ถ้าเกิดข้อผิดพลาด ไม่ต้องหยุดการทำงาน แต่การอัปเดตอาจล้มเหลวซ้ำ
        }

        $update = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ?, avatar = ? WHERE id = ?");
        try {
            if ($update->execute([$new_username, $email, $first_name, $last_name, $phone, $avatar_path, $user_id])) {
                $success = "อัปเดตข้อมูลส่วนตัวเรียบร้อยแล้วค่ะ";

                // อัปเดต Session ของ username ด้วย
                $_SESSION['username'] = $new_username;

                // ดึงข้อมูลใหม่มาแสดงผลทันที
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
            }
        } catch (Exception $e) {
            app_log('profile.php UPDATE users failed: ' . $e->getMessage());
            $error = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
        }
    }
}

// เปลี่ยนรหัสผ่าน
if (isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($old_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update_pass->execute([$hashed_password, $user_id])) {
                $success = "เปลี่ยนรหัสผ่านเรียบร้อยแล้วค่ะ คุณสามารถใช้รหัสใหม่ในครั้งถัดไปได้เลย";
                $user['password'] = $hashed_password;
            } else {
                $error = "เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน";
            }
        } else {
            $error = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกันค่ะ";
        }
    } else {
        $error = "รหัสผ่านเดิมไม่ถูกต้องค่ะ";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 text-gray-700 min-h-screen flex flex-col">

    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center relative">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="text-2xl text-green-800 font-bold tracking-tight">🌿 LoeiHerb</span>
            </a>

            <input id="nav-toggle" type="checkbox" />
            <label for="nav-toggle" class="nav-toggle-label md:hidden">
                <svg viewBox="0 0 100 80" fill="currentColor">
                    <rect width="100" height="10"></rect>
                    <rect y="30" width="100" height="10"></rect>
                    <rect y="60" width="100" height="10"></rect>
                </svg>
            </label>

            <div class="nav-links md:flex space-x-6 items-center">
                <a href="index.php" class="text-gray-600 hover:text-green-700 font-medium transition">หน้าแรก</a>
                <a href="categories.php" class="text-gray-600 hover:text-green-700 font-medium transition">หมวดหมู่สรรพคุณ</a>
                <a href="map.php" class="text-gray-600 hover:text-green-700 font-medium transition">แผนที่สมุนไพร</a>
                <a href="wisdom.php" class="text-gray-600 hover:text-green-700 font-medium transition">ภูมิปัญญาท้องถิ่น</a>
                <a href="contact.php" class="text-gray-600 hover:text-green-700 font-medium transition">ติดต่อเรา</a>

                <span class="text-green-800 font-bold bg-green-100 px-3 py-1 rounded-full text-sm flex items-center shadow-sm transition">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="text-sm text-red-600 hover:bg-red-50 px-4 py-2 rounded-full font-medium transition">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-6xl mx-auto w-full px-4 py-12">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800">การตั้งค่าบัญชี</h1>
            <p class="text-gray-500">จัดการข้อมูลส่วนตัว ความปลอดภัย และรูปโปรไฟล์</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-100 shadow-sm flex items-center">
                <span class="mr-2">⚠️</span> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-6 border border-green-100 shadow-sm flex items-center">
                <span class="mr-2">✅</span> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Summary -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 text-center sticky top-24">
                    <div class="relative w-32 h-32 mx-auto mb-4">
                        <?php if($user['avatar']): ?>
                            <img src="uploads/<?php echo $user['avatar']; ?>" class="w-full h-full object-cover rounded-full shadow-md border-4 border-white">
                        <?php else: ?>
                            <div class="w-full h-full bg-green-100 text-green-700 text-5xl font-bold rounded-full flex items-center justify-center shadow-inner border-4 border-white">
                                <?php echo mb_substr($user['username'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">@<?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="text-gray-500 text-sm mb-6"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <div class="border-t border-gray-100 pt-6 text-sm text-gray-500 space-y-3 text-left">
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-xl">
                            <span class="font-medium">📅 วันที่สมัคร</span>
                            <span class="text-gray-700 font-bold"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-xl">
                            <span class="font-medium">🕒 ใช้งานล่าสุด</span>
                            <span class="text-gray-700 font-bold"><?php echo $user['last_login'] ? date('d/m/Y', strtotime($user['last_login'])) : '-'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Forms -->
            <div class="w-full lg:w-2/3 space-y-8">
                <!-- ข้อมูลส่วนตัว -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center"><span class="bg-green-100 text-green-700 w-8 h-8 rounded-full flex justify-center items-center mr-3 text-sm">📝</span> ข้อมูลทั่วไป</h2>
                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition bg-yellow-50">
                            <p class="text-xs text-gray-400 mt-1">สามารถใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ, ตัวเลข และ _ เท่านั้น</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">อัปโหลดรูปโปรไฟล์</label>
                            <input type="file" name="avatar" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition border border-gray-200 rounded-xl bg-gray-50">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" placeholder="ระบุชื่อจริง" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">นามสกุล</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" placeholder="ระบุนามสกุล" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition bg-gray-50">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="08X-XXX-XXXX" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                            </div>
                        </div>
                        
                        <button type="submit" name="update_profile" class="mt-2 w-full md:w-auto px-8 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-md">บันทึกข้อมูลส่วนตัว</button>
                    </form>
                </div>

                <!-- กล่องข้อความสนทนา -->
                <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 flex flex-col h-[550px]">
                    <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center shrink-0"><span class="bg-yellow-100 text-yellow-700 w-8 h-8 rounded-full flex justify-center items-center mr-3 text-sm">💬</span> กล่องข้อความสนทนา (Inbox)</h2>
                    
                    <div class="space-y-4 overflow-y-auto pr-2 flex-1 mb-4 flex flex-col" id="chat-container">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <?php $img_html = $notif['image_path'] ? '<img src="uploads/'.htmlspecialchars($notif['image_path']).'" class="w-full rounded-lg mb-2 max-h-48 object-cover cursor-pointer" onclick="window.open(this.src, \'_blank\')">' : ''; ?>
                                <?php if($notif['sender'] == 'admin'): ?>
                                    <!-- ข้อความจากแอดมิน (ซ้าย) -->
                                    <div class="self-start max-w-[85%]" data-msg-id="<?php echo $notif['id']; ?>">
                                        <div class="flex items-end mb-1">
                                            <span class="bg-yellow-100 text-yellow-700 text-[11px] font-bold px-2 py-0.5 rounded-full mr-2">Admin</span>
                                            <span class="text-[10px] text-gray-400"><?php echo date('d/m H:i', strtotime($notif['created_at'])); ?></span>
                                        </div>
                                        <div class="p-3.5 rounded-2xl rounded-tl-sm bg-gray-100 text-gray-700 text-sm shadow-sm leading-relaxed">
                                            <?php echo $img_html . nl2br(htmlspecialchars($notif['message'])); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- ข้อความของผู้ใช้ (ขวา) -->
                                    <div class="self-end max-w-[85%]" data-msg-id="<?php echo $notif['id']; ?>">
                                        <div class="flex items-end justify-end mb-1">
                                            <span class="text-[10px] text-gray-400 mr-2"><?php echo date('d/m H:i', strtotime($notif['created_at'])); ?></span>
                                            <span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full">คุณ</span>
                                        </div>
                                        <div class="p-3.5 rounded-2xl rounded-tr-sm bg-green-600 text-white text-sm shadow-sm leading-relaxed">
                                            <?php echo $img_html . nl2br(htmlspecialchars($notif['message'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="m-auto text-center py-8 text-gray-400 text-sm">ยังไม่มีข้อความสนทนา เริ่มทักทายแอดมินได้เลย!</div>
                        <?php endif; ?>
                        <div id="profile-typing-indicator" class="hidden self-start max-w-[85%] mt-2 mb-2">
                            <div class="p-2.5 rounded-2xl rounded-tl-sm bg-gray-100 text-gray-500 text-xs italic flex items-center space-x-1 w-fit shadow-sm">
                                <span>แอดมินกำลังพิมพ์</span>
                                <span class="animate-bounce">.</span><span class="animate-bounce" style="animation-delay: 0.1s">.</span><span class="animate-bounce" style="animation-delay: 0.2s">.</span>
                            </div>
                        </div>
                    </div>
                    
                    <form id="profile-chat-form" action="" method="POST" enctype="multipart/form-data" class="mt-auto pt-4 border-t border-gray-100 shrink-0 flex gap-2 items-center">
                        <label class="cursor-pointer text-gray-400 hover:text-green-600 transition p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            <input type="file" id="profile_chat_image" name="chat_image" accept="image/*" class="hidden">
                        </label>
                        <div class="flex-1 relative">
                            <div id="profile_img_preview_wrap" class="hidden absolute bottom-full left-0 mb-2 p-1 bg-white border border-gray-200 rounded-lg shadow-lg">
                                <img id="profile_img_preview" src="" class="h-16 w-16 object-cover rounded">
                                <button type="button" id="profile_clear_img" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">x</button>
                            </div>
                            <input type="text" id="profile_chat_input" name="reply_message" autocomplete="off" placeholder="พิมพ์ข้อความตอบกลับ..." class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition bg-gray-50 focus:bg-white text-sm">
                        </div>
                        <input type="hidden" name="send_reply" value="1">
                        <button type="submit" name="send_reply" class="bg-green-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-md flex items-center justify-center text-sm">ส่ง 🚀</button>
                    </form>
                    <script>
                        const chatContainer = document.getElementById('chat-container');
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                        
                        let lastProfileMessageId = <?php echo count($notifications) > 0 ? end($notifications)['id'] : 0; ?>;
                        let pTypingTimeout;
                        let pIsTyping = false;

                        const pInputImg = document.getElementById('profile_chat_image');
                        const pImgWrap = document.getElementById('profile_img_preview_wrap');
                        const pImgPreview = document.getElementById('profile_img_preview');
                        const profileChatForm = document.getElementById('profile-chat-form');
                        const pInputText = document.getElementById('profile_chat_input');
                        const typingIndicator = document.getElementById('profile-typing-indicator');

                        // --- Helper Functions ---
                        function playSound() {
                            try { const ctx = new (window.AudioContext || window.webkitAudioContext)(); const o = ctx.createOscillator(); const g = ctx.createGain(); o.connect(g); g.connect(ctx.destination); o.type = 'sine'; o.frequency.setValueAtTime(880, ctx.currentTime); g.gain.setValueAtTime(0.1, ctx.currentTime); o.start(); g.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.3); o.stop(ctx.currentTime + 0.3); } catch(e) {}
                        }

                        function renderMessage(msg, isTemp = false) {
                            const placeholder = chatContainer.querySelector('.m-auto.text-center');
                            if (placeholder) placeholder.remove();

                            const timeStr = isTemp ? new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }) : msg.created_at.substring(11, 16);
                            const safeMsg = msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
                            const msgImg = msg.image_path ? `<img src="uploads/${msg.image_path}" class="w-full rounded-lg mb-2 object-cover max-h-48 cursor-pointer" onclick="window.open(this.src, '_blank')">` : '';
                            
                            let msgHTML = '';
                            if (msg.sender === 'admin') {
                                msgHTML = `<div class="self-start max-w-[85%]" data-msg-id="${msg.id}"><div class="flex items-end mb-1"><span class="bg-yellow-100 text-yellow-700 text-[11px] font-bold px-2 py-0.5 rounded-full mr-2">Admin</span><span class="text-[10px] text-gray-400">${timeStr}</span></div><div class="p-3.5 rounded-2xl rounded-tl-sm bg-gray-100 text-gray-700 text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div></div>`;
                            } else {
                                const tempClass = isTemp ? `profile-msg-temp opacity-60` : '';
                                const tempId = isTemp ? `id="${msg.id}"` : `data-msg-id="${msg.id}"`;
                                const sendingText = isTemp ? `<span class="text-green-500 font-bold ml-1">ส่ง...</span>` : '';
                                msgHTML = `<div class="self-end max-w-[85%] ${tempClass}" ${tempId}><div class="flex items-end justify-end mb-1"><span class="text-[10px] text-gray-400 mr-2 time-str">${timeStr} ${sendingText}</span><span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full">คุณ</span></div><div class="p-3.5 rounded-2xl rounded-tr-sm bg-green-600 text-white text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div></div>`;
                            }

                            if(typingIndicator) chatContainer.insertBefore(document.createRange().createContextualFragment(msgHTML), typingIndicator);
                            else chatContainer.insertAdjacentHTML('beforeend', msgHTML);
                            chatContainer.scrollTop = chatContainer.scrollHeight;
                        }

                        // --- Event Handlers ---
                        function handleTyping() {
                            if (!pIsTyping) {
                                pIsTyping = true; let fd = new FormData(); fd.append('update_typing_profile', '1'); fd.append('is_typing', '1'); fetch('profile.php', {method:'POST', body:fd});
                            }
                            clearTimeout(pTypingTimeout); pTypingTimeout = setTimeout(() => { pIsTyping = false; let fd2 = new FormData(); fd2.append('update_typing_profile', '1'); fd2.append('is_typing', '0'); fetch('profile.php', {method:'POST', body:fd2}); }, 2000);
                        }

                        function handleFormSubmit(e) {
                            e.preventDefault();
                            const msg = pInputText.value.trim();
                            const imgFile = pInputImg.files[0];
                            if (!msg && !imgFile) return;

                            const submitBtn = this.querySelector('button[type="submit"]');
                            submitBtn.disabled = true; submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            
                            const tempId = 'ptemp-' + Date.now();
                            const tempMessage = {
                                id: tempId,
                                sender: 'user',
                                message: msg,
                                image_path: imgFile ? pImgPreview.src : '',
                                created_at: ''
                            };
                            renderMessage(tempMessage, true);
                            
                            const formData = new FormData(profileChatForm);
                            formData.append('ajax', '1');
                            
                            pInputText.value = ''; pInputImg.value = ''; pImgWrap.classList.add('hidden');
                            
                            fetch('profile.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                const tempEl = document.getElementById(tempId);
                                if (data.status === 'success' && data.id && tempEl) {
                                    lastProfileMessageId = Math.max(lastProfileMessageId, data.id);
                                    tempEl.classList.remove('profile-msg-temp', 'opacity-60');
                                    tempEl.removeAttribute('id');
                                    tempEl.setAttribute('data-msg-id', data.id);
                                    const timeEl = tempEl.querySelector('.time-str');
                                    if(timeEl) timeEl.innerHTML = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                                } else {
                                    if(tempEl) tempEl.remove();
                                }
                            })
                            .catch(err => { const tempEl = document.getElementById(tempId); if(tempEl) tempEl.remove(); })
                            .finally(() => { submitBtn.disabled = false; submitBtn.classList.remove('opacity-50', 'cursor-not-allowed'); });
                        }

                        function fetchNewMessages() {
                            const formData = new FormData();
                            formData.append('fetch_profile_chat', '1');
                            formData.append('last_id', lastProfileMessageId);
                            
                            fetch('profile.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                if (data.messages && data.messages.length > 0) {
                                    let hasNewMsg = false;
                                    data.messages.forEach(msg => {
                                        if (msg.id > lastProfileMessageId) lastProfileMessageId = msg.id;
                                        if (document.querySelector(`[data-msg-id="${msg.id}"]`)) return;
                                        if(msg.sender === 'admin') hasNewMsg = true;
                                        renderMessage(msg);
                                    });
                                    if(hasNewMsg && document.hidden) playSound();
                                }
                                if (data.admin_typing == 1) {
                                    if(typingIndicator) { typingIndicator.classList.remove('hidden'); chatContainer.appendChild(typingIndicator); chatContainer.scrollTop = chatContainer.scrollHeight; }
                                } else { if(typingIndicator) typingIndicator.classList.add('hidden'); }
                            }).catch(err => {});
                        }

                        // --- Initial Setup ---
                        if(pInputImg) {
                            pInputImg.addEventListener('change', function() { if(this.files && this.files[0]) { let reader = new FileReader(); reader.onload = function(e) { pImgPreview.src = e.target.result; pImgWrap.classList.remove('hidden'); }; reader.readAsDataURL(this.files[0]); } });
                            document.getElementById('profile_clear_img').addEventListener('click', function() { pInputImg.value = ''; pImgWrap.classList.add('hidden'); });
                        }
                        if (profileChatForm) {
                            pInputText.addEventListener('input', handleTyping);
                            profileChatForm.addEventListener('submit', handleFormSubmit);
                        }
                        setInterval(fetchNewMessages, 3000);
                    </script>
                </div>

                <!-- ความปลอดภัย -->
                <?php if (empty($user['oauth_provider'])): // แสดงส่วนนี้เฉพาะผู้ใช้ที่สมัครแบบปกติ ?>
                    <div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center"><span class="bg-blue-100 text-blue-700 w-8 h-8 rounded-full flex justify-center items-center mr-3 text-sm">🔒</span> เปลี่ยนรหัสผ่าน</h2>
                        <form action="" method="POST" class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านเดิม</label>
                                <input type="password" name="old_password" required class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่านใหม่</label>
                                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">ยืนยันรหัสผ่านใหม่</label>
                                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
                                </div>
                            </div>
                            <button type="submit" name="change_password" class="mt-2 w-full md:w-auto px-8 bg-gray-800 text-white py-3 rounded-xl font-bold hover:bg-gray-900 transition shadow-md">เปลี่ยนรหัสผ่าน</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-gray-400 py-8 text-center mt-auto">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
</body>
</html>