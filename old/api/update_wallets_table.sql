-- إضافة عمود الرصيد المعلق لجدول المحافظ
ALTER TABLE `wallets` ADD COLUMN `pending_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `balance`;

-- تحديث جدول الصفقات لإضافة معرف المحادثة إذا لم يكن موجوداً
ALTER TABLE `deals` ADD COLUMN `conversation_id` INT(11) DEFAULT NULL;

-- إضافة فهرس للأداء
ALTER TABLE `deals` ADD INDEX `idx_conversation_id` (`conversation_id`);

-- إضافة مفتاح خارجي للمحادثة
ALTER TABLE `deals` ADD FOREIGN KEY (`conversation_id`) REFERENCES `conversations`(`id`) ON DELETE SET NULL;