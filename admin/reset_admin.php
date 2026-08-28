<?php
// สคริปต์รีเซ็ตรหัสผ่านสำหรับแก้ไขปัญหา "รหัสผ่านไม่ถูกต้อง"
require_once '../config/db.php';

try {
    // 1. ตรวจสอบและสร้างตารางหากยังไม่มี (ป้องกัน Error table not found)
    $conn->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. ลบ user 'admin' เดิมออกก่อน (เพื่อล้างค่าเก่าที่อาจรหัสผิด)
    $conn->exec("DELETE FROM admins WHERE username = 'admin'");

    // 3. เพิ่ม user 'admin' ใหม่ ด้วยรหัส '1234'
    $password_hash = password_hash('1234', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES ('admin', :pass)");
    $stmt->execute(['pass' => $password_hash]);

    echo "<div style='text-align:center; padding: 50px; font-family: sans-serif;'>";
    echo "<h1 style='color:green;'>✅ รีเซ็ตรหัสผ่านสำเร็จ!</h1>";
    echo "<p>ตอนนี้คุณสามารถเข้าสู่ระบบได้ด้วยข้อมูล:</p>";
    echo "<div style='background:#f0f0f0; display:inline-block; padding:20px; border-radius:10px; text-align:left;'>";
    echo "Username: <b>admin</b><br>";
    echo "Password: <b>1234</b>";
    echo "</div><br><br>";
    echo "<a href='login.php' style='background: #166534; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>เข้าสู่ระบบทันที</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
