// منطق تحديث حالة الاتصال لجميع صفحات الموقع
(function(){
    // التحقق من حالة تسجيل الدخول أولاً
    async function checkLoginStatus() {
        try {
            const response = await fetch('api/check_login.php', {
                method: 'GET',
                credentials: 'include'
            });
            const data = await response.json();
            return data.logged_in === true;
        } catch (error) {
            console.log('تعذر التحقق من حالة تسجيل الدخول:', error);
            return false;
        }
    }
    
    // دالة تحديث حالة الاتصال
    async function updateOnlineStatus(action) {
        const isLoggedIn = await checkLoginStatus();
        if (!isLoggedIn) {
            // المستخدم غير مسجل دخول، لا نحتاج لتحديث الحالة
            return;
        }
        
        try {
            await fetch('api/update_status.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}`
            });
        } catch (error) {
            // تجاهل الأخطاء بصمت لتجنب إزعاج المستخدم
            console.log('تعذر تحديث حالة الاتصال:', error);
        }
    }
    
    // أرسل حالة "متصل" عند تحميل الصفحة
    updateOnlineStatus('online');
    
    // أرسل حالة "غير متصل" عند إغلاق الصفحة أو إعادة تحميلها
    window.addEventListener('beforeunload', async function() {
        const isLoggedIn = await checkLoginStatus();
        if (isLoggedIn && navigator.sendBeacon) {
            navigator.sendBeacon('api/update_status.php', new URLSearchParams({action:'offline'}));
        }
    });
    
    // تحديث دوري كل 5 دقائق للمستخدمين المسجلين دخولهم
    setInterval(async () => {
        await updateOnlineStatus('online');
    }, 5 * 60 * 1000); // 5 دقائق
})();
