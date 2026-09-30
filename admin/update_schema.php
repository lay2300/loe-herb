<?php
require_once '../config/db.php';
header('Content-Type: text/html; charset=utf-8');

$alter_statements = [
    "ADD COLUMN family_name VARCHAR(255) AFTER sci_name",
    "ADD COLUMN category VARCHAR(100) AFTER family_name",
    "ADD COLUMN other_names TEXT AFTER category",
    "ADD COLUMN general_characteristics TEXT AFTER other_names",
    "ADD COLUMN parts_used TEXT AFTER properties",
    "ADD COLUMN culinary_uses TEXT AFTER parts_used",
    "ADD COLUMN additional_info TEXT AFTER culinary_uses",
    "ADD COLUMN coordinates VARCHAR(255) AFTER location_found",
    "ADD COLUMN sub_district VARCHAR(255) AFTER location_found"
];

echo "<h2>กำลังตรวจสอบตารางในฐานข้อมูล...</h2>";

try {
    // 1. ตาราง Admins
    $sql_admins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_admins);
    echo "✅ ตาราง admins: พร้อมใช้งาน<br>";

    $admin_columns = [
        'display_name' => 'VARCHAR(100) NULL',
        'email' => 'VARCHAR(100) NULL UNIQUE',
        'phone' => 'VARCHAR(30) NULL',
        'oauth_provider' => 'VARCHAR(50) NULL',
        'oauth_uid' => 'VARCHAR(255) NULL',
        'avatar' => 'VARCHAR(255) NULL'
    ];
    foreach ($admin_columns as $column => $definition) {
        try {
            $conn->exec("ALTER TABLE admins ADD COLUMN `$column` $definition");
            echo "✅ เพิ่มคอลัมน์ admins.$column<br>";
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1060) {
                echo "ℹ️ มีคอลัมน์ admins.$column แล้ว (ข้าม)<br>";
            } else {
                echo "❌ เพิ่มคอลัมน์ admins.$column ไม่สำเร็จ<br>";
            }
        }
    }

    // 2. ตาราง Herbs (สร้างโครงสร้างหลักก่อน แล้วค่อย Alter เพิ่มคอลัมน์)
    $sql_herbs = "CREATE TABLE IF NOT EXISTS herbs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        thai_name VARCHAR(255) NOT NULL,
        local_name VARCHAR(255),
        sci_name VARCHAR(255),
        properties TEXT,
        location_found VARCHAR(255),
        image_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql_herbs);
    echo "✅ ตาราง herbs: พร้อมใช้งาน<br>";

    // 3. ตาราง Gallery
    $sql_gallery = "CREATE TABLE IF NOT EXISTS herb_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        herb_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (herb_id) REFERENCES herbs(id) ON DELETE CASCADE
    )";
    $conn->exec($sql_gallery);
    echo "✅ ตาราง herb_images (Gallery): พร้อมใช้งาน<br>";
} catch (PDOException $e) {
    echo "❌ ตาราง herb_images: " . $e->getMessage() . "<br>";
}

echo "<h2>กำลังอัปเดตโครงสร้างฐานข้อมูล...</h2>";

foreach ($alter_statements as $statement) {
    try {
        $conn->exec("ALTER TABLE herbs " . $statement);
        echo "✅ สำเร็จ: " . $statement . "<br>";
    } catch (PDOException $e) {
        // 1060 = Duplicate column name
        if (strpos($e->getMessage(), 'Duplicate column name') !== false || $e->errorInfo[1] == 1060) {
             echo "ℹ️ มีอยู่แล้ว (ข้าม): " . $statement . "<br>";
        } else {
             echo "❌ ผิดพลาด: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<h2>กำลังอัปเดตตาราง Users สำหรับระบบ Social Login...</h2>";
try {
    $conn->exec("ALTER TABLE users ADD COLUMN oauth_provider ENUM('none', 'google', 'facebook') DEFAULT 'none' AFTER password");
    $conn->exec("ALTER TABLE users ADD COLUMN oauth_uid VARCHAR(255) DEFAULT NULL AFTER oauth_provider");
    echo "✅ เพิ่มระบบ Social Login ลงในตาราง users สำเร็จ<br>";
} catch (PDOException $e) {
    echo "ℹ️ มีคอลัมน์สำหรับระบบ Social Login อยู่แล้ว (ข้าม)<br>";
}

echo "<br><a href='index.php'>กลับหน้า Dashboard</a>";
?>