<?php
require_once '../config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS herb_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        herb_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (herb_id) REFERENCES herbs(id) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    echo "✅ สร้างตารางแกลเลอรี่ (herb_images) สำเร็จแล้ว! <br>";
    echo "<a href='index.php'>กลับหน้า Dashboard</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>