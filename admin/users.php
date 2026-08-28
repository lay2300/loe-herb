<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

$users = [];
try {
    // ดึงข้อมูลผู้ใช้ จัดเรียงจากคนที่มีข้อความยังไม่ได้อ่านก่อน ตามด้วยล็อกอินล่าสุด
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.id AND n.sender = 'user' AND n.is_read = 0) as unread_count 
            FROM users u ORDER BY unread_count DESC, u.last_login DESC, u.created_at DESC";
    $stmt = $conn->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* สามารถเพิ่มการจัดการข้อผิดพลาดที่นี่ได้ */ }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>ข้อมูลผู้ใช้งาน - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
</head>
<body class="bg-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <aside class="w-full md:w-64 bg-green-900 p-6 text-white">
            <h1 class="text-2xl font-bold mb-8 text-white flex items-center">🌿 LoeiHerb <span class="text-xs bg-green-800 text-green-100 px-2 py-1 rounded ml-2">Admin</span></h1>
            <nav class="space-y-2">
                <a href="index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-document-signed"></i> รายการสมุนไพร</a>
                <a href="articles.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-document"></i> จัดการบทความ</a>
                <a href="add.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-add"></i> เพิ่มสมุนไพรใหม่</a>
                <a href="users.php" class="block py-2.5 px-4 rounded-xl bg-green-800 text-white font-semibold shadow-sm"><i class="fi fi-rr-users"></i> ข้อมูลผู้ใช้งาน</a>
                <a href="../index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition mt-6 border-t border-green-800 pt-6"><i class="fi fi-rr-globe"></i> ไปที่หน้าเว็บหลัก</a>
                <a href="change_password.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-key"></i> เปลี่ยนรหัสผ่าน</a>
                <a href="logout.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-red-600 hover:text-white transition"><i class="fi fi-rr-exit"></i> ออกจากระบบ</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="animate-slide-up mb-8">
                <h2 class="text-2xl font-bold text-gray-800">จัดการข้อมูลผู้ใช้งาน</h2>
                <p class="text-gray-500 text-sm mt-1">ประวัติการสมัครสมาชิกและการเข้าสู่ระบบ</p>
            </div>

            <div class="animate-slide-up delay-100 table-container overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="p-4 font-semibold text-gray-500 text-sm">ชื่อผู้ใช้ (Username)</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">อีเมล</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">วันที่สมัครสมาชิก</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">ใช้งานล่าสุด</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $row): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4 font-bold text-gray-800 flex items-center gap-3">
                                    <div class="bg-green-100 text-green-700 font-bold w-8 h-8 rounded-full flex items-center justify-center text-sm"><?php echo mb_substr($row['username'], 0, 1); ?></div>
                                    <?php echo htmlspecialchars($row['username']); ?>
                                </td>
                                <td class="p-4 text-gray-600"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td class="p-4 text-sm text-gray-600"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td class="p-4 text-sm <?php echo !empty($row['last_login']) ? 'text-green-600 font-medium' : 'text-gray-400'; ?>"><?php echo !empty($row['last_login']) ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'ยังไม่เคยเข้าสู่ระบบ'; ?></td>
                                <td class="p-4 text-center space-x-2">
                                    <a href="chats.php?id=<?php echo $row['id']; ?>" class="inline-flex items-center text-yellow-600 hover:text-yellow-800 font-medium text-sm relative transition">
                                        <i class="fi fi-rr-envelope mr-1"></i> แชท
                                        <?php if($row['unread_count'] > 0): ?>
                                            <span class="absolute -top-3 -right-3 bg-red-500 text-white text-[10px] w-4 h-4 flex items-center justify-center rounded-full font-bold shadow-sm"><?php echo $row['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </a>
                                    <a href="user_edit.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm"><i class="fi fi-rr-edit"></i> แก้ไข</a>
                                    <a href="user_delete.php?id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('ยืนยันการลบผู้ใช้งาน [<?php echo htmlspecialchars($row['username']); ?>] ออกจากระบบถาวรหรือไม่?')" 
                                       class="text-red-600 hover:text-red-800 font-medium text-sm"><i class="fi fi-rr-trash"></i> ลบ</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="p-10 text-center text-gray-400 italic">ยังไม่มีผู้ใช้งานในระบบ</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>