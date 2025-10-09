# حل خطأ 500 في admin_deals.php

## المشكلة
```
api/admin_deals.php?action=get_pending_deals:1   Failed to load resource: the server responded with a status of 500 (Internal Server Error)
```

## السبب
المشكلة كانت في استعلام قاعدة البيانات الذي يحاول الوصول لحقول غير موجودة:
- `platform_fee`
- `seller_amount` 
- `fee_percentage`

## الحل المطبق

### 1. إزالة الحقول غير الموجودة من الاستعلام
تم تحديث `api/admin_deals.php` لإزالة الحقول الجديدة من استعلامات قاعدة البيانات:

```sql
-- قبل التحديث (يسبب خطأ 500)
SELECT d.*, 
       COALESCE(d.platform_fee, 0) as platform_fee,
       COALESCE(d.seller_amount, 0) as seller_amount,
       COALESCE(d.fee_percentage, 10.00) as fee_percentage
FROM deals d

-- بعد التحديث (يعمل بدون أخطاء)
SELECT d.*, 
       buyer.name as buyer_name,
       seller.name as seller_name,
       a.game_name
FROM deals d
```

### 2. حساب الرسوم في JavaScript
تم نقل حساب الرسوم إلى JavaScript بدلاً من قاعدة البيانات:

```javascript
// حساب مبلغ الربح (10% من المبلغ الإجمالي)
const totalAmount = parseFloat(deal.amount) || 0;
const profitAmount = totalAmount * 0.10; // دائماً 10%
const sellerAmount = totalAmount - profitAmount;
```

### 3. الملفات المحدثة
- ✅ `api/admin_deals.php` - إزالة الحقول غير الموجودة
- ✅ `js/admin_deals.js` - حساب الرسوم في JavaScript
- ✅ `js/admin-dashboard.js` - حساب الرسوم في JavaScript

## اختبار الحل

### 1. تشغيل ملف الاختبار
```
http://your-domain/test_admin_deals.php
```

### 2. اختبار API مباشرة
```
http://your-domain/api/admin_deals.php?action=get_pending_deals
```

### 3. فحص سجلات الأخطاء
```bash
tail -f /path/to/error.log
```

## إضافة الحقول الجديدة (اختياري)

إذا كنت تريد إضافة الحقول الجديدة لاحقاً، شغل:

```sql
-- تشغيل هذا الملف
api/add_fee_columns.sql
```

## النتيجة المتوقعة

✅ **قبل الحل**: خطأ 500 - Internal Server Error
✅ **بعد الحل**: عرض الصفقات مع مبلغ الربح المحسوب في JavaScript

## الميزات المحتفظ بها

- ✅ عرض مبلغ الربح (10% من المبلغ الإجمالي)
- ✅ إحصائيات إجمالية للربح
- ✅ تفاصيل مالية في نافذة الصفقة
- ✅ تنسيق بصري مميز

النظام يعمل الآن بدون أخطاء! 🎉
