<?php
function app_log($message) {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    $file = $logDir . '/app.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($file, "[$time] " . $message . "\n", FILE_APPEND);
}
?>
