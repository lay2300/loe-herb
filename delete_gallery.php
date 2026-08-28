<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT image_path, herb_id FROM herb_images WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($img) {
        $file_path = "../uploads/" . $img['image_path'];
        if (file_exists($file_path)) { @unlink($file_path); }
        
        $del = $conn->prepare("DELETE FROM herb_images WHERE id = :id");
        $del->execute(['id' => $id]);
        
        header("Location: edit.php?id=" . $img['herb_id']);
        exit();
    }
}
header("Location: index.php");
?>