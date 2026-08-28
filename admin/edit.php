<?php
session_start();
// ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

require_once '../config/db.php';
require_once '../config/thai_geography.php'; // ดึงข้อมูลอำเภอ/ตำบล

// 1. ตรวจสอบว่ามี ID ส่งมาหรือไม่
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'];

// 2. ดึงข้อมูลเดิมมาแสดง
$stmt = $conn->prepare("SELECT * FROM herbs WHERE id = :id");
$stmt->execute(['id' => $id]);
$herb = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$herb) {
    die("ไม่พบข้อมูลสมุนไพรนี้ในระบบค่ะ");
}

// ดึงรูปภาพ Gallery
$gallery_images = [];
try {
    $stmt_gallery = $conn->prepare("SELECT * FROM herb_images WHERE herb_id = :id");
    $stmt_gallery->execute(['id' => $id]);
    $gallery_images = $stmt_gallery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ข้ามไป */ }

// 3. ตรวจสอบการกดปุ่มบันทึกแก้ไข
if (isset($_POST['submit'])) {
    $thai_name = $_POST['thai_name'];
    $local_name = $_POST['local_name'];
    $sub_district = $_POST['sub_district'];
    $sci_name = $_POST['sci_name'];
    $family_name = $_POST['family_name'];
    $category = $_POST['category'];
    $other_names = $_POST['other_names'];
    $general_characteristics = $_POST['general_characteristics'];
    $properties = $_POST['properties'];
    $parts_used = $_POST['parts_used'];
    $culinary_uses = $_POST['culinary_uses'];
    $additional_info = $_POST['additional_info'];
    $location_found = $_POST['location_found'];
    $coordinates = $_POST['coordinates'] ?? '';
    
    $image_path = $herb['image_path']; // ใช้รูปเดิมไปก่อน

    // ถ้ามีการอัปโหลดรูปใหม่
    if (!empty($_FILES['image']['name'])) {
        $image_name = $_FILES['image']['name'];
        $tmp_name = $_FILES['image']['tmp_name'];
        $upload_dir = "../uploads/";
        $new_image_name = date("YmdHis") . "_" . $image_name;

        if (move_uploaded_file($tmp_name, $upload_dir . $new_image_name)) {
            // ลบรูปเก่าทิ้ง (ถ้ามี)
            if ($herb['image_path'] && file_exists($upload_dir . $herb['image_path'])) {
                @unlink($upload_dir . $herb['image_path']);
            }
            $image_path = $new_image_name;
        }
    }

    try {
        $sql = "UPDATE herbs SET 
                thai_name = :thai_name, 
                local_name = :local_name, 
                sci_name = :sci_name, 
                family_name = :family_name,
                category = :category,
                other_names = :other_names,
                general_characteristics = :general_characteristics,
                properties = :properties, 
                parts_used = :parts_used,
                sub_district = :sub_district,
                culinary_uses = :culinary_uses,
                additional_info = :additional_info,
                location_found = :location_found, 
            coordinates = :coordinates,
                image_path = :image_path 
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'thai_name' => $thai_name,
            'local_name' => $local_name,
            'sci_name' => $sci_name,
            'family_name' => $family_name,
            'category' => $category,
            'other_names' => $other_names,
            'general_characteristics' => $general_characteristics,
            'properties' => $properties,
            'parts_used' => $parts_used,
            'sub_district' => $sub_district,
            'culinary_uses' => $culinary_uses,
            'additional_info' => $additional_info,
            'location_found' => $location_found,
            'coordinates' => $coordinates,
            'image_path' => $image_path,
            'id' => $id
        ]);

        // --- จัดการ Gallery (อัปโหลดเพิ่ม) ---
        if (!empty($_FILES['gallery']['name'][0])) {
            $upload_dir = "../uploads/";
            
            $stmt_current_images = $conn->prepare("SELECT COUNT(*) FROM herb_images WHERE herb_id = :id");
            $stmt_current_images->execute(['id' => $id]);
            $current_images_count = $stmt_current_images->fetchColumn();
            
            $allowed_files = max(0, 5 - $current_images_count);
            $total_files = count($_FILES['gallery']['name']);
            
            if ($allowed_files > 0) {
                $total_files = min($total_files, $allowed_files);
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['gallery']['error'][$i] == 0) {
                        $g_name = $_FILES['gallery']['name'][$i];
                        $g_tmp = $_FILES['gallery']['tmp_name'][$i];
                        $g_new = date("YmdHis") . "_" . uniqid() . "_" . $g_name;
                        if (move_uploaded_file($g_tmp, $upload_dir . $g_new)) {
                            $conn->prepare("INSERT INTO herb_images (herb_id, image_path) VALUES (?, ?)")->execute([$id, $g_new]);
                        }
                    }
                }
            }
        }

        header("Location: index.php");
        exit();

    } catch (PDOException $e) {
        // ตรวจสอบว่า Error เกิดจากคอลัมน์หายไปหรือไม่
        if (strpos($e->getMessage(), 'Column not found') !== false || strpos($e->getMessage(), "Unknown column") !== false) {
            echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>";
            echo "<strong class='font-bold'>เกิดข้อผิดพลาด!</strong>";
            echo "<span class='block sm:inline'> ฐานข้อมูลยังไม่อัปเดตครับ (ไม่พบคอลัมน์ใหม่ในตาราง)</span>";
            echo "<br><a href='update_schema.php' class='underline font-bold'>👉 คลิกที่นี่เพื่ออัปเดตฐานข้อมูล (update_schema.php)</a>";
            echo "</div>";
        } else {
            echo "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลสมุนไพร - LoeiHerb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="bg-gray-50 p-4 md:p-8">

    <div class="animate-slide-up max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-blue-800">✏️ แก้ไขข้อมูลสมุนไพร</h2>
            <a href="index.php" class="text-gray-500 hover:text-gray-700 text-sm">ยกเลิก</a>
        </div>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                <input type="text" name="thai_name" value="<?php echo htmlspecialchars($herb['thai_name']); ?>" required class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อท้องถิ่น</label>
                    <input type="text" name="local_name" value="<?php echo htmlspecialchars($herb['local_name']); ?>" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อวิทยาศาสตร์</label>
                    <input type="text" name="sci_name" value="<?php echo htmlspecialchars($herb['sci_name']); ?>" class="mt-1 w-full p-2.5 border rounded-lg italic focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อวงศ์ (Family Name)</label>
                    <input type="text" name="family_name" value="<?php echo htmlspecialchars($herb['family_name'] ?? ''); ?>" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">หมวดหมู่</label>
                    <select name="category" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500 bg-white">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php 
                        $cats = ['ไม้ต้น', 'ไม้พุ่ม', 'ไม้เลื้อย', 'ไม้ล้มลุก', 'ไม้รอเลื้อย', 'พืชน้ำ', 'อื่นๆ'];
                        foreach($cats as $c) {
                            $selected = ($herb['category'] ?? '') == $c ? 'selected' : '';
                            echo "<option value='$c' $selected>$c</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่ออื่น ๆ</label>
                    <input type="text" name="other_names" value="<?php echo htmlspecialchars($herb['other_names'] ?? ''); ?>" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ลักษณะทั่วไป</label>
                <textarea name="general_characteristics" rows="3" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500"><?php echo htmlspecialchars($herb['general_characteristics'] ?? ''); ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">สรรพคุณและวิธีใช้ <span class="text-red-500">*</span></label>
                <textarea name="properties" rows="4" required class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500"><?php echo htmlspecialchars($herb['properties']); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ส่วนที่ใช้เป็นยา</label>
                    <input type="text" name="parts_used" value="<?php echo htmlspecialchars($herb['parts_used'] ?? ''); ?>" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ด้านอาหาร</label>
                    <input type="text" name="culinary_uses" value="<?php echo htmlspecialchars($herb['culinary_uses'] ?? ''); ?>" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ข้อมูลเพิ่มเติม</label>
                <textarea name="additional_info" rows="3" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-blue-500"><?php echo htmlspecialchars($herb['additional_info'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-100">
                <div>
                    <label class="block text-sm font-medium text-gray-700">พิกัดแผนที่ (ละติจูด, ลองจิจูด)</label>
                    <div class="flex space-x-2 mt-1">
                        <input type="text" name="coordinates" id="coordinates_input" value="<?php echo htmlspecialchars($herb['coordinates'] ?? ''); ?>" placeholder="เช่น 17.4925, 101.7223" class="w-full p-2.5 border rounded-lg focus:ring-blue-500">
                        <button type="button" onclick="getCurrentLocation()" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 transition shadow-sm text-sm whitespace-nowrap border border-gray-300" title="ตำแหน่งปัจจุบัน">📍</button>
                    </div>
                </div>
                <div>
                    <div id="mapPicker" class="w-full h-24 mt-1 rounded-xl border border-gray-300 shadow-inner z-0 relative overflow-hidden bg-gray-100"></div>
                    <p class="text-xs text-gray-400 mt-1">คลิกบนแผนที่หรือลากหมุดเพื่อเลือกพิกัด</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">รูปภาพสมุนไพร (อัปโหลดใหม่เพื่อเปลี่ยน)</label>
                <?php if($herb['image_path']): ?>
                    <div class="mb-2">
                        <img src="../uploads/<?php echo $herb['image_path']; ?>" class="h-32 rounded-lg object-cover">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">รูปภาพเพิ่มเติม (Gallery)</label>
                
                <div id="current-gallery-images" data-count="<?php echo count($gallery_images); ?>"></div>
                
                <!-- แสดงรูปที่มีอยู่ -->
                <?php if(count($gallery_images) > 0): ?>
                    <div class="grid grid-cols-3 md:grid-cols-4 gap-4 mb-4">
                        <?php foreach($gallery_images as $img): ?>
                            <div class="relative group">
                                <img src="../uploads/<?php echo $img['image_path']; ?>" class="h-24 w-full object-cover rounded-lg shadow-sm">
                                <a href="delete_gallery.php?id=<?php echo $img['id']; ?>" onclick="return confirm('ลบรูปนี้?')" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition shadow-md hover:bg-red-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <input type="file" id="gallery-upload" name="gallery[]" accept="image/*" multiple class="mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                <p class="text-xs text-gray-400 mt-1">สามารถอัปโหลดเพิ่มได้ (รวมกับรูปเดิมต้องไม่เกิน 5 รูป)</p>
            </div>

            <div class="pt-4">
                <button type="submit" name="submit" class="w-full bg-blue-700 text-white font-bold py-3 rounded-xl hover:bg-blue-800 shadow-md transition duration-200">
                    💾 บันทึกการแก้ไข
                </button>
            </div>

        </form>
    </div>

    <!-- Leaflet Maps Script (แผนที่ฟรี ไม่ต้องใช้ API Key) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let marker;

        // --- Dynamic Dropdown for Districts/Subdistricts ---
        const districtsData = <?php echo json_encode($loei_districts); ?>;
        const districtSelect = document.getElementById('district');
        const subdistrictSelect = document.getElementById('subdistrict');
        const initialSubDistrict = "<?php echo isset($herb['sub_district']) ? $herb['sub_district'] : ''; ?>";

        function updateSubdistricts(selectedDistrict, subToSelect = null) {
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>'; // Clear existing options
            if (selectedDistrict && districtsData[selectedDistrict]) {
                districtsData[selectedDistrict].forEach(subdistrict => {
                    const option = document.createElement('option');
                    option.value = subdistrict;
                    option.textContent = subdistrict;
                    if (subToSelect && subdistrict === subToSelect) {
                        option.selected = true;
                    }
                    subdistrictSelect.appendChild(option);
                });
            }
        }

        districtSelect.addEventListener('change', function() {
            // เมื่อมีการเปลี่ยนอำเภอ ให้ล้างค่าตำบลที่เลือกไว้
            updateSubdistricts(this.value);
        });


        document.addEventListener('DOMContentLoaded', function() {
            // ตอนโหลดหน้าเว็บ ให้โหลดตำบลของอำเภอที่ถูกเลือกไว้
            updateSubdistricts(districtSelect.value, initialSubDistrict);

            const galleryUpload = document.getElementById('gallery-upload');
            if (galleryUpload) {
                galleryUpload.addEventListener('change', function(e) {
                    const currentImagesContainer = document.getElementById('current-gallery-images');
                    const currentImagesCount = currentImagesContainer ? parseInt(currentImagesContainer.getAttribute('data-count')) || 0 : 0;
                    const newImagesCount = this.files.length;
                    
                    if (currentImagesCount + newImagesCount > 5) {
                        alert(`สามารถมีรูปภาพรวมกันได้สูงสุด 5 รูปเท่านั้น\nตอนนี้คุณมีอยู่แล้ว ${currentImagesCount} รูป (เลือกเพิ่มได้อีก ${5 - currentImagesCount} รูป)`);
                        this.value = ''; // ล้างค่าไฟล์ที่เลือก
                    }
                });
            }

            let startLocation = [17.4925, 101.7223];
            const inputCoords = document.getElementById('coordinates_input').value;
            
            if (inputCoords) {
                const parts = inputCoords.split(',');
                if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                    startLocation = [parseFloat(parts[0].trim()), parseFloat(parts[1].trim())];
                }
            }

            map = L.map('mapPicker', { scrollWheelZoom: false }).setView(startLocation, 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            marker = L.marker(startLocation, {draggable: true}).addTo(map);

            map.on('click', function(e) { marker.setLatLng(e.latlng); updateInput(e.latlng); });
            marker.on('dragend', function(e) { updateInput(marker.getLatLng()); });

            document.getElementById('coordinates_input').addEventListener('input', function() {
                const parts = this.value.split(',');
                if(parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                    const newLatLng = [parseFloat(parts[0].trim()), parseFloat(parts[1].trim())];
                    marker.setLatLng(newLatLng); map.panTo(newLatLng);
                }
            });
            
            setTimeout(() => { map.invalidateSize(); }, 400);
        });

        function updateInput(latlng) { document.getElementById('coordinates_input').value = latlng.lat.toFixed(6) + ", " + latlng.lng.toFixed(6); }
        
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const latlng = [position.coords.latitude, position.coords.longitude];
                    marker.setLatLng(latlng); map.setView(latlng, 15); updateInput({lat: latlng[0], lng: latlng[1]});
                }, function() { alert("ไม่สามารถดึงตำแหน่งปัจจุบันได้ กรุณาตรวจสอบว่าอนุญาตการเข้าถึงตำแหน่งหรือไม่"); });
            } else { alert("เบราว์เซอร์ของคุณไม่รองรับการดึงตำแหน่งปัจจุบัน"); }
        }
    </script>
</body>
</html>