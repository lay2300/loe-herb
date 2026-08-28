-- ==========================================
-- ฐานข้อมูลสำหรับโปรเจกต์: ระบบฐานข้อมูลสมุนไพรจังหวัดเลย (Loei Herb Database)
-- วิธีใช้งาน:
-- 1. เข้าไปที่ http://localhost/phpmyadmin
-- 2. คลิกเมนู "SQL" ด้านบน
-- 3. วางโค้ดทั้งหมดนี้ลงไปแล้วกดปุ่ม "Go"
-- ==========================================

-- สร้างฐานข้อมูล (ถ้ายังไม่มี)
CREATE DATABASE IF NOT EXISTS loei_herb_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE loei_herb_db;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

-- --------------------------------------------------------

-- 1. สร้างตารางผู้ดูแลระบบ (admins)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ล้างข้อมูล Admin เดิม (เพื่อป้องกันรหัสค้าง)
DELETE FROM admins WHERE username = 'admin';

-- เพิ่มข้อมูล Admin (Username: admin | Password: 1234)
-- หมายเหตุ: ใช้ password_hash('1234', PASSWORD_DEFAULT)
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa');

-- --------------------------------------------------------

-- 2. สร้างตารางสมุนไพร (herbs)
CREATE TABLE IF NOT EXISTS herbs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thai_name VARCHAR(255) NOT NULL,
    local_name VARCHAR(255),
    sci_name VARCHAR(255),
    family_name VARCHAR(255),
    category VARCHAR(100),
    other_names TEXT,
    general_characteristics TEXT,
    properties TEXT,
    parts_used TEXT,
    culinary_uses TEXT,
    additional_info TEXT,
    location_found VARCHAR(255),
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 3. สร้างตารางรูปภาพแกลเลอรี่ (herb_images)
CREATE TABLE IF NOT EXISTS herb_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    herb_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_herb FOREIGN KEY (herb_id) REFERENCES herbs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- 4. เพิ่มข้อมูลตัวอย่าง (Sample Data)
-- เพิ่มข้อมูลสมุนไพรเบื้องต้นเพื่อให้ระบบพร้อมใช้งานทันที
INSERT INTO herbs (thai_name, local_name, sci_name, family_name, category, other_names, general_characteristics, properties, parts_used, culinary_uses, additional_info, location_found, image_path) VALUES
('ฟ้าทะลายโจร', 'น้ำลายพังพอน', 'Andrographis paniculata (Burm.f.) Wall. ex Nees', 'Acanthaceae', 'ไม้ล้มลุก', 'Fa Thalai Chon', 'เป็นไม้ล้มลุก สูง 30-70 ซม. ลำต้นเป็นสี่เหลี่ยม แตกกิ่งก้านสาขามาก ใบเดี่ยว เรียงตรงข้าม รูปใบหอก', 'แก้ไข้หวัด แก้เจ็บคอ แก้ท้องเสีย เจริญอาหาร', 'ใบ, ทั้งต้น', '-', 'ห้ามใช้ในผู้ที่มีความดันโลหิตต่ำ และสตรีมีครรภ์', 'พบทั่วไปในจังหวัดเลย', ''),
('กระชายขาว', 'กระชายแกง', 'Boesenbergia rotunda (L.) Mansf.', 'Zingiberaceae', 'พืชหัว', 'Fingerroot', 'เป็นไม้ล้มลุก มีเหง้าใต้ดินสั้น และมีรากสะสมอาหารเป็นตุ้ม ออกเป็นกระจุก รูปทรงกระบอก ปลายเรียวแหลม', 'ขับลม แก้ท้องอืด ท้องเฟ้อ บำรุงกำลัง', 'เหง้า, ราก', 'ใช้เป็นส่วนผสมในเครื่องแกง ใส่ในผัดเผ็ด', '-', 'อำเภอภูเรือ, อำเภอด่านซ้าย', '');

COMMIT;