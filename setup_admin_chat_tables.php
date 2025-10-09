<?php
// ملف لإنشاء جداول المحادثات الإدارية
require_once 'api/db.php';

try {
    
    echo "الاتصال بقاعدة البيانات تم بنجاح\n";
    
    // إنشاء جدول المحادثات الإدارية
    $sql1 = "
        CREATE TABLE IF NOT EXISTS admin_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_message_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_admin_user_chat (user_id, admin_id)
        )
    ";
    
    $pdo->exec($sql1);
    echo "تم إنشاء جدول admin_chats بنجاح\n";
    
    // إنشاء جدول رسائل المحادثات الإدارية
    $sql2 = "
        CREATE TABLE IF NOT EXISTS admin_chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_id INT NOT NULL,
            sender_type ENUM('admin', 'user') NOT NULL,
            message_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chat_id) REFERENCES admin_chats(id) ON DELETE CASCADE,
            INDEX idx_chat_created (chat_id, created_at)
        )
    ";
    
    $pdo->exec($sql2);
    echo "تم إنشاء جدول admin_chat_messages بنجاح\n";
    
    // إضافة فهارس لتحسين الأداء
    try {
        $pdo->exec("CREATE INDEX idx_admin_chats_user_admin ON admin_chats(user_id, admin_id)");
        echo "تم إنشاء فهرس idx_admin_chats_user_admin\n";
    } catch (Exception $e) {
        echo "فهرس idx_admin_chats_user_admin موجود مسبقاً\n";
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_admin_chats_last_message ON admin_chats(last_message_at DESC)");
        echo "تم إنشاء فهرس idx_admin_chats_last_message\n";
    } catch (Exception $e) {
        echo "فهرس idx_admin_chats_last_message موجود مسبقاً\n";
    }
    
    echo "\nتم إنشاء جميع الجداول والفهارس بنجاح!\n";
    
} catch (PDOException $e) {
    echo "خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "خطأ عام: " . $e->getMessage() . "\n";
}
?>