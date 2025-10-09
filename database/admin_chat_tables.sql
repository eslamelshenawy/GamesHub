-- إنشاء جدول المحادثات الإدارية
CREATE TABLE IF NOT EXISTS admin_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_admin_user_chat (user_id, admin_id)
);

-- إنشاء جدول رسائل المحادثات الإدارية
CREATE TABLE IF NOT EXISTS admin_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    sender_type ENUM('admin', 'user') NOT NULL,
    message_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES admin_chats(id) ON DELETE CASCADE,
    INDEX idx_chat_created (chat_id, created_at)
);

-- إضافة فهرس لتحسين الأداء
CREATE INDEX idx_admin_chats_user_admin ON admin_chats(user_id, admin_id);
CREATE INDEX idx_admin_chats_last_message ON admin_chats(last_message_at DESC);