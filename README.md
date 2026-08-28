# LoeiHerb

ฐานข้อมูลสมุนไพรพื้นบ้านและภูมิปัญญาท้องถิ่นจังหวัดเลย

## Requirements

- XAMPP (Apache และ MySQL)
- PHP และ MySQL

## Local setup

1. วางโปรเจกต์ไว้ใน `C:\xampp\htdocs\loei-herb`
2. สร้างฐานข้อมูล `loei_herb_db`
3. นำเข้า `admin/database.sql`
4. ตั้งค่า OAuth ใน `config/oauth.local.php` โดยอ้างอิงจาก `config/oauth.local.example.php`
5. เปิดใช้งานผ่าน `http://localhost/loei-herb/`
