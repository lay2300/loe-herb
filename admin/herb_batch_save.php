<?php
require_once '../config/security.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบผู้ดูแล']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้อง']);
    exit();
}

$records = json_decode($_POST['records'] ?? '', true);
if (!is_array($records) || !$records || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไม่พบรายการสมุนไพรสำหรับบันทึก']);
    exit();
}

$fields = [
    'thai_name', 'sci_name', 'family_name', 'category', 'other_names',
    'general_characteristics', 'properties', 'parts_used', 'culinary_uses',
    'additional_info', 'location_found', 'sub_district', 'coordinates'
];

foreach ($records as $index => $record) {
    if (!is_array($record)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'รูปแบบข้อมูลรายการที่ ' . ($index + 1) . ' ไม่ถูกต้อง']);
        exit();
    }
    foreach ($fields as $field) {
        if (isset($record[$field]) && !is_string($record[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลรายการที่ ' . ($index + 1) . ' ไม่ถูกต้อง']);
            exit();
        }
    }
    if (trim($record['thai_name'] ?? '') === '' || trim($record['properties'] ?? '') === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'รายการที่ ' . ($index + 1) . ' ต้องมีชื่อภาษาไทยและสรรพคุณ']);
        exit();
    }
    if (mb_strlen($record['thai_name']) > 255 || mb_strlen($record['category'] ?? '') > 100) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ข้อมูลรายการที่ ' . ($index + 1) . ' ยาวเกินกำหนด']);
        exit();
    }
}

require_once '../config/db.php';
$uploadDirectory = __DIR__ . '/../uploads/';
$movedFiles = [];
$conn->beginTransaction();

try {
    $insert = $conn->prepare("INSERT INTO herbs (thai_name, sci_name, family_name, category, other_names, general_characteristics, properties, parts_used, culinary_uses, additional_info, location_found, sub_district, coordinates, image_path) VALUES (:thai_name, :sci_name, :family_name, :category, :other_names, :general_characteristics, :properties, :parts_used, :culinary_uses, :additional_info, :location_found, :sub_district, :coordinates, :image_path)");
    $imageInsert = $conn->prepare('INSERT INTO herb_images (herb_id, image_path) VALUES (?, ?)');
    $imageTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('ไม่สามารถเตรียมโฟลเดอร์อัปโหลดได้');
    }

    foreach ($records as $index => $record) {
        $imagePath = '';
        $images = $_FILES['images'] ?? null;
        if ($images && isset($images['error'][$index]) && $images['error'][$index] !== UPLOAD_ERR_NO_FILE) {
            if ($images['error'][$index] !== UPLOAD_ERR_OK || !is_uploaded_file($images['tmp_name'][$index])) {
                throw new RuntimeException('รับรูปของรายการที่ ' . ($index + 1) . ' ไม่สำเร็จ');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $images['tmp_name'][$index]);
            finfo_close($finfo);
            if (!isset($imageTypes[$mimeType])) {
                throw new RuntimeException('รูปของรายการที่ ' . ($index + 1) . ' ไม่ใช่ไฟล์ภาพที่รองรับ');
            }

            $imagePath = date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $imageTypes[$mimeType];
            if (!move_uploaded_file($images['tmp_name'][$index], $uploadDirectory . $imagePath)) {
                throw new RuntimeException('บันทึกรูปของรายการที่ ' . ($index + 1) . ' ไม่สำเร็จ');
            }
            $movedFiles[] = $uploadDirectory . $imagePath;
        }

        $values = [];
        foreach ($fields as $field) {
            $values[$field] = trim($record[$field] ?? '');
        }
        $values['image_path'] = $imagePath;
        $insert->execute($values);
        $herbId = $conn->lastInsertId();

        if (isset($_FILES['galleries']['name'][$index]) && is_array($_FILES['galleries']['name'][$index])) {
            $galleryCount = min(count($_FILES['galleries']['name'][$index]), 5);
            for ($galleryIndex = 0; $galleryIndex < $galleryCount; $galleryIndex++) {
                if ($_FILES['galleries']['error'][$index][$galleryIndex] === UPLOAD_ERR_NO_FILE) continue;
                if ($_FILES['galleries']['error'][$index][$galleryIndex] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['galleries']['tmp_name'][$index][$galleryIndex])) {
                    throw new RuntimeException('รับรูปแกลเลอรีของรายการที่ ' . ($index + 1) . ' ไม่สำเร็จ');
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $galleryMimeType = finfo_file($finfo, $_FILES['galleries']['tmp_name'][$index][$galleryIndex]);
                finfo_close($finfo);
                if (!isset($imageTypes[$galleryMimeType])) {
                    throw new RuntimeException('รูปแกลเลอรีของรายการที่ ' . ($index + 1) . ' ไม่ใช่ไฟล์ภาพที่รองรับ');
                }

                $galleryPath = date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $imageTypes[$galleryMimeType];
                if (!move_uploaded_file($_FILES['galleries']['tmp_name'][$index][$galleryIndex], $uploadDirectory . $galleryPath)) {
                    throw new RuntimeException('บันทึกรูปแกลเลอรีของรายการที่ ' . ($index + 1) . ' ไม่สำเร็จ');
                }
                $movedFiles[] = $uploadDirectory . $galleryPath;
                $imageInsert->execute([$herbId, $galleryPath]);
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'count' => count($records)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    foreach ($movedFiles as $movedFile) {
        if (is_file($movedFile)) unlink($movedFile);
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}