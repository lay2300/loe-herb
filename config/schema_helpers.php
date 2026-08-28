<?php
function ensure_user_columns(PDO $conn) {
    $requiredColumns = [
        'first_name' => 'VARCHAR(100) NULL',
        'last_name' => 'VARCHAR(100) NULL',
        'avatar' => 'VARCHAR(255) NULL',
        'phone' => 'VARCHAR(20) NULL',
        'oauth_provider' => 'VARCHAR(50) NULL',
        'oauth_uid' => 'VARCHAR(255) NULL',
    ];

    foreach ($requiredColumns as $column => $definition) {
        try {
            $row = $conn->query("SHOW COLUMNS FROM users LIKE '" . $column . "'")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $conn->exec("ALTER TABLE users ADD COLUMN $column $definition");
            }
        } catch (Exception $e) {
            // If users table doesn't exist or ALTER fails, rethrow so caller can handle/log
            throw $e;
        }
    }
}

function ensure_notifications_columns(PDO $conn) {
    $cols = [
        'image_path' => 'VARCHAR(255) NULL'
    ];
    foreach ($cols as $col => $def) {
        try {
            $row = $conn->query("SHOW COLUMNS FROM notifications LIKE '" . $col . "'")->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $conn->exec("ALTER TABLE notifications ADD COLUMN $col $def");
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
}

?>
