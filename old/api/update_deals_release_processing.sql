-- إضافة حقل لتتبع حالة معالجة تحرير الأموال
-- هذا الحقل سيحفظ حالة معالجة طلب تحرير الأموال

ALTER TABLE deals 
ADD COLUMN release_funds_processing BOOLEAN DEFAULT FALSE,
ADD COLUMN release_funds_requested_at TIMESTAMP NULL,
ADD COLUMN release_funds_requested_by INT NULL,
ADD CONSTRAINT fk_deals_release_requester 
    FOREIGN KEY (release_funds_requested_by) 
    REFERENCES users(id) 
    ON DELETE SET NULL;

-- إضافة فهرس لتحسين الأداء
CREATE INDEX idx_deals_release_processing ON deals(release_funds_processing);

-- تعليق: 
-- release_funds_processing: يشير إلى أن طلب تحرير الأموال قيد المعالجة
-- release_funds_requested_at: وقت طلب تحرير الأموال
-- release_funds_requested_by: معرف المستخدم الذي طلب تحرير الأموال