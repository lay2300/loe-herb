<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/oauth.php';
require_once __DIR__ . '/config/schema_helpers.php';
require_once __DIR__ . '/config/app_log.php';
require_once __DIR__ . '/config/curl_helper.php';
require_once __DIR__ . '/process_oauth.php';
?>