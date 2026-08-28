<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author = $_POST['author'];
    $tags = $_POST['tags'];
    $image_path = "";

    // Upload Image
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid() . "." . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $new_name);
        $image_path = $new_name;
    }

    $sql = "INSERT INTO articles (title, content, author, tags, image_path) VALUES (:title, :content, :author, :tags, :image_path)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'title' => $title,
        'content' => $content,
        'author' => $author,
        'tags' => $tags,
        'image_path' => $image_path
    ]);

    header("Location: articles.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>เพิ่มบทความใหม่ - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <!-- TinyMCE Rich Text Editor -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
      tinymce.init({
        selector: 'textarea[name="content"]',
        plugins: 'paste image link autolink lists media table wordcount',
        toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright | indent outdent | bullist numlist | image media | table',
        paste_data_images: true, // เปิดใช้งานการวางรูปภาพจาก Clipboard
        height: 400,
        menubar: false,
      });
    </script>
</head>
<body class="bg-gray-100">
    <div class="max-w-3xl mx-auto py-10 px-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">✍️ เขียนบทความใหม่</h2>
                <a href="articles.php" class="text-gray-500 hover:text-gray-700">ยกเลิก</a>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หัวข้อบทความ</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เนื้อหาบทความ</label>
                    <textarea name="content" rows="10" required class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ผู้เขียน</label>
                        <input type="text" name="author" placeholder="เช่น ปราชญ์ชาวบ้าน, แอดมิน" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tags (คั่นด้วยลูกน้ำ)</label>
                        <input type="text" name="tags" placeholder="เช่น ยาต้ม, ภูหลวง, ความเชื่อ" class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รูปภาพปก</label>
                    <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="bg-green-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-green-700 transition shadow-md">
                        บันทึกบทความ
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>