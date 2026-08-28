<?php
session_start();
// ตรวจสอบว่า Login หรือยัง
if (!isset($_SESSION['admin_login'])) {
    header("Location: login.php");
    exit();
}

// 1. ดึงไฟล์เชื่อมต่อฐานข้อมูล
require_once '../config/db.php';
require_once '../config/thai_geography.php'; // ดึงข้อมูลอำเภอ/ตำบล

// 2. ตรวจสอบว่ามีการกดปุ่ม "บันทึก" หรือยัง
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
    
    // จัดการเรื่องการอัปโหลดรูปภาพ
    $image_name = $_FILES['image']['name'];
    $tmp_name = $_FILES['image']['tmp_name'];
    $upload_dir = "../uploads/";

    // ตรวจสอบไฟล์เพื่อความปลอดภัย
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $image_ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $tmp_name);
    finfo_close($finfo);

    if (!in_array($image_ext, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
        echo "<script>alert('ชนิดของไฟล์ไม่ถูกต้อง! กรุณาอัปโหลดเฉพาะไฟล์รูปภาพ (jpg, png, gif, webp) เท่านั้น'); window.history.back();</script>";
        exit();
    }

    // ตรวจสอบว่ามีโฟลเดอร์ uploads หรือไม่ ถ้าไม่มีให้สร้าง
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // ตั้งชื่อไฟล์ใหม่ทั้งหมดเพื่อล้างอักขระแปลกปลอมในชื่อไฟล์
    $new_image_name = date("YmdHis") . "_" . uniqid() . "." . $image_ext;

    if (move_uploaded_file($tmp_name, $upload_dir . $new_image_name)) {
        try {
            // 3. เตรียมคำสั่ง SQL สำหรับเพิ่มข้อมูล
            $sql = "INSERT INTO herbs (thai_name, local_name, sci_name, family_name, category, other_names, general_characteristics, properties, parts_used, culinary_uses, additional_info, location_found, sub_district, coordinates, image_path) 
                    VALUES (:thai_name, :local_name, :sci_name, :family_name, :category, :other_names, :general_characteristics, :properties, :parts_used, :culinary_uses, :additional_info, :location_found, :sub_district, :coordinates, :image_path)";
            
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
                'culinary_uses' => $culinary_uses,
                'additional_info' => $additional_info,
                'location_found' => $location_found,
                'sub_district' => $sub_district,
                'coordinates' => $coordinates,
                'image_path' => $new_image_name
            ]);
            
            // --- จัดการ Gallery (หลายรูป) ---
            $herb_id = $conn->lastInsertId(); // ดึง ID ของสมุนไพรที่เพิ่งเพิ่ม
            if (!empty($_FILES['gallery']['name'][0])) {
                $total_files = count($_FILES['gallery']['name']);
                $total_files = min($total_files, 5); // จำกัดอัปโหลดได้สูงสุด 5 รูป
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['gallery']['error'][$i] == 0) {
                        $g_name = $_FILES['gallery']['name'][$i];
                        $g_tmp = $_FILES['gallery']['tmp_name'][$i];
                        $g_new = date("YmdHis") . "_" . uniqid() . "_" . $g_name;
                        if (move_uploaded_file($g_tmp, $upload_dir . $g_new)) {
                            $conn->prepare("INSERT INTO herb_images (herb_id, image_path) VALUES (?, ?)")->execute([$herb_id, $g_new]);
                        }
                    }
                }
            }

            // บันทึกสำเร็จแล้วกลับไปหน้า Dashboard
            header("Location: index.php");
            exit();

        } catch (PDOException $e) {
            // ถ้า Error เรื่องหาคอลัมน์ไม่เจอ ให้แจ้งเตือนให้ไปอัปเดตฐานข้อมูล
            if (strpos($e->getMessage(), 'Column not found') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative' role='alert'>";
                echo "<strong class='font-bold'>เกิดข้อผิดพลาด!</strong>";
                echo "<span class='block sm:inline'> ฐานข้อมูลยังไม่อัปเดตครับ (ไม่พบคอลัมน์หรือตารางใหม่)</span>";
                echo "<br><a href='update_schema.php' class='underline font-bold'>👉 คลิกที่นี่เพื่ออัปเดตฐานข้อมูล (update_schema.php)</a>";
                echo "</div>";
            } else {
                echo "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    } else {
        echo "<script>alert('อัปโหลดรูปภาพไม่สำเร็จ! กรุณาตรวจสอบว่ามีโฟลเดอร์ uploads หรือไม่ หรือไฟล์มีขนาดใหญ่เกินไป');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>เพิ่มสมุนไพรใหม่ - LoeiHerb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="bg-gray-50 p-4 md:p-8">

    <div class="animate-slide-up max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-green-800">🌱 เพิ่มสมุนไพรใหม่</h2>
            <a href="index.php" class="text-gray-500 hover:text-gray-700 text-sm">กลับหน้าหลัก</a>
        </div>

        <form action="add.php" method="POST" enctype="multipart/form-data" class="space-y-4">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">ชื่อภาษาไทย <span class="text-red-500">*</span></label>
                <input type="text" name="thai_name" required class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อท้องถิ่น (จังหวัดเลย)</label>
                    <input type="text" name="local_name" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อวิทยาศาสตร์</label>
                    <input type="text" name="sci_name" class="mt-1 w-full p-2.5 border rounded-lg italic focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่อวงศ์ (Family Name)</label>
                    <input type="text" name="family_name" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">หมวดหมู่</label>
                    <select name="category" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500 bg-white">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <option value="ไม้ต้น">ไม้ต้น</option>
                        <option value="ไม้พุ่ม">ไม้พุ่ม</option>
                        <option value="ไม้เลื้อย">ไม้เลื้อย</option>
                        <option value="ไม้ล้มลุก">ไม้ล้มลุก</option>
                        <option value="ไม้รอเลื้อย">ไม้รอเลื้อย</option>
                        <option value="พืชน้ำ">พืชน้ำ</option>
                        <option value="อื่นๆ">อื่นๆ</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ชื่ออื่น ๆ</label>
                    <input type="text" name="other_names" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ลักษณะทั่วไป</label>
                <textarea name="general_characteristics" rows="3" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">สรรพคุณและวิธีใช้ <span class="text-red-500">*</span></label>
                <textarea name="properties" rows="4" required class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">ส่วนที่ใช้เป็นยา</label>
                    <input type="text" name="parts_used" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ด้านอาหาร</label>
                    <input type="text" name="culinary_uses" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ข้อมูลเพิ่มเติม</label>
                <textarea name="additional_info" rows="3" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500"></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">จังหวัด</label>
                    <input type="text" value="เลย" disabled class="mt-1 w-full p-2.5 border rounded-lg bg-gray-100 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">อำเภอที่สำรวจพบ <span class="text-red-500">*</span></label>
                    <select name="location_found" id="district" required class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500 bg-white">
                        <option value="">-- เลือกอำเภอ --</option>
                        <?php foreach (array_keys($loei_districts) as $district): ?>
                            <option value="<?php echo $district; ?>">
                                <?php echo $district; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ตำบล</label>
                    <select name="sub_district" id="subdistrict" class="mt-1 w-full p-2.5 border rounded-lg focus:ring-green-500 bg-white">
                        <option value="">-- เลือกตำบล --</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">พิกัดแผนที่ (ละติจูด, ลองจิจูด)</label>
                    <div class="flex space-x-2 mt-1">
                        <input type="text" name="coordinates" id="coordinates_input" placeholder="เช่น 17.4925, 101.7223" class="w-full p-2.5 border rounded-lg focus:ring-green-500">
                        <button type="button" onclick="getCurrentLocation()" class="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 transition shadow-sm text-sm whitespace-nowrap border border-gray-300" title="ตำแหน่งปัจจุบัน">📍</button>
                    </div>
                </div>
                <div>
                    <div id="mapPicker" class="w-full h-24 mt-1 rounded-xl border border-gray-300 shadow-inner z-0 relative overflow-hidden bg-gray-100"></div>
                    <p class="text-xs text-gray-400 mt-1">คลิกบนแผนที่หรือลากหมุดเพื่อเลือกพิกัด</p>
                </div>
            </div>

            <!-- Drag & Drop Zone: รูปหลัก -->
            <div class="pt-4 border-t border-gray-100">
                <label class="block text-sm font-medium text-gray-700 mb-2">รูปภาพสมุนไพร (รูปหลัก) <span class="text-red-500">*</span></label>
                <div id="dropzone-main" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:bg-gray-50 transition cursor-pointer group bg-white">
                    <input type="file" name="image" id="image" accept="image/*" required class="hidden">
                    <div id="preview-main" class="hidden mb-4 flex justify-center"></div>
                    <div id="placeholder-main">
                        <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        </div>
                        <p class="text-gray-500 font-medium">คลิกเพื่อเลือกรูป หรือลากไฟล์มาวางที่นี่</p>
                        <p class="text-xs text-gray-400 mt-1">รองรับไฟล์ JPG, PNG, GIF</p>
                    </div>
                </div>
            </div>

            <!-- Drag & Drop Zone: Gallery -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">รูปภาพเพิ่มเติม (Gallery)</label>
                <div id="dropzone-gallery" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:bg-gray-50 transition cursor-pointer group bg-white">
                    <input type="file" name="gallery[]" id="gallery" accept="image/*" multiple class="hidden">
                    <div id="preview-gallery" class="hidden grid grid-cols-3 md:grid-cols-4 gap-4 mb-4"></div>
                    <div id="placeholder-gallery">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <p class="text-gray-500 font-medium">คลิกเพื่อเลือกรูป หรือลากไฟล์มาวางที่นี่ (หลายรูป)</p>
                        <p class="text-xs text-gray-400 mt-1">เลือกได้หลายไฟล์พร้อมกัน (สูงสุด 5 รูป)</p>
                    </div>
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" name="submit" class="w-full bg-green-700 text-white font-bold py-3 rounded-xl hover:bg-green-800 shadow-md transition duration-200">
                    💾 บันทึกข้อมูลสมุนไพร
                </button>
            </div>

        </form>
    </div>

    <script>
        function setupDragDrop(dropzoneId, inputId, previewId, placeholderId, isMultiple) {
            const dropzone = document.getElementById(dropzoneId);
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);

            // เมื่อคลิกที่ Dropzone ให้เปิด File Dialog
            dropzone.addEventListener('click', () => input.click());

            // เมื่อมีการเลือกไฟล์
            input.addEventListener('change', () => handleFiles(input.files));

            // Drag Events
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('border-green-500', 'bg-green-50');
            });

            ['dragleave', 'dragend'].forEach(type => {
                dropzone.addEventListener(type, () => {
                    dropzone.classList.remove('border-green-500', 'bg-green-50');
                });
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('border-green-500', 'bg-green-50');
                
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files; // กำหนดไฟล์ให้กับ input
                    handleFiles(e.dataTransfer.files);
                }
            });

            function handleFiles(files) {
                if (isMultiple && files.length > 5) {
                    alert('สามารถอัปโหลดรูปภาพเพิ่มเติมได้สูงสุด 5 รูปเท่านั้น');
                    input.value = ''; // ล้างไฟล์ที่เลือก
                    preview.innerHTML = '';
                    preview.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                    return;
                }
                preview.innerHTML = ''; // ล้าง Preview เก่า
                if (files.length > 0) {
                    preview.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                    
                    Array.from(files).forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = (e) => {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.className = isMultiple ? 'h-24 w-full object-cover rounded-lg shadow-sm border border-gray-200' : 'h-64 rounded-lg shadow-md object-cover border border-gray-200';
                                preview.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                } else {
                    preview.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                }
            }
        }

        // เรียกใช้งานฟังก์ชัน
        setupDragDrop('dropzone-main', 'image', 'preview-main', 'placeholder-main', false);
        setupDragDrop('dropzone-gallery', 'gallery', 'preview-gallery', 'placeholder-gallery', true);

        // --- Dynamic Dropdown for Districts/Subdistricts ---
        const districtsData = <?php echo json_encode($loei_districts); ?>;
        const districtSelect = document.getElementById('district');
        const subdistrictSelect = document.getElementById('subdistrict');

        function updateSubdistricts(selectedDistrict, initialSubDistrict = null) {
            subdistrictSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>'; // Clear existing options
            if (selectedDistrict && districtsData[selectedDistrict]) {
                districtsData[selectedDistrict].forEach(subdistrict => {
                    const option = document.createElement('option');
                    option.value = subdistrict;
                    option.textContent = subdistrict;
                    if (initialSubDistrict && subdistrict === initialSubDistrict) {
                        option.selected = true;
                    }
                    subdistrictSelect.appendChild(option);
                });
            }
        }

        districtSelect.addEventListener('change', function() {
            updateSubdistricts(this.value);
        });

    </script>
    
    <!-- Leaflet Maps Script (แผนที่ฟรี ไม่ต้องใช้ API Key) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let marker;

        document.addEventListener('DOMContentLoaded', function() {
            let startLocation = [17.4925, 101.7223]; // พิกัดเริ่มต้น (อ.เมืองเลย)
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
            
            // แก้ปัญหาแผนที่โหลดไม่เต็มกรอบ
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