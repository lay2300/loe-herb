<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

if (!isset($_GET['id'])) { header("Location: users.php"); exit(); }
$id = $_GET['id'];

// ดึงข้อมูลผู้ใช้ปัจจุบัน
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: users.php"); exit(); }

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $new_password = $_POST['new_password'];

    // เช็คว่าอีเมลใหม่ไปซ้ำกับคนอื่นหรือไม่
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $id]);
    
    if ($check->rowCount() > 0) {
        $error = "อีเมลนี้มีผู้ใช้งานอื่นใช้อยู่แล้วครับ";
    } else {
        if (!empty($new_password)) {
            // ถ้าระบุรหัสผ่านใหม่ด้วย
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET email=?, first_name=?, last_name=?, phone=?, password=? WHERE id=?";
            $update = $conn->prepare($sql);
            $update->execute([$email, $first_name, $last_name, $phone, $hashed_password, $id]);
        } else {
            // แก้ไขแค่ข้อมูลทั่วไป (รหัสผ่านเดิม)
            $sql = "UPDATE users SET email=?, first_name=?, last_name=?, phone=? WHERE id=?";
            $update = $conn->prepare($sql);
            $update->execute([$email, $first_name, $last_name, $phone, $id]);
        }
        $success = "บันทึกการแก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว";
        
        // ดึงข้อมูลมาอัปเดตเพื่อแสดงผลทันที
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>แก้ไขผู้ใช้งาน - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="max-w-3xl mx-auto py-10 px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h2 class="text-2xl font-bold text-gray-800">👤 แก้ไขข้อมูลผู้ใช้: @<?php echo htmlspecialchars($user['username']); ?></h2>
                <a href="users.php" class="text-gray-500 hover:text-green-700 font-medium">← กลับไปหน้าจัดการผู้ใช้</a>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-xl mb-6 border border-red-100 shadow-sm">⚠️ <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="bg-green-50 text-green-700 p-4 rounded-xl mb-6 border border-green-100 shadow-sm">✅ <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">นามสกุล</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none bg-yellow-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100">
                    <h3 class="text-lg font-bold text-red-700 mb-4">🔒 รีเซ็ตรหัสผ่าน (โซนอันตราย)</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งรหัสผ่านใหม่ให้ผู้ใช้ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
                        <input type="text" name="new_password" placeholder="พิมพ์รหัสผ่านใหม่ที่นี่..." class="w-full px-4 py-2 rounded-lg border border-red-300 focus:ring-2 focus:ring-red-500 outline-none">
                        <p class="text-xs text-gray-400 mt-1">หากคุณกรอกช่องนี้ รหัสผ่านของผู้ใช้จะถูกเปลี่ยนทันทีหลังจากกดบันทึก</p>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-md">
                        💾 บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>