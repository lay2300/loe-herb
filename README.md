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
5. เปิดใช้งานผ่าน `http://localhost:8080/loei-herb/`

## OAuth setup

คัดลอก `config/oauth.local.example.php` เป็น `config/oauth.local.php` แล้วใส่ credentials ชุดใหม่ของ Google และ Facebook ในไฟล์ local นี้ ไฟล์ดังกล่าวถูกยกเว้นจาก Git แล้ว

ใช้ Redirect URI เหล่านี้ใน OAuth provider:

- `http://localhost:8080/loei-herb/google_callback.php`
- `http://localhost:8080/loei-herb/facebook_callback.php`
- `http://localhost:8080/loei-herb/admin/google_callback.php`

# loe-herb
