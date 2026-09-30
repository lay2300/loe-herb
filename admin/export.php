<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

// ตั้งค่า Header ให้ Browser รู้ว่าเป็นไฟล์ CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=loei_herbs_data_' . date('Y-m-d') . '.csv');

// เปิด Output Stream
$output = fopen('php://output', 'w');

// ใส่ BOM เพื่อให้ Excel อ่านภาษาไทยออก (สำคัญมาก)
fputs($output, (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// เขียนหัวตาราง
fputcsv($output, ['ID', 'ชื่อภาษาไทย', 'ชื่อวิทยาศาสตร์', 'ชื่อวงศ์', 'หมวดหมู่', 'สรรพคุณ', 'พื้นที่ที่พบ', 'วันที่บันทึก']);

// ดึงข้อมูลและเขียนลงไฟล์
$sql = "SELECT id, thai_name, sci_name, family_name, category, properties, location_found, created_at FROM herbs ORDER BY id ASC";
$stmt = $conn->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}
fclose($output);
?>