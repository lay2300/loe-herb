<?php
session_start();
require_once 'config/db.php';

// ดึงข้อมูลสมุนไพรที่มีระบุตำแหน่ง
try {
    $sql = "SELECT id, thai_name, location_found, image_path, properties, coordinates FROM herbs WHERE location_found IS NOT NULL AND location_found != ''";
    $stmt = $conn->query($sql);
    $herbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// กำหนดพิกัดกลางของอำเภอต่างๆ ในจังหวัดเลย (Latitude, Longitude)
$district_coords = [
    'เมืองเลย' => ['lat' => 17.4925, 'lng' => 101.7223],
    'นาด้วง' => ['lat' => 17.4833, 'lng' => 101.9833],
    'เชียงคาน' => ['lat' => 17.8953, 'lng' => 101.6633],
    'ปากชม' => ['lat' => 18.0283, 'lng' => 101.8833],
    'ด่านซ้าย' => ['lat' => 17.2783, 'lng' => 101.1473],
    'นาแห้ว' => ['lat' => 17.4883, 'lng' => 101.0723],
    'ภูเรือ' => ['lat' => 17.4533, 'lng' => 101.3623],
    'ท่าลี่' => ['lat' => 17.6233, 'lng' => 101.4173],
    'วังสะพุง' => ['lat' => 17.3033, 'lng' => 101.7683],
    'ภูกระดึง' => ['lat' => 16.8833, 'lng' => 101.8833],
    'ภูหลวง' => ['lat' => 17.1500, 'lng' => 101.6833],
    'ผาขาว' => ['lat' => 17.0333, 'lng' => 102.0167],
    'เอราวัณ' => ['lat' => 17.2833, 'lng' => 102.0333],
    'หนองหิน' => ['lat' => 17.1167, 'lng' => 101.8667]
];

// เตรียมข้อมูลสำหรับส่งให้ JavaScript
$map_markers = [];
foreach ($herbs as $herb) {
    $lat = null;
    $lng = null;
    $is_precise = false;

    // 1. ตรวจสอบพิกัดที่ระบุโดยตรงก่อน
    if (!empty($herb['coordinates'])) {
        $parts = explode(',', $herb['coordinates']);
        if (count($parts) == 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
            $lat = (float)trim($parts[0]);
            $lng = (float)trim($parts[1]);
            $is_precise = true;
        }
    }

    // 2. ถ้าไม่มีพิกัดที่ระบุ ให้ใช้พิกัดกลางอำเภอเป็นค่าสำรอง
    if (!$is_precise) {
        $district = trim($herb['location_found']);
        $district_clean = str_replace(['อำเภอ', 'อ.'], '', $district);
        $district_clean = trim($district_clean);

        if (isset($district_coords[$district_clean])) {
            // สุ่มพิกัดเล็กน้อยเพื่อไม่ให้หมุดซ้อนกัน
            $lat = $district_coords[$district_clean]['lat'] + (mt_rand(-200, 200) / 100000);
            $lng = $district_coords[$district_clean]['lng'] + (mt_rand(-200, 200) / 100000);
        }
    }
    
    // เพิ่มข้อมูลลงใน Marker เฉพาะเมื่อมีพิกัด
    if ($lat !== null && $lng !== null) {
        $district_clean = str_replace(['อำเภอ', 'อ.'], '', trim($herb['location_found']));
        $district_clean = trim($district_clean);
        
        $map_markers[] = [
            'id' => $herb['id'],
            'title' => $herb['thai_name'],
            'lat' => $lat,
            'lng' => $lng,
            'district' => $district_clean,
            'image' => $herb['image_path'] ? 'uploads/' . $herb['image_path'] : '',
            'desc' => mb_substr($herb['properties'], 0, 80) . '...',
            'is_precise' => $is_precise // ส่งสถานะความแม่นยำไปด้วย
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผนที่สมุนไพร - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/map.css">
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
                <a href="map.php" class="text-green-700 font-bold transition">แผนที่สมุนไพร</a>
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

    <header class="bg-green-800 text-white py-12 text-center relative overflow-hidden">
        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')]"></div>
        <div class="relative z-10 max-w-4xl mx-auto px-4">
            <h1 class="text-3xl md:text-5xl font-bold mb-4">📍 แผนที่แหล่งสมุนไพร</h1>
            <p class="text-green-100 text-lg">สำรวจตำแหน่งที่พบสมุนไพรในอำเภอต่างๆ ของจังหวัดเลย</p>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-12">
        
        <div class="bg-white p-4 rounded-3xl shadow-lg border border-gray-100">
            <!-- Map Container -->
            <div id="map" class="shadow-inner bg-gray-100 relative z-0"></div>
        </div>

        <div class="mt-8 text-center text-gray-500 text-sm">
            <p>* ตำแหน่งในแผนที่อาจเป็นพิกัดที่แน่ชัด หรือเป็นเพียงจุดกึ่งกลางของอำเภอที่สำรวจพบ</p>
        </div>

    </main>

    <footer class="bg-gray-800 text-gray-400 py-10 text-center mt-12">
        <p>© 2026 ระบบฐานข้อมูลสมุนไพรจังหวัดเลย</p>
    </footer>

    <!-- Leaflet Maps Script (แผนที่ฟรี ไม่ต้องใช้ API Key) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // พิกัดกลางจังหวัดเลย
            const map = L.map('map').setView([17.486, 101.722], 9);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const markers = <?php echo json_encode($map_markers); ?>;
            
            markers.forEach(item => {
                const marker = L.marker([item.lat, item.lng], {title: item.title}).addTo(map);

                // เพิ่มสัญลักษณ์บอกความแม่นยำของตำแหน่ง
                const locationText = item.is_precise 
                    ? `<span class="text-green-600 font-bold">📍 พิกัดแน่ชัด</span> (อ.${item.district})`
                    : `📍 อ.${item.district} (โดยประมาณ)`;

                const contentString = `
                    <div class="p-2 w-48 font-sans">
                        ${item.image ? `<img src="${item.image}" class="w-full h-32 object-cover rounded-lg mb-2">` : ''}
                        <h3 class="font-bold text-lg text-green-800 mb-1">${item.title}</h3>
                        <p class="text-xs text-gray-500 mb-2">${locationText}</p>
                        <a href="detail.php?id=${item.id}" class="block text-center bg-green-600 text-white text-xs font-bold py-1.5 rounded hover:bg-green-700 transition" style="color: white !important; text-decoration: none;">ดูรายละเอียด</a>
                    </div>
                `;

                marker.bindPopup(contentString);
            });
            setTimeout(() => { map.invalidateSize(); }, 500);
        });
    </script>
    <?php include 'chat_widget.php'; ?>
</body>
</html>