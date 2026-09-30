<?php
session_start();
require_once 'config/db.php';

// รับค่าหมวดหมู่ที่เลือก (ถ้ามี)
$selected_cat = isset($_GET['cat']) ? $_GET['cat'] : null;
$selected_group = isset($_GET['group']) ? $_GET['group'] : '';
$herbs = [];
$db_categories = [];
$symptom_groups = [
    'pain' => ['name' => 'กลุ่มแก้ปวดเมื่อย', 'keywords' => ['ปวดเมื่อย', 'ปวดกล้ามเนื้อ', 'ปวดข้อ', 'ปวดเอว', 'แก้ปวด', 'เมื่อยล้า'], 'icon' => '💪', 'color' => 'bg-orange-100 text-orange-700'],
    'heart' => ['name' => 'กลุ่มบำรุงหัวใจ', 'keywords' => ['หัวใจ'], 'icon' => '❤️', 'color' => 'bg-red-100 text-red-700'],
    'skin' => ['name' => 'กลุ่มรักษาโรคผิวหนัง', 'keywords' => ['ผิวหนัง', 'กลาก', 'เกลื้อน', 'ผื่น', 'คัน'], 'icon' => '🧴', 'color' => 'bg-pink-100 text-pink-700'],
    'fever' => ['name' => 'กลุ่มแก้ไข้/ตัวร้อน', 'keywords' => ['ไข้', 'ตัวร้อน'], 'icon' => '🌡️', 'color' => 'bg-blue-100 text-blue-700'],
    'digestive' => ['name' => 'กลุ่มระบบทางเดินอาหาร', 'keywords' => ['ท้องเสีย', 'ท้องผูก', 'ท้องอืด', 'ปวดท้อง', 'ขับลม', 'อาหารไม่ย่อย', 'ทางเดินอาหาร'], 'icon' => '🤢', 'color' => 'bg-yellow-100 text-yellow-700'],
    'tonic' => ['name' => 'กลุ่มบำรุงกำลัง', 'keywords' => ['บำรุงกำลัง', 'บำรุงร่างกาย', 'กำลังวังชา', 'อ่อนเพลีย'], 'icon' => '⚡', 'color' => 'bg-green-100 text-green-700'],
];

