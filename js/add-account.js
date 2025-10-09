// Sidebar helpers are centralized in js/sidebar.js (openSidebar/closeSidebar/showPlaceholder)
    // Use those global functions instead of redefining them here.
    // Defensive fallback: if the global `showPlaceholder` helper was removed, provide a minimal one
    // so this file's calls don't throw console errors. This keeps UX messages visible even if
    // the sidebar helpers aren't loaded.
    if (typeof showPlaceholder !== 'function') {
        window.showPlaceholder = function(message, duration = 3000) {
            try {
                // create a simple toast element if not present
                let toast = document.getElementById('__simple_toast');
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = '__simple_toast';
                    toast.style.position = 'fixed';
                    toast.style.right = '16px';
                    toast.style.top = '16px';
                    toast.style.zIndex = 99999;
                    toast.style.maxWidth = '320px';
                    toast.style.fontFamily = 'Arial, sans-serif';
                    document.body.appendChild(toast);
                }
                const item = document.createElement('div');
                item.textContent = message || '';
                item.style.background = 'rgba(0,0,0,0.8)';
                item.style.color = 'white';
                item.style.padding = '10px 12px';
                item.style.marginTop = '8px';
                item.style.borderRadius = '6px';
                item.style.boxShadow = '0 2px 8px rgba(0,0,0,0.3)';
                item.style.fontSize = '13px';
                toast.appendChild(item);
                setTimeout(() => {
                    try { toast.removeChild(item); } catch(e){}
                    // remove wrapper when empty
                    if (toast.childElementCount === 0) {
                        try { toast.parentNode.removeChild(toast); } catch(e){}
                    }
                }, duration);
            } catch (e) {
                // silent fallback
                console.warn('showPlaceholder fallback error', e);
            }
        };
    }
    
    // التحقق من عدد الصور المرفوعة فقط
    const imgUploadEl = document.getElementById('image-upload');
    if (imgUploadEl) {
        imgUploadEl.addEventListener('change', function(event) {
        const fileInput = event.target;
        const count = fileInput.files.length;
        if (count > 30) {
            showPlaceholder('لقد تجاوزت الحد المسموح به (30 صورة). سيتم رفع أول 30 صورة فقط.');
        }
        });
    }


    // Handle form submission
    // جلب رمز CSRF عند تحميل الصفحة
    fetch('api/get_csrf.php', {credentials: 'include'})
        .then(res => res.json())
        .then(data => {
            if(data.csrf_token){
                document.getElementById('csrf_token').value = data.csrf_token;
            }
        });

    // عند تحميل الصفحة: إذا كان المستخدم مسجلاً الدخول ويوجد إعلان معلق في sessionStorage، استرجعه تلقائياً
    document.addEventListener('DOMContentLoaded', function() {
        function isLoggedIn() {
            return document.cookie.includes('PHPSESSID');
        }
        if (isLoggedIn()) {
            try {
                var draft = sessionStorage.getItem('addAccountDraft');
                if (draft) {
                    var data = JSON.parse(draft);
                    if (data) {
                        if (document.getElementById('game-name') && data.game_name) document.getElementById('game-name').value = data.game_name;
                        if (document.getElementById('description') && data.description) document.getElementById('description').value = data.description;
                        if (document.getElementById('price') && data.price) document.getElementById('price').value = data.price;
                        // إذا كان auto_publish_ad=1 في sessionStorage، نفذ الإرسال التلقائي
                        var autoPublish = false;
                        try {
                            autoPublish = sessionStorage.getItem('auto_publish_ad') === '1';
                            if(autoPublish) sessionStorage.removeItem('auto_publish_ad');
                        } catch(e) {}
                        if(autoPublish) {
                            setTimeout(function(){
                                var form = document.querySelector('form');
                                if(form) form.dispatchEvent(new Event('submit'));
                            }, 500);
                        }
                    }
                }
            } catch(e) {}
        }
    });

    const theForm = document.querySelector('form');
    if (theForm) {
        theForm.addEventListener('submit', function(event) {
            function isLoggedIn() {
                return document.cookie.includes('PHPSESSID');
            }
        if (!isLoggedIn()) {
                // حفظ بيانات النموذج والصور في sessionStorage و IndexedDB
                try {
                    var images = [];
                    var imageInput = document.getElementById('image-upload');
                    if (imageInput && imageInput.files && imageInput.files.length > 0) {
                        for (var i = 0; i < imageInput.files.length && i < 30; i++) {
                            var file = imageInput.files[i];
                            var key = 'addAccountImage_' + i;
                            images.push(key);
                            // حفظ الصورة في IndexedDB
                            if (typeof saveImageToIDB === 'function') {
                                saveImageToIDB(key, file);
                            }
                        }
                    }
                    sessionStorage.setItem('addAccountDraft', JSON.stringify({
                        game_name: document.getElementById('game-name').value,
                        description: document.getElementById('description').value,
                        price: document.getElementById('price').value,
                        images: images
                    }));
                } catch(e) {
                    // fallback: حفظ فقط النصوص إذا فشل حفظ الصور
                    try {
                        sessionStorage.setItem('addAccountDraft', JSON.stringify({
                            game_name: document.getElementById('game-name').value,
                            description: document.getElementById('description').value,
                            price: document.getElementById('price').value
                        }));
                    } catch(e) {}
                }
                event.preventDefault();
                showPlaceholder('يجـب تسجيل الدخول أولا لإنشاء إعلان. سيتم تحويلك لصفحة التسجيل.');
                setTimeout(function(){
            // redirect to login (not signup) so the user can authenticate
            // include the full current path (and query/hash) encoded as `return` so
            // the login page can redirect back safely after successful auth
            try {
                var currentPath = window.location.pathname + window.location.search + window.location.hash;
                // store the intended return target in sessionStorage so login/signup can restore it
                try { sessionStorage.setItem('post_auth_return', currentPath); } catch(e) { /* ignore */ }
                var returnParam = encodeURIComponent(currentPath);
                window.location.href = 'login.html?return=' + returnParam;
            } catch (e) {
                // fallback to a simple redirect if something goes wrong
                try { sessionStorage.setItem('post_auth_return', '/add-account.html'); } catch(e) {}
                window.location.href = 'login.html?return=%2Fadd-account.html';
            }
                }, 1200);
                return false;
            }
            // إذا كان مسجلاً الدخول، أكمل الإرسال
            event.preventDefault();
            const form = event.target;
            fetch('api/get_csrf.php', {credentials: 'include'})
                .then(res => res.json())
                .then(data => {
                    if(data.csrf_token){
                        document.getElementById('csrf_token').value = data.csrf_token;
                    }
                    const formData = new FormData(form);
                    fetch('api/add_account.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    })
                    .then(async res => {
                        // If server says the user is unauthorized, redirect to login
                        if (res.status === 401) {
                            // try to read JSON body for optional redirect URL
                            try {
                                const bodyText = await res.text();
                                const info = JSON.parse(bodyText);
                                if (info && info.redirect) {
                                    window.location.href = info.redirect;
                                    throw new Error('Redirecting to login');
                                }
                            } catch (e) {
                                // ignore parse errors and fallback to default
                            }
                            // fallback redirect
                            try {
                                var currentPath = window.location.pathname + window.location.search + window.location.hash;
                                sessionStorage.setItem('post_auth_return', currentPath);
                            } catch(e) {}
                            window.location.href = 'login.html?return=%2Fadd-account.html';
                            throw new Error('Redirecting to login');
                        }

                        const text = await res.text();
                        try {
                            const data = JSON.parse(text);
                            return data;
                        } catch (e) {
                            console.error('Invalid JSON from server:', text);
                            throw new Error(text || 'Invalid JSON from server');
                        }
                    })
                    .then(data => {
                        if(data.success){
                            // clear saved draft + images
                            try { sessionStorage.removeItem('addAccountDraft'); } catch(e){}
                            try { clearImagesFromIDB(); } catch(e){}
                            document.getElementById('success-modal').classList.remove('hidden');
                            document.body.style.overflow = 'hidden';
                            setTimeout(function(){
                                window.location.href = 'myaccount.html';
                            }, 1200);
                        } else {
                            showPlaceholder(data.error || 'فشل إضافة الحساب');
                        }
                    })
                    .catch(err => {
                        showPlaceholder(err.message || 'خطأ في الاتصال بالخادم');
                    });
                });
        });
    }
