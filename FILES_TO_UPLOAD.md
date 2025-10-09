# قائمة الملفات المُعدلة - لوحة الإدارة

## 📋 ملخص التعديلات
تم إصلاح مشاكل JSON في لوحة الإدارة بسبب ترتيب خاطئ للـ headers و session_start().

---

## 🔧 الملفات المُعدلة (يجب رفعها للسيرفر)

### 1. ملفات API - إدارة الشحن والسحب

#### ✅ ملف: `api/get_topup_requests.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\get_topup_requests.php`

**التعديل:**
- إضافة `header('Content-Type: application/json; charset=utf-8');`
- ترتيب الـ require statements (db.php ثم security.php ثم headers)
- استخدام `ensure_session()` بدلاً من `session_start()`

**السبب:** كان يرجع HTML بدلاً من JSON

---

#### ✅ ملف: `api/approve_topup.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\approve_topup.php`

**التعديل:**
- نقل الـ header بعد require statements
- إضافة `require_once 'security.php'`
- استخدام `ensure_session()`

**السبب:** ترتيب خاطئ سبب إصدار warnings قبل JSON

---

#### ✅ ملف: `api/reject_topup.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\reject_topup.php`

**التعديل:**
- نقل الـ header بعد require statements
- إضافة `require_once 'security.php'`
- استخدام `ensure_session()`

**السبب:** ترتيب خاطئ سبب إصدار warnings قبل JSON

---

### 2. ملفات API - إدارة البلاغات

#### ✅ ملف: `api/get_reports.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\get_reports.php`

**التعديل:**
- استبدال `session_start()` بـ `ensure_session()`
- ترتيب الـ requires قبل headers
- إضافة `require_once 'security.php'`

**السبب:** `session_start()` المكرر كان يُصدر HTML warning

---

#### ✅ ملف: `api/admin_reports.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\admin_reports.php`

**التعديل:**
- استبدال `session_start()` بـ `ensure_session()`
- ترتيب الـ requires قبل headers
- إضافة `require_once 'security.php'`

**السبب:** `session_start()` المكرر كان يُصدر HTML warning

---

### 3. ملفات API - إدارة المستخدمين

#### ✅ ملف: `api/get_user_details.php`
**المسار الكامل:** `C:\xampp\htdocs\api\api\get_user_details.php`

**التعديل:**
- ترتيب الـ requires قبل headers
- إضافة `require_once 'security.php'`
- استخدام `ensure_session()`
- إضافة التحقق من صلاحيات الأدمن
- إضافة `charset=utf-8` للـ header

**السبب:** ترتيب خاطئ + عدم التحقق من الصلاحيات

---

### 4. ملفات JavaScript

#### ✅ ملف: `js/admin-dashboard.js`
**المسار الكامل:** `C:\xampp\htdocs\api\js\admin-dashboard.js`

**التعديل:**
- السطر 1601: تغيير المسار من `/api/get_user_details.php` إلى `api/get_user_details.php`

**السبب:** مسار خاطئ كان يسبب 404 Not Found

---

## 📦 الملفات الاختيارية (للاختبار فقط - لا تُرفع للسيرفر)

هذه الملفات تم إنشاؤها لأغراض الاختبار المحلي فقط:

```
api/create_test_topup.php       - إنشاء طلب شحن تجريبي
api/create_test_withdraw.php    - إنشاء طلب سحب تجريبي
api/create_test_report.php      - إنشاء بلاغ تجريبي
```

**⚠️ تحذير:** لا تقم برفع هذه الملفات للسيرفر الإنتاجي!

---

## 🚀 خطوات الرفع للسيرفر

### الطريقة 1: رفع يدوي عبر FTP/cPanel

1. **قم برفع الملفات التالية:**

```
📁 api/
├── approve_topup.php          ← استبدال
├── reject_topup.php           ← استبدال
├── get_topup_requests.php     ← استبدال
├── get_reports.php            ← استبدال
├── admin_reports.php          ← استبدال
└── get_user_details.php       ← استبدال

📁 js/
└── admin-dashboard.js         ← استبدال
```

