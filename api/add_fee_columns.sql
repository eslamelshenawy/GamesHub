-- إضافة حقول الرسوم إلى جدول deals
-- تشغيل هذا الملف إذا لم تكن الحقول موجودة

-- التحقق من وجود الحقول وإضافتها إذا لم تكن موجودة
ALTER TABLE deals 
ADD COLUMN IF NOT EXISTS platform_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'رسوم المنصة',
ADD COLUMN IF NOT EXISTS seller_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'المبلغ الذي يحصل عليه البائع',
ADD COLUMN IF NOT EXISTS fee_percentage DECIMAL(5,2) DEFAULT 10.00 COMMENT 'نسبة الرسوم';

-- إضافة فهرس لتحسين الأداء
CREATE INDEX IF NOT EXISTS idx_deals_fee ON deals(platform_fee, seller_amount);

-- تحديث المعاملات المالية لتشمل نوع الرسوم
ALTER TABLE financial_logs 
MODIFY COLUMN type ENUM('ESCROW','RELEASE','REFUND','DEPOSIT','WITHDRAW','FEE','TRANSFER') DEFAULT 'ESCROW';
