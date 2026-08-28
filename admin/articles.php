<?php
require_once 'admin_bootstrap.php';

// ดึงข้อมูลบทความทั้งหมด
$articles = [];
$table_error = false;
try {
    $sql = "SELECT * FROM articles ORDER BY created_at DESC";
    $stmt = $conn->query($sql);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $table_error = true;
    } else {
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>จัดการบทความ - Admin</title>
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
                <a href="articles.php" class="block py-2.5 px-4 rounded-xl bg-green-800 text-white font-semibold shadow-sm"><i class="fi fi-rr-document"></i> จัดการบทความ</a>
                <a href="add.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-add"></i> เพิ่มสมุนไพรใหม่</a>
                <a href="users.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-users"></i> ข้อมูลผู้ใช้งาน</a>
                <a href="chats.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition relative"><i class="fi fi-rr-comment"></i> ศูนย์ข้อความ (แชท) <?php if($total_admin_unread > 0): ?><span class="absolute right-4 top-3 bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center font-bold rounded-full border border-green-800"><?php echo $total_admin_unread; ?></span><?php endif; ?></a>
                <a href="../index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition mt-6 border-t border-green-800 pt-6"><i class="fi fi-rr-globe"></i> ไปที่หน้าเว็บหลัก</a>
                <a href="change_password.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-key"></i> เปลี่ยนรหัสผ่าน</a>
                <a href="logout.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-red-600 hover:text-white transition"><i class="fi fi-rr-exit"></i> ออกจากระบบ</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="animate-slide-up flex justify-between items-center mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">จัดการบทความภูมิปัญญาท้องถิ่น</h2>
                    <?php if ($table_error): ?>
                        <div class="mt-2 text-red-700 bg-red-100 p-3 rounded-lg border border-red-300">
                            ⚠️ <b>ไม่พบตารางบทความ!</b> กรุณา <a href="../install_articles.php" class="underline font-bold text-red-800">คลิกที่นี่เพื่อติดตั้งตาราง</a> ก่อนใช้งาน
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm mt-1">บทความทั้งหมด <?php echo count($articles); ?> รายการ</p>
                    <?php endif; ?>
                </div>
                <a href="article_add.php" class="bg-green-600 text-white rounded-lg px-4 py-2 hover:bg-green-700 transition flex items-center shadow-sm">
                    <i class="fi fi-rr-pencil mr-2"></i>
                    เขียนบทความใหม่
                </a>
            </div>

            <div class="animate-slide-up delay-100 table-container overflow-hidden">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="p-4 font-semibold text-gray-500 text-sm">รูปปก</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">หัวข้อบทความ</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">ผู้เขียน</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">วันที่</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (count($articles) > 0): ?>
                            <?php foreach ($articles as $row): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="p-4">
                                    <?php if ($row['image_path']): ?>
                                        <img src="../uploads/<?php echo $row['image_path']; ?>" class="w-20 h-14 object-cover rounded-lg shadow-sm">
                                    <?php else: ?>
                                        <div class="w-20 h-14 bg-gray-100 rounded-lg flex items-center justify-center text-xs text-gray-400">No Img</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-gray-800"><?php echo $row['title']; ?></div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <?php 
                                            $tags = explode(',', $row['tags']);
                                            foreach($tags as $tag) {
                                                if(trim($tag)) echo "<span class='bg-gray-100 px-2 py-0.5 rounded mr-1'>#".trim($tag)."</span>";
                                            }
                                        ?>
                                    </div>
                                </td>
                                <td class="p-4 text-sm text-gray-600"><?php echo $row['author']; ?></td>
                                <td class="p-4 text-sm text-gray-600"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                <td class="p-4 text-center space-x-2">
                                    <a href="article_edit.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm"><i class="fi fi-rr-edit"></i> แก้ไข</a>
                                    <a href="article_delete.php?id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('ยืนยันการลบบทความนี้?')" 
                                       class="text-red-600 hover:text-red-800 font-medium text-sm"><i class="fi fi-rr-trash"></i> ลบ</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-10 text-center text-gray-400 italic">ยังไม่มีบทความในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

</body>
</html>