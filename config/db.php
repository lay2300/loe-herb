<?php
// 1. กำหนดค่าเริ่มต้นของ Server
$host = getenv('LOEI_DB_HOST') ?: 'localhost';
$dbname = getenv('LOEI_DB_NAME') ?: 'loei_herb_db';
$username = getenv('LOEI_DB_USER') ?: 'root';
$password = getenv('LOEI_DB_PASSWORD') ?: '';

try {
    // 2. เริ่มต้นการเชื่อมต่อด้วย PDO (ปลอดภัยจากการโดน Hack)
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // 3. ตั้งค่าให้ PHP แจ้งเตือนเวลาเขียน SQL ผิด (ช่วยให้เราแก้บั๊กง่ายขึ้น)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // (ทางเลือก) เปิดส่วนนี้ไว้ทดสอบ ถ้าเชื่อมผ่านมันจะขึ้นคำว่า "Connected"
    // echo "เชื่อมต่อสำเร็จ!"; 
    
} catch(PDOException $e) {
    // 4. ถ้าเชื่อมต่อไม่ได้ ให้หยุดทำงานและโชว์ข้อความผิดพลาด
    error_log('Database connection failed: ' . $e->getMessage());
    die('ขออภัย! ระบบฐานข้อมูลไม่พร้อมใช้งานในขณะนี้');
}
?>
