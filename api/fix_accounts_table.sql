-- إضافة عمود description إلى جدول accounts إذا لم يكن موجوداً
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS description TEXT NOT NULL DEFAULT '';

-- التأكد من وجود العمود game_name
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS game_name VARCHAR(100) NOT NULL DEFAULT '';

-- التأكد من وجود العمود price
ALTER TABLE accounts ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0;

-- عرض بنية الجدول للتأكد
DESCRIBE accounts;