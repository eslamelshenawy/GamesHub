-- تحديث نظام رسوم المنصة
-- إضافة حقول جديدة لجدول deals لتتبع الرسوم

ALTER TABLE deals 
ADD COLUMN platform_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT 'رسوم المنصة',
ADD COLUMN seller_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'المبلغ الذي يحصل عليه البائع',
ADD COLUMN fee_percentage DECIMAL(5,2) DEFAULT 10.00 COMMENT 'نسبة الرسوم';

-- إضافة فهرس لتحسين الأداء
CREATE INDEX idx_deals_fee ON deals(platform_fee, seller_amount);

-- تحديث المعاملات المالية لتشمل نوع الرسوم
ALTER TABLE financial_logs 
MODIFY COLUMN type ENUM('ESCROW','RELEASE','REFUND','DEPOSIT','WITHDRAW','FEE','TRANSFER') DEFAULT 'ESCROW';

-- إضافة جدول لتتبع إحصائيات الرسوم
CREATE TABLE IF NOT EXISTS platform_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    deal_id INT NOT NULL,
    fee_amount DECIMAL(10,2) NOT NULL,
    fee_percentage DECIMAL(5,2) NOT NULL,
    seller_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE
);

-- إضافة فهرس للجدول الجديد
CREATE INDEX idx_platform_fees_deal ON platform_fees(deal_id);
CREATE INDEX idx_platform_fees_date ON platform_fees(created_at);
