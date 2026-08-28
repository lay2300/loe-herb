<?php
session_start();
// ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

// 1. ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/db.php';

// 2. ตรวจสอบว่ามี ID ส่งมาให้ลบหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // --- ส่วนที่ 1: ลบไฟล์รูปภาพออกจากเครื่อง ---
        // ดึงชื่อไฟล์รูปภาพจากฐานข้อมูลขึ้นมาก่อน
        $stmt_img = $conn->prepare("SELECT image_path FROM herbs WHERE id = :id");
        $stmt_img->execute(['id' => $id]);
        $row = $stmt_img->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['image_path'] != "") {
            $file_path = "../uploads/" . $row['image_path'];
            // ตรวจสอบว่ามีไฟล์อยู่จริงไหม ถ้ามีให้ลบทิ้ง (unlink)
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }

        // --- ส่วนที่ 1.5: ลบไฟล์รูปภาพใน Gallery ด้วย ---
        $stmt_gallery = $conn->prepare("SELECT image_path FROM herb_images WHERE herb_id = :id");
        $stmt_gallery->execute(['id' => $id]);
        $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($gallery_images as $g_img) {
            if (!empty($g_img['image_path'])) {
                $g_file_path = "../uploads/" . $g_img['image_path'];
                if (file_exists($g_file_path)) { unlink($g_file_path); }
            }
        }

        // --- ส่วนที่ 2: ลบข้อมูลออกจากฐานข้อมูล ---
        $sql = "DELETE FROM herbs WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);

        // เมื่อลบเสร็จแล้ว ให้เด้งกลับไปหน้า Dashboard (index.php)
        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        // หากเกิดข้อผิดพลาด ให้แสดงข้อความ
        die("เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage());
    }
} else {
    // ถ้าไม่มี ID ส่งมา ให้เด้งกลับหน้าหลักทันที
    header("Location: index.php");
    exit();
}
?>