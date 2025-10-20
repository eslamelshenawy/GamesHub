// نظام إدارة الجلسات - Session Manager
// يتم تحميله في جميع الصفحات لمراقبة انتهاء الجلسة تلقائياً

(function() {
    'use strict';

    // حفظ fetch الأصلية
    const originalFetch = window.fetch;

    // استبدال fetch بنسخة معدلة
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                // التحقق من حالة الاستجابة
                if (response.status === 401 || response.status === 403) {
                    // Session انتهت أو غير مصرح
                    console.warn('Session expired or unauthorized - Status:', response.status);

                    // التحقق من أن الصفحة ليست login أو signup أو home
                    const currentPath = window.location.pathname;
                    const publicPages = ['login.html', 'signup.html', 'index.html', 'forget-password.html', 'forget-pass.html'];
                    const isPublicPage = publicPages.some(page => currentPath.includes(page));

                    if (!isPublicPage) {
                        // عرض رسالة وإعادة التوجيه
                        handleSessionExpired();
                    }
                }

                return response;
            })
            .catch(error => {
                console.error('Fetch error:', error);
                throw error;
            });
    };

    // معالجة انتهاء الجلسة
    function handleSessionExpired() {
        // منع تكرار الرسالة
        if (window.sessionExpiredHandled) {
            return;
        }
        window.sessionExpiredHandled = true;

        // عرض رسالة منبثقة
        showSessionExpiredModal();

        // إعادة التوجيه بعد 2 ثانية
        setTimeout(() => {
            window.location.href = '/index.html';
        }, 2000);
    }

    // عرض رسالة انتهاء الجلسة
    function showSessionExpiredModal() {
        // التحقق من وجود الرسالة بالفعل
        if (document.getElementById('globalSessionExpiredModal')) {
            const modal = document.getElementById('globalSessionExpiredModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            return;
        }

        // إنشاء الرسالة
        const modalHTML = `
            <div id="globalSessionExpiredModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-[9999]" style="font-family: 'Tajawal', sans-serif;">
                <div class="bg-gray-800 rounded-lg p-8 max-w-md mx-4 border border-yellow-500 shadow-2xl animate-fadeIn">
                    <div class="text-center">
                        <div class="mb-4">
                            <svg class="w-16 h-16 text-yellow-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-yellow-500 mb-4">⏰ انتهت جلستك</h2>
                        <p class="text-gray-300 mb-6 leading-relaxed">
                            تم انتهاء جلسة تسجيل الدخول الخاصة بك.<br>
                            سيتم توجيهك إلى الصفحة الرئيسية...
                        </p>
                        <div class="flex justify-center">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-500"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // إضافة CSS للأنيميشن
        if (!document.getElementById('sessionManagerStyles')) {
            const style = document.createElement('style');
            style.id = 'sessionManagerStyles';
            style.textContent = `
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                        transform: scale(0.9);
                    }
                    to {
                        opacity: 1;
                        transform: scale(1);
                    }
                }
                .animate-fadeIn {
                    animation: fadeIn 0.3s ease-out;
                }
            `;
            document.head.appendChild(style);
        }
    }

    // Log للتأكد من تحميل السكريبت
    console.log('✅ Session Manager initialized');

})();
