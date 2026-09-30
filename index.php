<?php
session_start();
// 1. เชื่อมต่อฐานข้อมูล
require_once 'config/db.php';

// Check unread notifications for navbar
$unread_count = 0;
if (isset($_SESSION['user_login'])) {
    try {
        $stmt_c = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
        $stmt_c->execute([$_SESSION['user_id']]);
        $unread_count = $stmt_c->fetchColumn();
    } catch (PDOException $e) {}
}

// 2. ตรวจสอบการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . str_replace(' ', '%', $search) . '%';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 9; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit;

try {
    // 0. ดึงข้อมูลสมุนไพรแนะนำก่อน (เพื่อนำ ID ไปตัดออกจากรายการหลัก)
    $featured_herbs = [];
    
    if (empty($search)) {
        $stmt_feat = $conn->query("SELECT * FROM herbs ORDER BY RAND() LIMIT 3");
        $featured_herbs = $stmt_feat->fetchAll(PDO::FETCH_ASSOC);
    }

    // 1. นับจำนวนข้อมูลทั้งหมด (ตัดตัวที่แนะนำออก)
    $sql_count = "SELECT COUNT(*) FROM herbs 
            WHERE (thai_name LIKE :search_count_1
            OR local_name LIKE :search_count_2
            OR other_names LIKE :search_count_3
            OR properties LIKE :search_count_4
            OR additional_info LIKE :search_count_5)";

    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute([
        'search_count_1' => $search_param,
        'search_count_2' => $search_param,
        'search_count_3' => $search_param,
        'search_count_4' => $search_param,
        'search_count_5' => $search_param
    ]);
    $total_items = $stmt_count->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // 2. ดึงข้อมูลตามหน้า (Pagination) - ตัดตัวแนะนำออก
    $sql = "SELECT * FROM herbs 
            WHERE (thai_name LIKE :search_1
            OR local_name LIKE :search_2
            OR other_names LIKE :search_3
            OR properties LIKE :search_4
            OR additional_info LIKE :search_5)";
            
    $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':search_1', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':search_2', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':search_3', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':search_4', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':search_5', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $herbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/index.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/effects.css?v=<?php echo time(); ?>">
    <style>
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .fade-in-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .search-button-bounce {
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), background-color 0.2s ease;
        }
        .search-button-bounce:hover {
            transform: scale(1.05) translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-700">

    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 h-16 flex justify-between items-center relative">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="text-2xl text-green-800 font-bold tracking-tight">🌿 LoeiHerb</span>
            </a>

            <!-- checkbox hack for hamburger menu -->
            <input id="nav-toggle" type="checkbox" />
            <label for="nav-toggle" class="nav-toggle-label md:hidden">
                <!-- three bars icon -->
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
                <a href="contact.php" class="text-gray-600 hover:text-green-700 font-medium transition">ติดต่อเรา</a>
                
                <?php if(isset($_SESSION['user_login'])): ?>
                    <a href="profile.php" title="จัดการบัญชีผู้ใช้" class="text-green-800 font-bold bg-green-100 hover:bg-green-200 px-3 py-1 rounded-full text-sm flex items-center shadow-sm transition relative">
                        👤 <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <?php if($unread_count > 0): ?><span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full border border-white"><?php echo $unread_count; ?></span><?php endif; ?>
                    </a>
                    <a href="logout.php" class="text-sm text-red-600 hover:bg-red-50 px-4 py-2 rounded-full font-medium transition">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="login.php" class="text-sm text-green-700 hover:text-green-900 font-medium transition">เข้าสู่ระบบ</a>
                    <a href="register.php" class="text-sm bg-green-600 text-white hover:bg-green-700 px-4 py-2 rounded-full font-medium transition shadow-sm">สมัครสมาชิก</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header class="hero-section py-24 md:py-32 px-4 text-center">
        <div class="hero-bg-image"></div>
        <div class="leaf-container"></div>
        <div class="relative z-10 max-w-3xl mx-auto">
            <h1 class="text-3xl md:text-6xl font-bold mb-6 leading-tight drop-shadow-md">สมุนไพรพื้นบ้าน<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-green-300 to-yellow-200">จังหวัดเลย</span></h1>
            <p class="text-lg md:text-xl text-gray-100 mb-10 font-light drop-shadow">แหล่งรวบรวมภูมิปัญญาท้องถิ่นและการสำรวจจากพื้นที่จริง เพื่อการอนุรักษ์และศึกษา</p>
        
            <form action="index.php" method="GET" class="search-box max-w-xl mx-auto flex rounded-full overflow-hidden p-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="ค้นหาชื่อสมุนไพร หรือ สรรพคุณ..." 
                       class="w-full px-6 py-4 rounded-l-full text-gray-800 focus:outline-none placeholder-gray-500 bg-white">
                <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-5 md:px-8 py-4 font-bold rounded-r-full search-button-bounce flex items-center shrink-0">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    ค้นหา
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <!-- ส่วนแสดงสมุนไพรแนะนำ (แสดงเฉพาะตอนไม่ได้ค้นหา) -->
        <?php if(empty($search) && count($featured_herbs) > 0): ?>
            <div class="mb-16 border-b border-gray-200 pb-12">
                <div class="text-center mb-10">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">✨ สมุนไพรน่ารู้ประจำวัน</h2>
                    <p class="text-gray-500">เรื่องราวสมุนไพรที่น่าสนใจที่เราคัดสรรมาฝาก</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <?php foreach($featured_herbs as $feat): ?>
                        <a href="detail.php?id=<?php echo $feat['id']; ?>" class="group block bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-green-100 relative transform hover:-translate-y-1 fade-in-up">
                            <div class="absolute top-0 right-0 bg-yellow-400 text-yellow-900 text-xs font-bold px-3 py-1 rounded-bl-lg z-10 shadow-sm">
                                แนะนำ
                            </div>
                            <div class="h-48 overflow-hidden">
                                <?php if($feat['image_path']): ?>
                                    <img src="uploads/<?php echo $feat['image_path']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                <?php else: ?>
                                    <div class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400">ไม่มีรูปภาพ</div>
                                <?php endif; ?>
                            </div>
                            <div class="p-5">
                                <h3 class="text-lg font-bold text-gray-800 mb-1 group-hover:text-green-700 transition"><?php echo $feat['thai_name']; ?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?php echo $feat['properties']; ?></p>
                                <div class="flex items-center text-green-600 text-sm font-medium">
                                    อ่านเพิ่มเติม 
                                    <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="mb-8 flex justify-between items-end">
            <h2 class="text-2xl font-bold text-gray-800 border-l-4 border-green-600 pl-3">
                <?php echo $search ? "ผลการค้นหา: '$search'" : "รายการสมุนไพรทั้งหมด"; ?>
            </h2>
            <span class="text-gray-500">พบ <?php echo count($herbs); ?> รายการ (หน้า <?php echo $page; ?>/<?php echo $total_pages > 0 ? $total_pages : 1; ?>)</span>
        </div>

        <?php if(count($herbs) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($herbs as $row): ?>
                    <div class="bg-white rounded-3xl shadow-sm hover:shadow-2xl transition-all duration-300 overflow-hidden group border border-gray-100 flex flex-col h-full fade-in-up">
                        <div class="relative h-64 overflow-hidden">
                            <?php if($row['image_path']): ?>
                                <img src="uploads/<?php echo $row['image_path']; ?>" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">ไม่มีรูปภาพ</div>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4  flex flex-col items-end space-y-1">
                                <span class="bg-white/90 backdrop-blur text-green-800 text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                    📍 อ.<?php echo $row['location_found']; ?>
                                </span>



                                <?php if(!empty($row['category'])): ?>
                                <span class="bg-green-600/90 backdrop-blur text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                    🏷️ <?php echo $row['category']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-xl font-bold text-gray-800 mb-1 group-hover:text-green-700 transition"><?php echo $row['thai_name']; ?></h3>
                            <p class="text-gray-600 text-sm line-clamp-3 mb-6 flex-1">
                                <?php echo $row['properties']; ?>
                            </p>
                            <div class="mt-auto">
                                <a href="detail.php?id=<?php echo $row['id']; ?>" 
                                   class="block w-full text-center bg-green-50 text-green-700 font-bold py-2.5 rounded-xl hover:bg-green-600 hover:text-white transition duration-300" >
                                   อ่านข้อมูลเพิ่มเติม
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-12 flex flex-wrap justify-center items-center gap-2">
                <!-- ปุ่มย้อนกลับ -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-green-50 hover:text-green-700 transition">
                        &laquo; ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- เลขหน้า -->
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 border rounded-lg transition <?php echo $i == $page ? 'bg-green-600 text-white border-green-600 shadow-md' : 'bg-white border-gray-300 text-gray-600 hover:bg-green-50 hover:text-green-700'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <!-- ปุ่มถัดไป -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-600 hover:bg-green-50 hover:text-green-700 transition">
                        ถัดไป &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-20 bg-white rounded-3xl shadow-inner">
                <p class="text-gray-400 text-lg italic">เสียใจด้วยค่ะ ไม่พบข้อมูลสมุนไพรที่คุณค้นหา</p>
                <a href="index.php" class="text-green-600 underline mt-2 inline-block">ดูสมุนไพรทั้งหมด</a>
            </div>
        <?php endif; ?>
    </main>
    <footer class="bg-gray-800 text-gray-400 py-10 text-center">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย - พัฒนาโดยคุณ Naruwan</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target); // ให้แอนิเมชันแสดงแค่ครั้งเดียว
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: "0px 0px -50px 0px"
            });

            // กำหนด Delay ให้การ์ดแต่ละใบแสดงเหลื่อมกันเล็กน้อยเพื่อความสวยงาม
            document.querySelectorAll('.fade-in-up').forEach((el, index) => {
                el.style.transitionDelay = `${(index % 3) * 150}ms`; 
                observer.observe(el);
            });
        });
    </script>
    <script src="js/effects.js"></script>
    <?php include 'chat_widget.php'; ?>
</body>
</html>