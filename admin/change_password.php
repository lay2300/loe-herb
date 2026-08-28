<?php
session_start();
require_once '../config/db.php';

// ตรวจสอบ Login
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

// ถ้ายังไม่มี admin_id ใน session (กรณีล็อกอินค้างไว้จากระบบเก่า) ให้ดึงจาก username admin
if (!isset($_SESSION['admin_id'])) {
    try {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['admin_id'] = $user['id'];
        }
    } catch (PDOException $e) {
        // ถ้ายังไม่ได้สร้างตาราง ให้แจ้งเตือนและลิ้งค์ไปหน้าติดตั้ง
        die("⚠️ ไม่พบตารางฐานข้อมูลผู้ใช้ <br>กรุณารันไฟล์ <a href='install_admin.php'>install_admin.php</a> เพื่อติดตั้งระบบก่อนใช้งานครับ");
    }
}

$msg = "";
$msg_type = "";

if (isset($_POST['submit'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!isset($_SESSION['admin_id'])) {
         $msg = "ไม่พบข้อมูลผู้ใช้ กรุณาล็อกอินใหม่";
         $msg_type = "error";
    } else {
        $id = $_SESSION['admin_id'];

        // 1. ดึงรหัสผ่านเดิมจาก DB มาตรวจสอบ
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['password'])) {
            // 2. ตรวจสอบว่ารหัสใหม่ตรงกันไหม
            if ($new_password === $confirm_password) {
                // 3. อัปเดตรหัสผ่านใหม่ (Hash ก่อนบันทึก)
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE admins SET password = :p WHERE id = :id");
                
                if ($update->execute(['p' => $new_hash, 'id' => $id])) {
                    $msg = "เปลี่ยนรหัสผ่านสำเร็จเรียบร้อยแล้ว!";
                    $msg_type = "success";
                } else {
                    $msg = "เกิดข้อผิดพลาดในการบันทึก";
                    $msg_type = "error";
                }
            } else {
                $msg = "รหัสผ่านใหม่ไม่ตรงกัน";
                $msg_type = "error";
            }
        } else {
            $msg = "รหัสผ่านเดิมไม่ถูกต้อง";
            $msg_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>เปลี่ยนรหัสผ่าน - LoeiHerb Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">

    <div class="bg-white p-8 rounded-xl shadow-lg w-96">
        <h2 class="text-2xl font-bold text-center text-blue-800 mb-6">🔑 เปลี่ยนรหัสผ่าน</h2>
        
        <?php if($msg): ?>
            <div class="<?php echo $msg_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> p-3 rounded mb-4 text-sm text-center">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-600 mb-1">รหัสผ่านเดิม</label>
                <input type="password" name="current_password" required class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <hr class="border-gray-200">
            <div>
                <label class="block text-sm text-gray-600 mb-1">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" required class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" required class="w-full border rounded-lg p-2 focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            
            <button type="submit" name="submit" class="w-full bg-blue-700 text-white py-2 rounded-lg hover:bg-blue-800 font-bold transition">บันทึกรหัสผ่านใหม่</button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="index.php" class="text-sm text-gray-500 hover:text-blue-700">← กลับหน้า Dashboard</a>
        </div>
    </div>

</body>
</html>