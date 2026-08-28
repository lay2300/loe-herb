<?php
// 1. กำหนดค่าเริ่มต้นของ Server
$host = "localhost";      // ส่วนใหญ่คือ localhost
$dbname = "loei_herb_db"; // ต้องตรงกับชื่อฐานข้อมูลที่สร้างใน phpMyAdmin
$username = "root";       // ค่าเริ่มต้นของ XAMPP คือ root
$password = "";           // ค่าเริ่มต้นของ XAMPP คือว่างไว้ (ไม่ต้องใส่รหัส)

try {
    // 2. เริ่มต้นการเชื่อมต่อด้วย PDO (ปลอดภัยจากการโดน Hack)
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // 3. ตั้งค่าให้ PHP แจ้งเตือนเวลาเขียน SQL ผิด (ช่วยให้เราแก้บั๊กง่ายขึ้น)
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // (ทางเลือก) เปิดส่วนนี้ไว้ทดสอบ ถ้าเชื่อมผ่านมันจะขึ้นคำว่า "Connected"
    // echo "เชื่อมต่อสำเร็จ!"; 
    
} catch(PDOException $e) {
    // 4. ถ้าเชื่อมต่อไม่ได้ ให้หยุดทำงานและโชว์ข้อความผิดพลาด
    die("ขออภัย! ไม่สามารถเชื่อมต่อฐานข้อมูลได้: " . $e->getMessage());
}
?>
