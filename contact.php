<?php
session_start();
require_once 'config/db.php';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/contact.css">
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
                <svg viewBox="0 0 100 80" fill="currentColor">
                    <rect width="100" height="10"></rect>
                    <rect y="30" width="100" height="10"></rect>
                    <rect y="60" width="100" height="10"></rect>
                </svg>
            </label>

            <div class="nav-links md:flex space-x-6 items-center">
                <a href="index.php" class="text-gray-600 hover:text-green-700 font-medium transition">หน้าแรก</a>
                <a href="categories.php" class="text-gray-600 hover:text-green-700 font-medium transition">หมวดหมู่สรรพคุณ</a>
                <a href="wisdom.php" class="text-gray-600 hover:text-green-700 font-medium transition">ภูมิปัญญาท้องถิ่น</a>
                <a href="contact.php" class="text-green-700 font-bold transition">ติดต่อเรา</a>

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
            <h1 class="text-3xl md:text-5xl font-bold mb-4">📞 ติดต่อเรา</h1>
            <p class="text-green-100 text-lg">สอบถามข้อมูลเพิ่มเติม หรือแนะนำสมุนไพรใหม่ๆ</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            
            <!-- Contact Info -->
            <div class="space-y-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-l-4 border-green-600 pl-4">ข้อมูลการติดต่อ</h2>
                    <p class="text-gray-600 mb-6">หากท่านมีข้อสงสัยเกี่ยวกับข้อมูลสมุนไพร หรือต้องการให้ข้อมูลเพิ่มเติม สามารถติดต่อทีมงานผู้จัดทำได้ตามช่องทางด้านล่างนี้</p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="contact-icon-box">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">ที่อยู่</h3>
                                <p class="text-gray-600">ศูนย์การเรียนรู้ภูมิปัญญาท้องถิ่นจังหวัดเลย<br>อำเภอเมืองเลย จังหวัดเลย 42000</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="contact-icon-box">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">เบอร์โทรศัพท์</h3>
                                <p class="text-gray-600">0XX-XXX-XXXX (คุณ Naruwan)</p>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="contact-icon-box">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800">อีเมล</h3>
                                <p class="text-gray-600">contact@loeiherb.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Embed (Placeholder) -->
                <div class="bg-gray-200 rounded-2xl h-64 overflow-hidden shadow-inner">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d60996.65136666874!2d101.69165566900363!3d17.49252836266049!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x312150a00600371d%3A0x3646545242780614!2z4LmA4Lih4Li34Lit4LiH4LmA4Lil4Lii!5e0!3m2!1sth!2sth!4v1707720000000!5m2!1sth!2sth" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form-container bg-white p-8 rounded-3xl border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">ส่งข้อความถึงเรา</h2>
                <form action="#" method="POST" onsubmit="alert('ขอบคุณสำหรับข้อความค่ะ ระบบได้รับข้อมูลเรียบร้อยแล้ว (Demo)'); return false;">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อ-นามสกุล</label>
                            <input type="text" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
                            <input type="email" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">หัวข้อเรื่อง</label>
                            <select class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition">
                                <option>สอบถามข้อมูลสมุนไพร</option>
                                <option>แจ้งข้อมูลสมุนไพรใหม่</option>
                                <option>แจ้งปัญหาการใช้งานเว็บไซต์</option>
                                <option>อื่นๆ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">ข้อความ</label>
                            <textarea rows="5" required class="w-full px-4 py-2 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-xl transition shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                            ส่งข้อความ
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
    <?php include 'chat_widget.php'; ?>
</body>
</html>