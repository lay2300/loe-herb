<?php
session_start();
// เลิกล็อกอินเฉพาะผู้ใช้ทั่วไป (ไม่กวนส่วนของ Admin ถ้ามี)
unset($_SESSION['user_login']);
unset($_SESSION['user_id']);
unset($_SESSION['username']);

header("Location: index.php");
exit();
?>