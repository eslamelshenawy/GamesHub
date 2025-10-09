-- إنشاء جدول لتتبع آخر قراءة للمحادثات الإدارية
CREATE TABLE IF NOT EXISTS admin_chat_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES admin_chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_chat_user_read (chat_id, user_id)
);

-- إضافة فهرس لتحسين الأداء
CREATE INDEX idx_admin_chat_reads_chat_user ON admin_chat_reads(chat_id, user_id);
CREATE INDEX idx_admin_chat_reads_last_read ON admin_chat_reads(last_read_at DESC);