// نظام التحقق من حالة الحظر
class BanChecker {
    constructor() {
        this.checkInterval = null;
        this.isChecking = false;
    }

    // التحقق من حالة المستخدم
    async checkUserStatus() {
        if (this.isChecking) return;
        this.isChecking = true;

        try {
            console.log('Making request to api/check_login.php');
            const response = await fetch('api/check_login.php', {
                method: 'GET',
                credentials: 'include'
            });

            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            if (response.ok) {
                const text = await response.text();
                console.log('Raw response text:', text);
                console.log('Response URL:', response.url);
                console.log('Response headers:', response.headers);
                
                if (text.startsWith('<?php')) {
                    console.log('User not logged in, skipping ban check');
                    return;
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                    console.log('Parsed data:', data);
                } catch (e) {
                    console.log('Failed to parse response as JSON, user likely not logged in');
                    return;
                }
                
                if (data.logged_in) {
                    // التحقق من تفاصيل المستخدم للتأكد من حالة الحظر
                    await this.checkBanStatus(data.user_id);
                }
            } else {
                console.log('Response not ok, status:', response.status);
            }
        } catch (error) {
            console.error('Error checking user status:', error);
        } finally {
            this.isChecking = false;
        }
    }

    // التحقق من حالة الحظر
    async checkBanStatus(userId) {
        try {
            const response = await fetch('api/check_user_ban.php', {
                method: 'GET',
                credentials: 'include'
            });

            if (response.ok) {
                const text = await response.text();
                
                if (text.startsWith('<?php')) {
                    console.log('User not logged in, skipping ban status check');
                    return;
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.log('Failed to parse ban status response as JSON');
                    return;
                }
                
                if (data.banned) {
                    this.showBanMessage();
                    this.logout();
                }
            }
        } catch (error) {
            console.error('Error checking ban status:', error);
        }
    }

    // عرض رسالة الحظر
    showBanMessage() {
        // إنشاء النافذة المنبثقة إذا لم تكن موجودة
        if (!document.getElementById('banModal')) {
            this.createBanModal();
        }

        const modal = document.getElementById('banModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // إنشاء النافذة المنبثقة
    createBanModal() {
        const modalHTML = `
            <div id="banModal" class="hidden fixed inset-0 bg-black bg-opacity-75 items-center justify-center z-50" style="font-family: 'Tajawal', sans-serif;">
                <div class="bg-gray-800 rounded-lg p-8 max-w-md mx-4 border border-red-500 shadow-2xl">
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="w-16 h-16 text-red-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-red-500 mb-4">تم حظر حسابك</h2>
                        <p class="text-gray-300 mb-6 leading-relaxed">
                            تم حظر حسابك من قبل الإدارة. لا يمكنك الوصول إلى الموقع حالياً.<br>
                            إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع الدعم الفني.
                        </p>
                        <button onclick="banChecker.redirectToLogin()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-colors">
                            العودة لتسجيل الدخول
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // تسجيل الخروج
    async logout() {
        try {
            await fetch('api/logout.php', {
                method: 'POST',
                credentials: 'include'
            });
        } catch (error) {
            console.error('Error logging out:', error);
        }
    }

    // إعادة التوجيه لصفحة تسجيل الدخول
    redirectToLogin() {
        window.location.href = '/login.html';
    }

    // بدء المراقبة الدورية
    startMonitoring() {
        // التحقق الأولي
        this.checkUserStatus();
        
        // التحقق كل 30 ثانية
        this.checkInterval = setInterval(() => {
            this.checkUserStatus();
        }, 30000);
    }

    // إيقاف المراقبة
    stopMonitoring() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }
}

// إنشاء مثيل عام
const banChecker = new BanChecker();

// بدء المراقبة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    // التحقق من أن الصفحة ليست صفحة تسجيل الدخول
    if (!window.location.pathname.includes('login.html')) {
        banChecker.startMonitoring();
    }
});

// إيقاف المراقبة عند مغادرة الصفحة
window.addEventListener('beforeunload', () => {
    banChecker.stopMonitoring();
});