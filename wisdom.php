<?php
session_start();
require_once 'config/db.php';

// รับค่าค้นหา
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    if ($search) {
        $sql = "SELECT * FROM articles WHERE title LIKE :search_title OR content LIKE :search_content OR tags LIKE :search_tags ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->execute([
            'search_title' => $search_param,
            'search_content' => $search_param,
            'search_tags' => $search_param
        ]);
    } else {
        $sql = "SELECT * FROM articles ORDER BY created_at DESC";
        $stmt = $conn->query($sql);
    }
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ตรวจสอบกรณีตารางหาย ให้แจ้งเตือนพร้อมลิงก์ติดตั้ง
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        die("<div style='text-align:center; padding:50px; font-family:sans-serif;'>⚠️ ไม่พบตารางบทความในฐานข้อมูล <br><br> กรุณารันไฟล์ <a href='install_articles.php' style='color:green; font-weight:bold;'>install_articles.php</a> เพื่อติดตั้งตารางก่อนครับ</div>");
    }
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ภูมิปัญญาท้องถิ่น - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/wisdom.css">
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

    <header class="bg-green-800 text-white py-16 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')]"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4">
            <h1 class="text-3xl md:text-5xl font-bold mb-4">📜 ภูมิปัญญาท้องถิ่น</h1>
            <p class="text-green-100 text-lg mb-8">รวบรวมเกร็ดความรู้ ตำรับยา และวิถีชีวิตชาวเมืองเลยที่ผูกพันกับธรรมชาติ</p>
            
            <!-- Search Form -->
            <form action="wisdom.php" method="GET" class="wisdom-search-box max-w-xl mx-auto flex shadow-lg rounded-full overflow-hidden p-1 bg-white/20 backdrop-blur-sm border border-white/30">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="ค้นหาบทความ..." 
                       class="w-full px-6 py-3 rounded-l-full text-gray-800 focus:outline-none placeholder-gray-200 bg-transparent text-white placeholder:text-green-100">
                <button type="submit" class="bg-white text-green-800 px-6 py-3 font-bold rounded-r-full hover:bg-green-50 transition flex items-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <?php if($search): ?>
            <div class="mb-8 flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-800">ผลการค้นหา: "<?php echo htmlspecialchars($search); ?>"</h2>
                <a href="wisdom.php" class="text-green-600 hover:underline text-sm">ดูทั้งหมด</a>
            </div>
        <?php endif; ?>

        <?php if(count($articles) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <?php foreach($articles as $index => $article): ?>
                <?php 
                    // แปลง Tags string เป็น array
                    $tags = array_filter(explode(',', $article['tags']));
                    // ตัดคำเนื้อหา
                    $excerpt = mb_substr($article['content'], 0, 150) . '...';
                ?>
                <article class="article-card bg-white rounded-3xl overflow-hidden flex flex-col md:flex-row h-full group">
                    <!-- Image -->
                    <div class="md:w-2/5 relative overflow-hidden h-64 md:h-auto">
                        <?php if($article['image_path']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                        <?php else: ?>
                            <div class="absolute inset-0 w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">ไม่มีรูปภาพ</div>
                        <?php endif; ?>
                        <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                            <?php foreach($tags as $tag): ?>
                                <span class="bg-white/90 backdrop-blur text-green-800 text-xs font-bold px-2 py-1 rounded-md shadow-sm">
                                    #<?php echo htmlspecialchars(trim($tag), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-8 md:w-3/5 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center text-gray-400 text-xs mb-3 space-x-2">
                                <span>🗓️ <?php echo date('d/m/Y', strtotime($article['created_at'])); ?></span>
                                <span>•</span>
                                <span>✍️ <?php echo htmlspecialchars($article['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-3 group-hover:text-green-700 transition leading-tight">
                                <?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </h2>
                            <p class="text-gray-600 mb-4 line-clamp-3">
                                <?php echo htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                            <!-- ลิงก์ไปหน้าอ่านบทความ (ถ้ามี) หรือใช้ Modal -->
                            <a href="wisdom_detail.php?id=<?php echo $article['id']; ?>" class="text-green-600 font-bold hover:text-green-800 transition flex items-center group/btn">
                                อ่านบทความ
                                <svg class="w-4 h-4 ml-1 group-hover/btn:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                            </a>
                            <div class="flex space-x-2 text-gray-400">
                                <button class="hover:text-red-500 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg></button>
                                <button class="hover:text-blue-500 transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg></button>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-3xl border border-gray-100 shadow-sm">
                <p class="text-gray-400 text-lg">ไม่พบบทความที่คุณค้นหา</p>
            </div>
        <?php endif; ?>

    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
    <?php include 'chat_widget.php'; ?>
</body>
</html>