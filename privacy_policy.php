<?php
session_start();
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นโยบายความเป็นส่วนตัว - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 text-gray-700">

    <!-- Navbar -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center relative">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="text-2xl text-green-800 font-bold tracking-tight">🌿 LoeiHerb</span>
            </a>

            <input id="nav-toggle" type="checkbox" />
            <label for="nav-toggle" class="nav-toggle-label md:hidden">
                <svg viewBox="0 0 100 80" fill="currentColor"><rect width="100" height="10"></rect><rect y="30" width="100" height="10"></rect><rect y="60" width="100" height="10"></rect></svg>
            </label>

            <div class="nav-links md:flex space-x-6 items-center">
                <a href="index.php" class="text-gray-600 hover:text-green-700 font-medium transition">หน้าแรก</a>
                <a href="categories.php" class="text-gray-600 hover:text-green-700 font-medium transition">หมวดหมู่สรรพคุณ</a>
                <a href="wisdom.php" class="text-gray-600 hover:text-green-700 font-medium transition">ภูมิปัญญาท้องถิ่น</a>
                <a href="contact.php" class="text-gray-600 hover:text-green-700 font-medium transition">ติดต่อเรา</a>

                <?php if(isset($_SESSION['user_login'])): ?>
                    <a href="profile.php" title="จัดการบัญชีผู้ใช้" class="text-green-800 font-bold bg-green-100 hover:bg-green-200 px-3 py-1 rounded-full text-sm flex items-center shadow-sm transition">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></a>
                    <a href="logout.php" class="text-sm text-red-600 hover:bg-red-50 px-4 py-2 rounded-full font-medium transition">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm text-green-700 hover:text-green-900 font-medium transition">เข้าสู่ระบบ</a>
                    <a href="register.php" class="text-sm bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-full font-medium transition shadow-sm">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header class="bg-green-800 text-white py-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')]"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4">
            <h1 class="text-3xl md:text-5xl font-bold mb-4">นโยบายความเป็นส่วนตัว</h1>
            <p class="text-green-100 text-lg">Privacy Policy</p>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-12">
        <div class="bg-white p-8 md:p-12 rounded-2xl shadow-sm border border-gray-100 prose prose-lg max-w-none">
            <h2>1. ข้อมูลที่เรารวบรวม</h2>
            <p>เมื่อคุณสมัครสมาชิกหรือเข้าสู่ระบบผ่านบริการของบุคคลที่สาม เช่น Google หรือ Facebook เราจะรวบรวมข้อมูลเท่าที่จำเป็นเพื่อใช้ในการยืนยันตัวตนและสร้างบัญชีผู้ใช้ของคุณ ซึ่งอาจรวมถึง:</p>
            <ul>
                <li>ชื่อ-นามสกุล (First name, Last name)</li>
                <li>อีเมล (Email Address)</li>
                <li>รูปโปรไฟล์ (Profile Picture)</li>
                <li>รหัสเฉพาะจากผู้ให้บริการ (OAuth UID)</li>
            </ul>

            <h2>2. วัตถุประสงค์ในการใช้ข้อมูล</h2>
            <p>เราใช้ข้อมูลที่รวบรวมเพื่อวัตถุประสงค์ดังต่อไปนี้:</p>
            <ul>
                <li>เพื่อสร้างและจัดการบัญชีผู้ใช้ของคุณ</li>
                <li>เพื่ออำนวยความสะดวกในการเข้าสู่ระบบเว็บไซต์</li>
                <li>เพื่อแสดงชื่อและรูปโปรไฟล์ของคุณภายในเว็บไซต์ เช่น ในหน้าข้อมูลส่วนตัว หรือส่วนแสดงความคิดเห็น</li>
                <li>เพื่อใช้ในการติดต่อสื่อสารกับคุณผ่านระบบแจ้งเตือนภายในเว็บไซต์</li>
            </ul>
            <p>เราจะไม่เปิดเผยข้อมูลส่วนตัวของคุณให้กับบุคคลที่สามโดยไม่ได้รับความยินยอมจากคุณ ยกเว้นในกรณีที่กฎหมายกำหนด</p>
        </div>
    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
    <?php include 'chat_widget.php'; ?>
</body>
</html>