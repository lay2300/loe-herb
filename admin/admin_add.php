<?php
require_once '../config/security.php';
start_secure_session();

if (!isset($_SESSION['admin_login'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/db.php';

$error = '';
$success = '';
$schema_ready = true;

try {
    $conn->query('SELECT display_name, email, phone FROM admins LIMIT 0');
} catch (PDOException $e) {
    $schema_ready = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $schema_ready) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $display_name = trim($_POST['display_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($display_name === '' || mb_strlen($display_name) > 100) {
            $error = 'กรุณากรอกชื่อไม่เกิน 100 ตัวอักษร';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            $error = 'ชื่อผู้ใช้ต้องมี 3-50 ตัว และใช้ได้เฉพาะ a-z, A-Z, 0-9 หรือ _';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
        } elseif (mb_strlen($phone) > 30) {
            $error = 'เบอร์โทรต้องไม่เกิน 30 ตัวอักษร';
        } elseif (strlen($password) < 12) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[^A-Za-z0-9\s]/', $password)) {
            $error = 'รหัสผ่านต้องมีตัวอักษรภาษาอังกฤษอย่างน้อย 1 ตัว และอักขระพิเศษอย่างน้อย 1 ตัว';
        } else {
            try {
                $check = $conn->prepare('SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1');
                $check->execute([$username, $email]);
                if ($check->fetchColumn()) {
                    $error = 'ชื่อผู้ใช้หรืออีเมลนี้มีในระบบแล้ว';
                } else {
                    $stmt = $conn->prepare('INSERT INTO admins (username, display_name, email, phone, password) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$username, $display_name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
                    $success = 'เพิ่มบัญชีแอดมินเรียบร้อยแล้ว';
                }
            } catch (PDOException $e) {
                $error = 'บันทึกบัญชีไม่สำเร็จ กรุณาตรวจสอบว่าชื่อผู้ใช้หรืออีเมลซ้ำหรือไม่';
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
    <title>เพิ่มผู้ดูแลระบบ - LoeiHerb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="min-h-screen bg-gray-100 p-4 md:p-8">
    <main class="mx-auto max-w-xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm md:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-gray-800">เพิ่มผู้ดูแลระบบ</h1>
            <a href="index.php" class="text-sm text-green-700 hover:text-green-900">กลับ Dashboard</a>
        </div>

        <?php if (!$schema_ready): ?>
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                กรุณาอัปเดตโครงสร้างฐานข้อมูลก่อนใช้งาน
                <a href="update_schema.php" class="ml-1 font-semibold underline">อัปเดตฐานข้อมูล</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700"><?php echo e($success); ?></div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <div>
                    <label for="display_name" class="mb-1 block text-sm font-medium text-gray-700">ชื่อที่แสดง</label>
                    <input id="display_name" name="display_name" type="text" maxlength="100" required class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="username" class="mb-1 block text-sm font-medium text-gray-700">ชื่อผู้ใช้</label>
                    <input id="username" name="username" type="text" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" required autocomplete="username" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">อีเมล</label>
                    <input id="email" name="email" type="email" maxlength="100" required autocomplete="email" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">เบอร์โทรศัพท์ (ไม่บังคับ)</label>
                    <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                    <input id="password" name="password" type="password" minlength="12" pattern="(?=.*[A-Za-z])(?=.*[^A-Za-z0-9\s]).{12,}" title="ต้องมีอย่างน้อย 12 ตัวอักษร ประกอบด้วยอักษรภาษาอังกฤษและอักขระพิเศษ" required autocomplete="new-password" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <p class="mt-1 text-xs text-gray-500">อย่างน้อย 12 ตัวอักษร มีอักษรภาษาอังกฤษและอักขระพิเศษ เช่น ! @ # $</p>
                </div>
                <button type="submit" class="w-full rounded-lg bg-green-700 px-4 py-2.5 font-semibold text-white hover:bg-green-800">สร้างบัญชีแอดมิน</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>