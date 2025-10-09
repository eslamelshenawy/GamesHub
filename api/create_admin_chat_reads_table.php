<?php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `admin_chat_reads` (
        `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `chat_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `last_read_at` TIMESTAMP NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_chat_user` (`chat_id`, `user_id`),
        KEY `idx_chat_id` (`chat_id`),
        KEY `idx_user_id` (`user_id`),
        CONSTRAINT `fk_admin_chat_reads_chat` FOREIGN KEY (`chat_id`) REFERENCES `admin_chats` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_admin_chat_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء جدول admin_chat_reads بنجاح'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
