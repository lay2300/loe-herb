<?php
header("Location: chats.php" . (isset($_GET['id']) ? "?id=".$_GET['id'] : ""));
exit();
?>