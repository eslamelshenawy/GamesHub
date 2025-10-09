-- إضافة حقل deal_initiator_id إلى جدول الصفقات
-- هذا الحقل سيحفظ معرف المستخدم الذي بدأ الصفقة

ALTER TABLE deals 
ADD COLUMN deal_initiator_id INT NOT NULL DEFAULT 0,
ADD CONSTRAINT fk_deals_initiator 
    FOREIGN KEY (deal_initiator_id) 
    REFERENCES users(id) 
    ON DELETE CASCADE;

-- إضافة فهرس لتحسين الأداء
CREATE INDEX idx_deals_initiator ON deals(deal_initiator_id);

-- تحديث الصفقات الموجودة لتعيين deal_initiator_id = buyer_id
-- (افتراض أن المشتري هو من يبدأ الصفقة عادة)
UPDATE deals SET deal_initiator_id = buyer_id WHERE deal_initiator_id = 0;

-- إضافة حقل delivery_confirmed لتتبع تأكيد التسليم
ALTER TABLE deals 
ADD COLUMN delivery_confirmed BOOLEAN DEFAULT FALSE,
ADD COLUMN delivery_confirmed_at TIMESTAMP NULL,
ADD COLUMN delivery_confirmed_by INT NULL,
ADD CONSTRAINT fk_deals_delivery_confirmer 
    FOREIGN KEY (delivery_confirmed_by) 
    REFERENCES users(id) 
    ON DELETE SET NULL;

-- إضافة فهرس لحقل delivery_confirmed
CREATE INDEX idx_deals_delivery_confirmed ON deals(delivery_confirmed);