<?php
session_start();
require_once 'config/db.php';

// ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $conn->prepare("SELECT * FROM articles WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $article = $stmt->fetch(PDO::FETCH_ASSOC);

        // ถ้าไม่พบข้อมูลให้กลับไปหน้า wisdom
        if (!$article) {
            header("Location: wisdom.php");
            exit();
        }
        
        // แปลง Tags เป็น Array
        $tags = array_filter(explode(',', $article['tags']));

    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: wisdom.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article['title']; ?> - ภูมิปัญญาท้องถิ่น</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/wisdom_detail.css">
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
                <a href="wisdom.php" class="text-green-700 font-bold transition">ภูมิปัญญาท้องถิ่น</a>
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

    <main class="max-w-4xl mx-auto px-4 py-12">
        
        <!-- Breadcrumb -->
        <a href="wisdom.php" class="inline-flex items-center text-gray-500 hover:text-green-700 mb-6 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            กลับไปหน้าบทความรวม
        </a>

        <article class="bg-white rounded-3xl shadow-lg border border-gray-100 overflow-hidden">
            <!-- Cover Image -->
            <?php if($article['image_path']): ?>
                <div class="article-cover h-64 md:h-96 w-full">
                    <img src="uploads/<?php echo $article['image_path']; ?>" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 p-8 text-white">
                        <div class="flex flex-wrap gap-2 mb-3">
                            <?php foreach($tags as $tag): ?>
                                <span class="bg-green-600/80 backdrop-blur text-white text-xs font-bold px-2 py-1 rounded-md">
                                    #<?php echo trim($tag); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-bold leading-tight shadow-black drop-shadow-md"><?php echo $article['title']; ?></h1>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-8 border-b border-gray-100">
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 leading-tight"><?php echo $article['title']; ?></h1>
                </div>
            <?php endif; ?>

            <!-- Meta & Content -->
            <div class="p-8 md:p-12">
                <div class="flex flex-col sm:flex-row sm:items-center text-gray-500 text-sm mb-8 pb-8 border-b border-gray-100 gap-3 sm:gap-6">
                    <span class="flex items-center mr-6"><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> <?php echo date('d/m/Y', strtotime($article['created_at'])); ?></span>
                    <span class="flex items-center"><svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg> เขียนโดย: <?php echo $article['author']; ?></span>
                </div>

                <div class="prose prose-lg prose-green max-w-none text-gray-600 leading-loose">
                    <?php echo nl2br($article['content']); ?>
                </div>
            </div>
        </article>

    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
    <?php include 'chat_widget.php'; ?>
</body>
</html>