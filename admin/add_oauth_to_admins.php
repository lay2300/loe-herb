<?php
require_once '../config/db.php';

echo "<div style='font-family: Sarabun, sans-serif; padding: 20px;'>";
echo "<h2>กำลังอัปเดตตาราง `admins`...</h2>";

$columns = [
    "oauth_provider" => "VARCHAR(50) NULL",
    "oauth_uid"      => "VARCHAR(255) NULL",
    "email"          => "VARCHAR(100) NULL UNIQUE",
    "avatar"         => "VARCHAR(255) NULL"
];

foreach ($columns as $column => $type) {
    try {
        $conn->exec("ALTER TABLE admins ADD COLUMN `$column` $type");
        echo "<p style='color: green;'>✅ เพิ่มคอลัมน์ `$column` สำเร็จ</p>";
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1060) { // Error for duplicate column
            echo "<p style='color: orange;'>ℹ️ คอลัมน์ `$column` มีอยู่แล้ว (ข้าม)</p>";
        } else {
            echo "<p style='color: red;'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h3 style='color: blue; margin-top: 20px;'>อัปเดตโครงสร้างฐานข้อมูลเรียบร้อยแล้ว</h3>";
echo "<a href='index.php' style='display: inline-block; margin-top: 10px; padding: 10px 15px; background-color: #166534; color: white; text-decoration: none; border-radius: 8px;'>กลับหน้า Dashboard</a>";
echo "</div>";
?>