<?php
session_start();
unset($_SESSION['admin_login']);
unset($_SESSION['admin_id']);

header("Location: login.php"); // เด้งกลับไปหน้า Login
exit();
?>