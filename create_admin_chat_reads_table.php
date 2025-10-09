<?php
require_once 'api/db.php';

try {
    // إنشاء جدول admin_chat_reads
    $sql = "
        CREATE TABLE IF NOT EXISTS admin_chat_reads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id INT NOT NULL,
            user_id INT NOT NULL,
            last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES admin_chats(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_chat_user_read (chat_id, user_id)
        )
    ";
    
    $pdo->exec($sql);
    echo "جدول admin_chat_reads تم إنشاؤه بنجاح\n";
    
    // إنشاء الفهارس
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_chat_reads_chat_user ON admin_chat_reads(chat_id, user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_chat_reads_last_read ON admin_chat_reads(last_read_at DESC)");
    
    echo "الفهارس تم إنشاؤها بنجاح\n";
    echo "تم إعداد جدول admin_chat_reads بنجاح!\n";
    
} catch (Exception $e) {
    echo "خطأ في إنشاء الجدول: " . $e->getMessage() . "\n";
}
?>