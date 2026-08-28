<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // ดึงชื่อไฟล์รูปเพื่อลบ
    $stmt = $conn->prepare("SELECT image_path FROM articles WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($article) {
        if ($article['image_path'] && file_exists("../uploads/" . $article['image_path'])) {
            @unlink("../uploads/" . $article['image_path']);
        }
        $conn->prepare("DELETE FROM articles WHERE id = :id")->execute(['id' => $id]);
    }
}
header("Location: articles.php");
?>