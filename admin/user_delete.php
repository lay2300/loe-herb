<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // ดึงข้อมูลรูปโปรไฟล์เพื่อทำการลบไฟล์ออกจากเครื่องด้วย (ถ้ามี)
    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (!empty($user['avatar']) && file_exists("../uploads/" . $user['avatar'])) {
            @unlink("../uploads/" . $user['avatar']); // ลบไฟล์รูป
        }
        // ลบข้อมูลออกจากฐานข้อมูล
        $conn->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $id]);
    }
}
header("Location: users.php");
exit();
?>