# إرشادات إصلاح قاعدة البيانات

## المشكلة
خطأ في قاعدة البيانات: `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'description' in 'field list'`

## السبب
الـ view المسمى `deals_with_users` في قاعدة البيانات ما زال يحتوي على مرجع للعمود `description` الذي تم حذفه من جدول `accounts`.

## الحل

### الطريقة الأولى: استخدام phpMyAdmin أو MySQL Workbench
1. افتح phpMyAdmin أو MySQL Workbench
2. اختر قاعدة البيانات `games_accounts`
3. نفذ الكود SQL التالي:

```sql
DROP VIEW IF EXISTS `deals_with_users`;

CREATE VIEW `deals_with_users` AS 
SELECT 
    d.id,
    d.buyer_id,
    d.seller_id,
    d.amount,
    d.details,
    d.status,
    d.escrow_amount,
    d.escrow_status,
    d.created_at,
    d.updated_at,
    d.account_id,
    d.conversation_id,
    d.buyer_confirmed_at,
    d.admin_review_status,
    d.admin_reviewed_by,
    d.admin_reviewed_at,
    d.admin_notes,
    buyer.name AS buyer_name,
    buyer.phone AS buyer_phone,
    seller.name AS seller_name,
    seller.phone AS seller_phone,
    acc.game_name,
    '' AS account_description
FROM deals d
LEFT JOIN users buyer ON d.buyer_id = buyer.id
LEFT JOIN users seller ON d.seller_id = seller.id
LEFT JOIN accounts acc ON d.account_id = acc.id;
```

### الطريقة الثانية: استخدام خادم PHP
1. قم بتشغيل خادم PHP (مثل XAMPP أو WAMP)
2. ضع المشروع في مجلد الخادم
3. افتح `http://localhost/GamesHub_project/execute_fix.php`

### الطريقة الثالثة: استخدام سطر الأوامر
```bash
mysql -u username -p games_accounts < fix_view.sql
```

## الملفات المُحدثة
- `fix_view.sql` - يحتوي على كود SQL للإصلاح
- `execute_fix.php` - ملف PHP لتنفيذ الإصلاح
- `api/games_accounts.sql` - تم تحديث تعريف الـ view فيه

## ملاحظة مهمة
الخادم الحالي (Python HTTP Server) لا يدعم PHP، لذلك يجب استخدام إحدى الطرق المذكورة أعلاه لتنفيذ الإصلاح.