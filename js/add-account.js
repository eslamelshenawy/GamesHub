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
        // التحقق من تسجيل الدخول باستخدام API بدلاً من فحص الكوكي
        fetch('api/check_login.php', { credentials: 'include' })
            .then(res => res.json())
            .then(loginData => {
                if (loginData.logged_in) {
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
            })
            .catch(err => {
                console.error('خطأ في التحقق من تسجيل الدخول:', err);
            });
    });

    const theForm = document.querySelector('form');
    if (theForm) {
        theForm.addEventListener('submit', function(event) {
            // أزل الفحص المحلي للكوكي - دع السيرفر يتحقق من تسجيل الدخول
            // إذا لم يكن مسجل دخول، سيرد السيرفر بـ 401 وسنتعامل معه
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