2. **تأكد من:**
   - حفظ نسخة احتياطية من الملفات القديمة قبل الاستبدال
   - التأكد من الصلاحيات (644 للملفات)
   - اختبار كل صفحة بعد الرفع

---

### الطريقة 2: استخدام Git

إذا كنت تستخدم Git:

```bash
# إضافة الملفات المُعدلة
git add api/approve_topup.php
git add api/reject_topup.php
git add api/get_topup_requests.php
git add api/get_reports.php
git add api/admin_reports.php
git add api/get_user_details.php
git add js/admin-dashboard.js

# عمل commit
git commit -m "Fix: Admin dashboard JSON API issues
- Fixed session_start() warnings
- Fixed headers order in API files
- Fixed viewUserDetails path in admin-dashboard.js
- Added proper session management using ensure_session()"

# رفع للسيرفر
git push origin main
```

---

## ✅ التحقق من نجاح الرفع

بعد رفع الملفات، تأكد من:

1. **إدارة الشحن والسحب:**
   - ✓ البيانات تظهر بدون أخطاء JSON
   - ✓ أزرار "قبول/رفض" تعمل

2. **إدارة البلاغات:**
   - ✓ البلاغات تُحمّل بشكل صحيح
   - ✓ أزرار "حل/رفض" تعمل

3. **إدارة المستخدمين:**
   - ✓ زر "عرض التفاصيل" يعمل
   - ✓ النافذة المنبثقة تظهر بيانات المستخدم

---

## 🔍 استكشاف الأخطاء

إذا ظهرت مشاكل بعد الرفع:

### خطأ: "Unexpected token '<'"
**السبب:** ملف PHP يُرجع HTML بدلاً من JSON
**الحل:** تأكد من ترتيب الـ require statements قبل headers

### خطأ: "404 Not Found"
**السبب:** مسار الملف غير صحيح
**الحل:** تأكد من رفع الملف في المجلد `api/`

### خطأ: "session_start() already active"
**السبب:** استخدام `session_start()` بدلاً من `ensure_session()`
**الحل:** تأكد من استخدام `ensure_session()` في جميع الملفات

---

## 📞 ملاحظات مهمة

1. **النسخ الاحتياطي:**
   - احفظ نسخة من الملفات القديمة قبل الاستبدال
   - يمكنك إعادة التراجع في أي وقت

2. **الاختبار:**
   - اختبر كل قسم بعد الرفع
   - تأكد من تسجيل الدخول كأدمن

3. **الأمان:**
   - تأكد من أن السيرفر يدعم `session_start()`
   - تأكد من صلاحيات قاعدة البيانات

---

## 📊 ملخص التغييرات

| الملف | التعديل الرئيسي | الحالة |
|-------|-----------------|--------|
| `api/get_topup_requests.php` | إصلاح header + session | ✅ جاهز |
| `api/approve_topup.php` | إصلاح ترتيب requires | ✅ جاهز |
| `api/reject_topup.php` | إصلاح ترتيب requires | ✅ جاهز |
| `api/get_reports.php` | إصلاح session_start | ✅ جاهز |
| `api/admin_reports.php` | إصلاح session_start | ✅ جاهز |
| `api/get_user_details.php` | إصلاح ترتيب + صلاحيات | ✅ جاهز |
| `js/admin-dashboard.js` | إصلاح مسار API | ✅ جاهز |

---

## ⚡ الترتيب الصحيح المُستخدم في جميع الملفات

```php
<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

// باقي الكود...
```

---

**تاريخ التعديل:** 2025-10-10
**الإصدار:** 1.0
**المُطور:** Claude Code

---

## 🎯 النتيجة النهائية

✅ جميع أقسام لوحة الإدارة تعمل بشكل صحيح
✅ لا توجد أخطاء JSON
✅ جميع الأزرار والإجراءات تعمل
✅ البيانات تُحمّل وتُحفظ بنجاح
