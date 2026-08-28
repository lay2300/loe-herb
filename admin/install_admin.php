<?php
require_once '../config/db.php';

try {
    // 1. สร้างตาราง admins ถ้ายังไม่มี
    $sql = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->exec($sql);

    // 2. ตรวจสอบว่ามี admin หรือยัง
    $stmt = $conn->prepare("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        // 3. เพิ่ม admin เริ่มต้น (รหัส: 1234)
        // password_hash จะทำการเข้ารหัสรหัสผ่านให้ปลอดภัย (อ่านไม่ออก)
        $password = password_hash('1234', PASSWORD_DEFAULT);
        $sql = "INSERT INTO admins (username, password) VALUES ('admin', :password)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['password' => $password]);
        echo "✅ สร้างตารางและบัญชี Admin สำเร็จ! (User: admin / Pass: 1234)<br>";
    } else {
        echo "✅ มีบัญชี Admin อยู่แล้ว<br>";
    }
    echo "<a href='login.php'>ไปหน้า Login</a>";

} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1932) {
        echo "<div style='color:red; border:1px solid red; padding:20px; background:#fee; font-family:sans-serif;'>❌ <b>เกิดข้อผิดพลาดร้ายแรง (Error #1932)</b><br>ตารางฐานข้อมูลเสียหาย (Table doesn't exist in engine)<br>สาเหตุ: ไฟล์ ibdata1 ไม่ตรงกับไฟล์ในโฟลเดอร์ฐานข้อมูล<br><br><b>วิธีแก้ไข:</b><br>1. Stop MySQL<br>2. ลบโฟลเดอร์ <code>loei_herb_db</code> ใน <code>xampp/mysql/data/</code> ทิ้ง<br>3. Start MySQL และสร้างฐานข้อมูลใหม่<br>4. Import ไฟล์ <code>database.sql</code> ใหม่</div>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>