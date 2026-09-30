<?php
// ต้องล็อกอินก่อน จึงจะใช้การรีเซ็ตแบบผู้ดูแลได้
session_start();
if (!isset($_SESSION['admin_login'])) {
    header('Location: login.php');
    exit();
}
require_once '../config/db.php';

try {
    // 1. ตรวจสอบและสร้างตารางหากยังไม่มี (ป้องกัน Error table not found)
    $conn->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "<div style='text-align:center; padding: 50px; font-family: sans-serif;'>";
    echo "<h1 style='color:green;'>✅ ตารางผู้ดูแลพร้อมใช้งาน</h1>";
    echo "<p>กรุณาใช้หน้าเปลี่ยนรหัสผ่านหลังจากเข้าสู่ระบบ</p>";
    echo "<a href='change_password.php'>ไปหน้าเปลี่ยนรหัสผ่าน</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
