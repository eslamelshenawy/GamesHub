// نشر الحساب تلقائياً بعد تسجيل الدخول إذا وجدت بيانات نشر معلقة
// يعتمد على image-idb.js
(async function(){
    // التحقق من تسجيل الدخول باستخدام API بدلاً من فحص الكوكي
    let loginData;
    try {
        const res = await fetch('/api/api/check_login.php', { credentials: 'include' });
        loginData = await res.json();
    } catch(e) {
        console.error('خطأ في التحقق من تسجيل الدخول:', e);
        return;
    }

    // انتظر حتى يتم تسجيل الدخول (في حال تم التوجيه بعد تسجيل الدخول)
    if (!loginData || !loginData.logged_in) return;
    // تحقق من وجود بيانات نشر معلقة
    if (!sessionStorage.getItem('pending_ad')) return;
    // تحميل مكتبة image-idb.js إذا لم تكن محملة
    if (typeof getImageFromIDB !== 'function') {
        await new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'js/image-idb.js';
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }
    const data = JSON.parse(sessionStorage.getItem('pending_ad'));
    // تجهيز الصور
    let files = [];
    if (data.images && Array.isArray(data.images) && data.images.length > 0) {
        let promises = [];
        for (let i = 0; i < data.images.length; i++) {
            promises.push(
                getImageFromIDB(data.images[i]).then(blob => {
                    if (blob) {
                        files.push(new File([blob], `image${i+1}.jpg`, {type: blob.type || 'image/jpeg'}));
                    }
                })
            );
        }
        await Promise.all(promises);
    }
    // تجهيز البيانات
    const formData = new FormData();
    formData.append('csrf_token', ''); // يمكن جلب رمز CSRF إذا لزم الأمر
    formData.append('game_name', data.game_name || '');
    formData.append('description', data.description || '');
    formData.append('price', data.price || '');
    files.forEach(f => formData.append('images[]', f));
    // إرسال الطلب
    fetch('/api/api/add_account.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
    })
    .then(async res => {
        let resp;
        try { resp = await res.json(); } catch { resp = {}; }
        if (resp && resp.success) {
            sessionStorage.removeItem('pending_ad');
            if (typeof clearImagesFromIDB === 'function') clearImagesFromIDB();
            // إظهار رسالة نجاح
            alert('تم نشر الحساب بنجاح!');
            // توجيه المستخدم مباشرة إلى صفحة حسابي
            window.location.href = 'myaccount.html';
        } else {
            alert(resp && resp.error ? resp.error : 'فشل نشر الحساب.');
        }
    })
    .catch(()=>{
        alert('تعذر الاتصال بالخادم. حاول لاحقًا.');
    });
})();
