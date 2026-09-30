<?php
require_once '../config/security.php';
require_once '../config/docx_smartart.php';
start_secure_session();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_login'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบผู้ดูแล']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'รองรับคำขอแบบ POST เท่านั้น']);
    exit();
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'โทเคนความปลอดภัยไม่ถูกต้องหรือหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่']);
    exit();
}

$upload = $_FILES['docx'] ?? null;
if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'รับไฟล์ Word ไม่สำเร็จ']);
    exit();
}

if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'docx' || $upload['size'] > 20 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'รองรับไฟล์ .docx ขนาดไม่เกิน 20 MB']);
    exit();
}

try {
    $thaiNames = extractSmartArtNamesInDocumentOrder($upload['tmp_name']);
    echo json_encode(['success' => true, 'thai_name' => $thaiNames[0] ?? null, 'thai_names' => $thaiNames], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'อ่าน SmartArt จากไฟล์ Word ไม่สำเร็จ']);
}