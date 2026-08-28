<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

// ตั้งค่าชื่อไฟล์ดาวน์โหลด (เช่น loei_herb_backup_2026-02-16.sql)
$filename = "loei_herb_backup_" . date("Y-m-d_H-i-s") . ".sql";

// Header สำหรับสั่งให้ Browser ดาวน์โหลดไฟล์
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"" . $filename . "\""); 

// ส่วนหัวของไฟล์ SQL
echo "-- LoeiHerb Database Backup\n";
echo "-- Generated: " . date("Y-m-d H:i:s") . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+07:00\";\n\n";

// รายชื่อตารางทั้งหมดในระบบ
$tables = ['admins', 'herbs', 'herb_images', 'articles'];

foreach ($tables as $table) {
    // 1. สร้างคำสั่งสร้างตาราง (CREATE TABLE)
    echo "-- Table structure for table `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    try {
        $stmt = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . ";\n\n";
    } catch (PDOException $e) {
        continue; // ถ้าตารางไม่มีอยู่จริง ให้ข้ามไป
    }

    // 2. สร้างคำสั่งเพิ่มข้อมูล (INSERT INTO)
    echo "-- Dumping data for table `$table`\n";
    try {
        $stmt = $conn->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            echo "INSERT INTO `$table` VALUES\n";
            $count = 0;
            $total = count($rows);
            
            foreach ($rows as $row) {
                $count++;
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        // ใช้ quote เพื่อจัดการเรื่องเครื่องหมาย ' หรือ " ในข้อความ
                        $values[] = $conn->quote($value);
                    }
                }
                echo "(" . implode(", ", $values) . ")";
                // ถ้าไม่ใช่แถวสุดท้าย ให้ใส่ลูกน้ำ คั่น
                echo ($count < $total) ? ",\n" : ";\n";
            }
        }
        echo "\n\n";
    } catch (PDOException $e) {
        continue;
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "COMMIT;";
exit();
?>