try {
    if (isset($symptom_groups[$selected_group])) {
        $keywords = $symptom_groups[$selected_group]['keywords'];
        $conditions = [];
        $params = [];
        foreach ($keywords as $keyword) {
            $conditions[] = '(properties LIKE ? OR additional_info LIKE ?)';
            $params[] = '%' . $keyword . '%';
            $params[] = '%' . $keyword . '%';
        }

        $sql = 'SELECT * FROM herbs WHERE (' . implode(' OR ', $conditions) . ') ORDER BY thai_name ASC';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $herbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selected_cat) {
        // 1. กรณีเลือกหมวดหมู่: ดึงรายการสมุนไพรในหมวดนั้น
        $sql = "SELECT * FROM herbs WHERE category = :cat ORDER BY thai_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['cat' => $selected_cat]);
        $herbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 2. กรณีหน้ารวม: 
        // 2.1 ดึงหมวดหมู่จากฐานข้อมูล (ที่มีข้อมูลอยู่จริง)
        $sql = "SELECT category, COUNT(*) as count, 
                (SELECT image_path FROM herbs h2 WHERE h2.category = h1.category AND image_path != '' ORDER BY RAND() LIMIT 1) as cover_image 
                FROM herbs h1 
                WHERE category IS NOT NULL AND category != '' 
                GROUP BY category 
                ORDER BY count DESC";
        $stmt = $conn->query($sql);
        $db_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หมวดหมู่สรรพคุณ - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime(__DIR__ . '/css/style.css'); ?>">
    <link rel="stylesheet" href="css/categories.css?v=<?php echo filemtime(__DIR__ . '/css/categories.css'); ?>">
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
                <a href="categories.php" class="text-green-700 font-bold transition">หมวดหมู่สรรพคุณ</a>
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
            <h1 class="text-3xl md:text-5xl font-bold mb-4">หมวดหมู่และสรรพคุณสมุนไพร</h1>
            <p class="text-green-100 text-lg">ค้นหาสมุนไพรตามกลุ่มอาการรักษา หรือประเภทของพืช</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <?php if ($selected_cat || isset($symptom_groups[$selected_group])): ?>
            <!-- แสดงรายการสมุนไพรในกลุ่มสรรพคุณหรือหมวดหมู่ที่เลือก -->
            <div class="mb-8">
                <a href="categories.php" class="text-green-600 hover:underline flex items-center mb-4">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    กลับไปหน้าหมวดหมู่รวม
                </a>
                <h2 class="text-3xl font-bold text-gray-800 border-l-4 border-green-600 pl-4">
                    <?php echo isset($symptom_groups[$selected_group])
                        ? htmlspecialchars($symptom_groups[$selected_group]['name'])
                        : 'หมวดหมู่: ' . htmlspecialchars($selected_cat); ?>
                </h2>
                <p class="text-gray-500 mt-2 pl-5">
                    พบ <?php echo count($herbs); ?> รายการ<?php if (isset($symptom_groups[$selected_group])): ?> จากคำที่ระบุในสรรพคุณและข้อมูลเพิ่มเติม<?php endif; ?>
                </p>
            </div>

            <?php if (count($herbs) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($herbs as $row): ?>
                    <div class="bg-white rounded-3xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden group border border-gray-100 flex flex-col h-full">
                        <div class="relative h-56 overflow-hidden">
                            <?php if($row['image_path']): ?>
                                <img src="uploads/<?php echo $row['image_path']; ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">ไม่มีรูปภาพ</div>
                            <?php endif; ?>
                        </div>
                        <div class="p-6 flex-1 flex flex-col">
                            <h3 class="text-xl font-bold text-gray-800 mb-1 group-hover:text-green-700 transition"><?php echo $row['thai_name']; ?></h3>
                            <p class="text-gray-600 text-sm line-clamp-2 mb-4 flex-1"><?php echo $row['properties']; ?></p>
                            <a href="detail.php?id=<?php echo $row['id']; ?>" class="mt-auto block w-full text-center bg-green-50 text-green-700 font-bold py-2 rounded-xl hover:bg-green-600 hover:text-white transition">อ่านเพิ่มเติม</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p class="rounded-xl bg-white p-8 text-center text-gray-500">ยังไม่พบสมุนไพรที่มีข้อมูลสรรพคุณตรงกับกลุ่มนี้</p>
            <?php endif; ?>

        <?php else: ?>
            <!-- หน้าแสดงหมวดหมู่รวม -->
            
            <!-- 1. ค้นหาตามอาการ (Symptom Tags) -->
            <div class="mb-16">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <span class="bg-yellow-400 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">💊</span>
                    ค้นหาตามกลุ่มอาการรักษา
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <?php foreach($symptom_groups as $group_id => $group): ?>
                        <a href="categories.php?group=<?php echo urlencode($group_id); ?>"
                           class="<?php echo $group['color']; ?> p-4 rounded-2xl text-center hover:shadow-md hover:scale-105 transition duration-300 flex flex-col items-center justify-center h-32 border border-white/50 shadow-sm">
                            <span class="symptom-icon"><?php echo $group['icon']; ?></span>
                            <span class="font-bold text-sm"><?php echo $group['name']; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 2. หมวดหมู่จากฐานข้อมูล (Database Categories) -->
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                    <span class="bg-green-600 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">📂</span>
                    หมวดหมู่พืชสมุนไพร
                </h2>
                
                <?php if(count($db_categories) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                        <?php foreach($db_categories as $cat): ?>
                            <a href="categories.php?cat=<?php echo urlencode($cat['category']); ?>" class="category-card group relative rounded-2xl overflow-hidden h-48">
                                <!-- Background Image -->
                                <?php if($cat['cover_image']): ?>
                                    <img src="uploads/<?php echo $cat['cover_image']; ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent"></div>
                                <?php else: ?>
                                    <div class="absolute inset-0 bg-gradient-to-br from-green-600 to-teal-800 group-hover:scale-110 transition-transform duration-700"></div>
                                <?php endif; ?>
                                
                                <!-- Content -->
                                <div class="absolute bottom-0 left-0 p-5 w-full">
                                    <h3 class="text-xl font-bold text-white mb-1"><?php echo $cat['category']; ?></h3>
                                    <span class="inline-block bg-white/20 backdrop-blur-sm text-white text-xs px-2 py-1 rounded-md border border-white/30">
                                        <?php echo $cat['count']; ?> รายการ
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400 italic bg-gray-100 p-8 rounded-xl text-center">ยังไม่มีการจัดหมวดหมู่ในระบบ</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>
    <?php include 'chat_widget.php'; ?>
</body>
</html>