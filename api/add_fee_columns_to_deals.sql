-- إضافة أعمدة الرسوم إلى جدول deals
ALTER TABLE `deals` 
ADD COLUMN `platform_fee` decimal(10,2) DEFAULT 0.00 COMMENT 'رسوم المنصة',
ADD COLUMN `seller_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'المبلغ المحول للبائع بعد خصم الرسوم',
ADD COLUMN `fee_percentage` decimal(5,2) DEFAULT 10.00 COMMENT 'نسبة الرسوم المئوية';
