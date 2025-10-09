# 🔗 دليل الروابط السريع

## 🏠 الصفحات الرئيسية

```
http://localhost/api/                           # الصفحة الرئيسية
http://localhost/api/login.html                 # تسجيل الدخول
http://localhost/api/messages.php               # صفحة الرسائل
http://localhost/api/admin-dashboard.html       # لوحة الإدارة
http://localhost/api/myaccount.html             # حسابي (إعلاناتي)
```

---

## 💬 روابط الدردشة

```bash
# قائمة المحادثات
http://localhost/api/messages.php

# فتح محادثة مع مستخدم معين (غير 5 بالـ ID المطلوب)
http://localhost/api/messages.php?user_id=5
http://localhost/api/messages.php?seller_id=5

# فتح محادثة بـ conversation_id
http://localhost/api/messages.php?conversation_id=10
```

---

## 🔧 روابط الاختبار

```bash
# إضافة محادثة تجريبية
http://localhost/api/api/test_add_conversation.php

# التحقق من جداول قاعدة البيانات
http://localhost/api/api/check_admin_tables.php

# إنشاء جدول admin_chat_reads
http://localhost/api/api/create_admin_chat_reads_table.php
```

---

## 🔐 API - Authentication

```javascript
GET   api/check_login.php          // التحقق من تسجيل الدخول
POST  api/login.php                // تسجيل الدخول
POST  api/logout.php               // تسجيل الخروج
POST  api/signup.php               // إنشاء حساب جديد
```

---

## 💬 API - Messages

```javascript
GET   api/get_conversations.php              // جلب قائمة المحادثات
GET   api/get_messages.php?user_id=5        // جلب رسائل محادثة
POST  api/send_message.php                  // إرسال رسالة نصية
POST  api/send_file.php                     // إرسال صورة/ملف
POST  api/delete_message.php                // حذف رسالة
POST  api/mark_read.php                     // تعليم الرسائل كمقروءة
```

**طريقة استخدام send_message.php:**
```javascript
const formData = new FormData();
formData.append('to', 5);                    // ID المستقبل
formData.append('message', 'مرحباً!');      // النص
formData.append('csrf_token', token);        // CSRF Token

fetch('api/send_message.php', {
  method: 'POST',
  body: formData,
  credentials: 'include'
});
```

**طريقة استخدام send_file.php:**
```javascript
const formData = new FormData();
formData.append('to', 5);                    // ID المستقبل
formData.append('file', fileInput.files[0]); // الملف
formData.append('message', 'شوف الصورة');   // نص اختياري
formData.append('csrf_token', token);        // CSRF Token

fetch('api/send_file.php', {
  method: 'POST',
  body: formData,
  credentials: 'include'
});
```

---

## 🤝 API - Deals (الصفقات)

```javascript
GET   api/get_active_deal.php?conversation_id=10    // جلب الصفقة النشطة
POST  api/fund_deal.php                             // تمويل صفقة
POST  api/fund_deal_user.php                        // تأكيد استلام البيانات
POST  api/release_funds.php                         // تحرير الأموال
POST  api/cancel_deal_user.php                      // إلغاء صفقة
```

---

## ⚙️ API - Admin

```javascript
GET   api/get_user_admin_chats.php            // جلب المحادثات الإدارية
GET   api/get_user_admin_messages.php         // جلب رسائل محادثة إدارية
POST  api/send_user_admin_message.php         // إرسال رسالة إدارية
POST  api/ban_user.php                        // حظر مستخدم
POST  api/unban_user.php                      // إلغاء حظر مستخدم
```

---

## 👤 API - User Profile

```javascript
GET   api/get_user.php?id=5                   // جلب بيانات مستخدم
POST  api/update_status.php                   // تحديث حالة الاتصال
```

---

## 📊 ملاحظات مهمة

### استخدام المعاملات في الروابط:

| المعامل | الوصف | مثال |
|---------|-------|-------|
| `user_id` | ID المستخدم للدردشة معه | `?user_id=5` |
| `seller_id` | نفس user_id (اسم بديل) | `?seller_id=5` |
| `conversation_id` | ID المحادثة نفسها | `?conversation_id=10` |
| `id` | ID عام (للمستخدم أو الحساب) | `?id=5` |

### طرق الطلب:

- **GET:** لجلب البيانات (القراءة فقط)
- **POST:** لإرسال البيانات (الإضافة/التعديل/الحذف)

### المصادقة:

جميع الطلبات تحتاج:
```javascript
credentials: 'include'  // لإرسال session cookies
```

### CSRF Protection:

معظم طلبات POST تحتاج CSRF token:
```javascript
// 1. احصل على الـ token
fetch('api/get_csrf.php', { credentials: 'include' })
  .then(r => r.json())
  .then(data => {
    const token = data.csrf_token;
    
    // 2. استخدمه في الطلب
    formData.append('csrf_token', token);
  });
```

---

## 🎯 أمثلة سريعة

### مثال 1: إرسال رسالة نصية
```javascript
async function sendTextMessage(toUserId, messageText) {
  // 1. احصل على CSRF token
  const csrfRes = await fetch('api/get_csrf.php', { credentials: 'include' });
  const { csrf_token } = await csrfRes.json();
  
  // 2. جهز البيانات
  const formData = new FormData();
  formData.append('to', toUserId);
  formData.append('message', messageText);
  formData.append('csrf_token', csrf_token);
  
  // 3. أرسل الطلب
  const res = await fetch('api/send_message.php', {
    method: 'POST',
    body: formData,
    credentials: 'include'
  });
  
  return await res.json();
}

// الاستخدام
sendTextMessage(5, 'مرحباً!');
```

### مثال 2: جلب المحادثات
```javascript
async function getConversations() {
  const res = await fetch('api/get_conversations.php', {
    credentials: 'include'
  });
  
  const data = await res.json();
  
  if (data.success) {
    console.log('المحادثات:', data.conversations);
  }
}
```

### مثال 3: التحقق من تسجيل الدخول
```javascript
async function checkLogin() {
  const res = await fetch('api/check_login.php', {
    credentials: 'include'
  });
  
  const data = await res.json();
  
  if (data.logged_in) {
    console.log('مرحباً', data.name);
  } else {
    console.log('غير مسجل دخول');
  }
}
```

---

## 🚀 اختصارات مفيدة

```bash
# افتح phpMyAdmin
http://localhost/phpmyadmin/

# تحقق من خادم Apache
http://localhost/

# مجلد Uploads
http://localhost/api/uploads/

# ملف معين في uploads
http://localhost/api/uploads/filename.jpg
```

---

**آخر تحديث:** 10 أكتوبر 2025
**للمزيد:** راجع [README.md](README.md) و [CHANGELOG.md](CHANGELOG.md)
