<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS articles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        image_path VARCHAR(255),
        author VARCHAR(100),
        tags VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "✅ สร้างตารางบทความ (articles) สำเร็จแล้ว! <br>";
    echo "<a href='admin/index.php'>ไปที่หน้า Admin Dashboard</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>