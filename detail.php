<?php
session_start();
// 1. เชื่อมต่อฐานข้อมูล
require_once 'config/db.php';

// 2. รับค่า ID จาก URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        $sql = "SELECT * FROM herbs WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id' => $id]);
        $herb = $stmt->fetch(PDO::FETCH_ASSOC);

        // ถ้าไม่พบข้อมูลให้กลับไปหน้าแรก
        if (!$herb) {
            header("Location: index.php");
            exit();
        }

        // ดึงรูปภาพ Gallery (ใส่ Try-Catch แยก เพื่อป้องกัน Error กรณีไม่มีตาราง)
        $gallery_images = [];
        try {
            $stmt_gallery = $conn->prepare("SELECT * FROM herb_images WHERE herb_id = :id");
            $stmt_gallery->execute(['id' => $id]);
            $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* ข้ามไปถ้าไม่มีตาราง */ }
        
        // --- เตรียมพิกัดแผนที่ (Mini Map) ---
        $map_lat = 17.486;
        $map_lng = 101.722;
        $has_specific_coords = false;

        $coords = isset($herb['coordinates']) ? $herb['coordinates'] : '';
        if (!empty($coords)) {
            $parts = explode(',', $coords);
            if (count($parts) == 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                $map_lat = (float)trim($parts[0]);
                $map_lng = (float)trim($parts[1]);
                $has_specific_coords = true;
            }
        }

        if (!$has_specific_coords && !empty($herb['location_found'])) {
            $district_coords = [
                'เมืองเลย' => ['lat' => 17.4925, 'lng' => 101.7223], 'นาด้วง' => ['lat' => 17.4833, 'lng' => 101.9833],
                'เชียงคาน' => ['lat' => 17.8953, 'lng' => 101.6633], 'ปากชม' => ['lat' => 18.0283, 'lng' => 101.8833],
                'ด่านซ้าย' => ['lat' => 17.2783, 'lng' => 101.1473], 'นาแห้ว' => ['lat' => 17.4883, 'lng' => 101.0723],
                'ภูเรือ' => ['lat' => 17.4533, 'lng' => 101.3623], 'ท่าลี่' => ['lat' => 17.6233, 'lng' => 101.4173],
                'วังสะพุง' => ['lat' => 17.3033, 'lng' => 101.7683], 'ภูกระดึง' => ['lat' => 16.8833, 'lng' => 101.8833],
                'ภูหลวง' => ['lat' => 17.1500, 'lng' => 101.6833], 'ผาขาว' => ['lat' => 17.0333, 'lng' => 102.0167],
                'เอราวัณ' => ['lat' => 17.2833, 'lng' => 102.0333], 'หนองหิน' => ['lat' => 17.1167, 'lng' => 101.8667]
            ];
            $district_clean = str_replace(['อำเภอ', 'อ.'], '', trim($herb['location_found']));
            if (isset($district_coords[$district_clean])) {
                $map_lat = $district_coords[$district_clean]['lat'];
                $map_lng = $district_coords[$district_clean]['lng'];
            }
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($herb['thai_name'], ENT_QUOTES, 'UTF-8'); ?> - ข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/detail.css?v=<?php echo time(); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="bg-gray-50">

    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 h-16 flex justify-between items-center relative">
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

    <main class="max-w-6xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            
            <!-- Left Column: Image -->
            <div class="lg:sticky lg:top-24 h-fit">
                <div class="herb-image-container">
                    <?php if($herb['image_path']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($herb['image_path'], ENT_QUOTES, 'UTF-8'); ?>" class="herb-image w-full h-auto object-cover cursor-pointer" onclick="openLightbox(this.src)">
                    <?php else: ?>
                        <div class="w-full h-96 bg-gray-200 flex items-center justify-center text-gray-400">ไม่มีรูปภาพประกอบ</div>
                    <?php endif; ?>
                </div>
                <div class="mt-6 flex justify-center">
                    <span class="bg-green-100 text-green-800 text-sm font-semibold px-4 py-1.5 rounded-full">
                        บันทึกเมื่อ: <?php echo date('d/m/Y', strtotime($herb['created_at'])); ?>
                    </span>
                </div>
            </div>

            <!-- Right Column: Content -->
            <div class="bg-white p-6 md:p-10 rounded-3xl shadow-lg border border-gray-100 h-fit">
                <div class="mb-8 border-b border-gray-100 pb-6">
                    <h1 class="text-3xl md:text-5xl font-bold text-gray-800 mb-3 text-green-900"><?php echo htmlspecialchars($herb['thai_name'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <?php if(!empty($herb['other_names'])): ?>
                        <p class="text-md text-gray-400 mt-1">ชื่ออื่น ๆ: <?php echo htmlspecialchars($herb['other_names'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="info-card bg-blue-50 p-5 rounded-2xl border border-blue-100">
                        <span class="text-xs text-gray-400 uppercase font-bold tracking-wider block mb-1">ชื่อวิทยาศาสตร์</span>
                        <span class="text-blue-900 italic font-medium text-lg"><?php echo htmlspecialchars($herb['sci_name'] ?: 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="info-card bg-purple-50 p-5 rounded-2xl border border-purple-100">
                        <span class="text-xs text-gray-400 uppercase font-bold tracking-wider block mb-1">ชื่อวงศ์</span>
                        <span class="text-purple-900 font-medium text-lg"><?php echo htmlspecialchars($herb['family_name'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="info-card bg-orange-50 p-5 rounded-2xl border border-orange-100">
                        <span class="text-xs text-gray-400 uppercase font-bold tracking-wider block mb-1">หมวดหมู่</span>
                        <span class="text-orange-900 font-medium text-lg"><?php echo htmlspecialchars($herb['category'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                
                <div class="mb-8">
                    <div class="bg-green-50 p-5 rounded-2xl border border-green-100">
                        <span class="text-xs text-green-600 uppercase font-bold tracking-wider block mb-1">พื้นที่ที่สำรวจพบ</span>
                        <span class="text-green-900 font-medium text-lg mb-3 block">
                            <?php 
                                $loc_parts = [];
                                if (!empty($herb['sub_district'])) $loc_parts[] = 'ต.' . htmlspecialchars($herb['sub_district']);
                                if (!empty($herb['location_found'])) $loc_parts[] = 'อ.' . htmlspecialchars($herb['location_found']);
                                $loc_parts[] = 'จ.เลย';
                                echo implode(' ', $loc_parts);
                            ?>
                        </span>
                        <div id="minimap" class="h-48 w-full rounded-xl border border-green-200 shadow-inner relative z-0"></div>
                        <?php if(!$has_specific_coords): ?>
                            <p class="text-[10px] text-green-700 mt-2">* ตำแหน่งบนแผนที่เป็นจุดกึ่งกลางของอำเภอ (ไม่ได้ระบุพิกัดที่แน่ชัด)</p>
                        <?php else: ?>
                            <p class="text-[10px] text-green-700 mt-2">📍 พิกัด: <?php echo htmlspecialchars($herb['coordinates']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-8">
                    <?php if(!empty($herb['general_characteristics'])): ?>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                            <span class="bg-yellow-400 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">📝</span>
                            ลักษณะทั่วไป
                        </h3>
                        <div class="text-gray-600 leading-relaxed pl-5">
                            <?php echo nl2br(htmlspecialchars($herb['general_characteristics'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($herb['parts_used'])): ?>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                            <span class="bg-blue-500 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm"> 🌿</span>
                            ส่วนที่ใช้เป็นยา
                        </h3>
                        <div class="text-gray-600 leading-relaxed pl-5">
                            <?php echo htmlspecialchars($herb['parts_used']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-4 gap-3">
                        <h3 class="text-2xl font-bold text-gray-800  flex items-center">
                            <span class="bg-green-600 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">✨</span>
                            สรรพคุณและวิธีใช้
                        </h3>
                        <button onclick="speakText()" id="speakBtn" class="flex items-center bg-green-100 text-green-700 px-3 py-1.5 rounded-lg hover:bg-green-200 transition text-sm font-bold">
                            <span class="mr-2">🔊</span> ฟังเสียงอ่าน
                        </button>
                    </div>
                    <div class="text-gray-600 leading-loose whitespace-pre-line text-lg bg-gray-50 p-6 rounded-2xl border border-gray-100">
                        <?php echo htmlspecialchars($herb['properties']); ?>
                    </div>
                    </div>

                    <?php if(!empty($herb['culinary_uses'])): ?>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                        <span class="bg-orange-500 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">🍲</span>
                            ด้านอาหาร
                        </h3>
                        <div class="text-gray-600 leading-relaxed pl-5">
                            <?php echo nl2br(htmlspecialchars($herb['culinary_uses'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($herb['additional_info'])): ?>
                    <div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2 flex items-center">
                         <span class="bg-purple-500 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">ℹ️</span>
                            ข้อมูลเพิ่มเติม
                        </h3>
                        <div class="text-gray-600 leading-relaxed pl-5">
                            <?php echo nl2br(htmlspecialchars($herb['additional_info'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Gallery Section -->
                <?php if(count($gallery_images) > 0): ?>
                <div class="mt-10 pt-8 border-t border-gray-100">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                        <span class="bg-pink-500 w-8 h-8 rounded-lg mr-3 flex items-center justify-center text-white shadow-sm">📸</span>
                        แกลเลอรี่รูปภาพ
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach($gallery_images as $img): ?>
                            <img src="uploads/<?php echo $img['image_path']; ?>" class="w-full h-48 object-cover rounded-xl shadow-sm hover:scale-105 transition duration-500 cursor-pointer border border-gray-100" onclick="openLightbox(this.src)">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-10 pt-6 border-t border-gray-100 flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center space-x-4 bg-green-50 p-3 rounded-xl border border-green-100">
                        <div id="qrcode" class="bg-white p-1 rounded-lg shadow-sm"></div>
                        <div>
                            <p class="text-sm font-bold text-green-800">📱 สแกน QR Code</p>
                            <p class="text-xs text-green-600">เพื่อเปิดอ่านบนมือถือ</p>
                        </div>
                    </div>

                    <button onclick="window.print()" class="flex items-center bg-gray-100 hover:bg-gray-200 text-gray-600 px-6 py-3 rounded-xl transition font-medium shadow-sm">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        พิมพ์ข้อมูลนี้
                    </button>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-10 text-center text-gray-400 text-sm">
        <p>ระบบฐานข้อมูลสมุนไพรจังหวัดเลย - พัฒนาเพื่อการศึกษา</p>
    </footer>

    <!-- Lightbox Container -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close">&times;</span>
        <img id="lightbox-img" src="" alt="Full Image">
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text:  window.location.href,
            width: 64,
            height: 64,
            colorDark : "#15803d",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        // Text-to-Speech Function
        function speakText() {
            const text =  `<?php echo str_replace(array("\r", "\n"), ' ', addslashes($herb['properties'])); ?>`;
            
            if ('speechSynthesis' in window) {
                // หยุดเสียงเก่าถ้ามี
                window.speechSynthesis.cancel();

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'th-TH'; // ตั้งค่าภาษาไทย
                utterance.rate = 1.0; // ความเร็วปกติ
                utterance.pitch = 1.0; // ระดับเสียงปกติ

                window.speechSynthesis.speak(utterance);
            } else {
                alert("ขออภัย เบราว์เซอร์ของคุณไม่รองรับการอ่านเสียง");
            }
        }

        // Lightbox Functions
        function openLightbox(src) {
            const lightbox = document.getElementById('lightbox');
            const img = document.getElementById('lightbox-img');
            img.src = src;
            lightbox.classList.add('active');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
        }
    </script>

    <!-- Leaflet Maps Script -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lat = <?php echo $map_lat; ?>;
            const lng = <?php echo $map_lng; ?>;
            const map = L.map('minimap').setView([lat, lng], <?php echo $has_specific_coords ? '13' : '10'; ?>);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map).bindPopup('<b><?php echo addslashes($herb['thai_name']); ?></b><br>อ.<?php echo addslashes($herb['location_found']); ?>').openPopup();
            setTimeout(() => { map.invalidateSize(); }, 500);
        });
    </script>
    <?php include 'chat_widget.php'; ?>
</body>
</html>