<?php
if (session_status() === PHP_SESSION_NONE) { // ป้องกันการ start session ซ้ำ
    session_start();
}

// 1. ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';

// 2. ดึงข้อมูลที่ใช้ร่วมกัน เช่น จำนวนข้อความที่ยังไม่ได้อ่าน
$total_admin_unread = 0;
try { 
    $total_admin_unread = $conn->query("SELECT COUNT(*) FROM notifications WHERE sender = 'user' AND is_read = 0")->fetchColumn(); 
} catch(PDOException $e){
    // ถ้าตาราง notifications ยังไม่มี ให้ข้ามไปก่อน
    $total_admin_unread = 0;
}

require_once '../config/thai_geography.php'; // ดึงข้อมูลอำเภอ/ตำบล
?>