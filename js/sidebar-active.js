/*
 * js/sidebar-active.js
 * المسؤولية: تحديد وإبراز الصفحة النشطة في القائمة الجانبية بناءً على URL الحالي
 * الاستخدام: يتم تضمينه تلقائياً مع sidebar.js
 */

(function() {
    'use strict';
    
    // دالة لتحديد الصفحة النشطة بناءً على URL الحالي
    function setActivePage() {
        // الحصول على اسم الملف الحالي من URL
        const currentPath = window.location.pathname;
        const currentPage = currentPath.split('/').pop() || 'index.html';
        
        // إزالة الكلاس النشط من جميع عناصر القائمة الجانبية
        const sidebarItems = document.querySelectorAll('.sidebar-item');
        sidebarItems.forEach(item => {
            item.classList.remove('active-link');
            const link = item.querySelector('a');
            if (link) {
                link.classList.remove('active-link');
            }
        });
        
        // تحديد الصفحة النشطة وإضافة الكلاس المناسب
        let activeItem = null;
        
        // البحث عن الرابط المطابق للصفحة الحالية
        sidebarItems.forEach(item => {
            const link = item.querySelector('a');
            if (link) {
                // تجاهل الروابط التي لها data-action (مثل تسجيل الخروج)
                const dataAction = link.getAttribute('data-action');
                if (dataAction) {
                    return; // تخطي هذا العنصر
                }
                
                const href = link.getAttribute('href');
                if (href && href !== '#') {
                    // استخراج اسم الملف من href
                    const linkPage = href.split('/').pop().split('?')[0].split('#')[0];
                    
                    // مقارنة اسم الملف
                    if (linkPage === currentPage || 
                        (currentPage === '' && linkPage === 'index.html') ||
                        (currentPage === 'index.html' && linkPage === '') ||
                        (currentPage.includes('index') && linkPage.includes('index'))) {
                        activeItem = item;
                    }
                }
            }
        });
        
        // إضافة الكلاس النشط للعنصر المطابق
        if (activeItem) {
            activeItem.classList.add('active-link');
            const link = activeItem.querySelector('a');
            if (link) {
                link.classList.add('active-link');
            }
        } else {
            // إذا لم يتم العثور على مطابقة، تفعيل الصفحة الرئيسية كافتراضي
            const homeItem = document.querySelector('.sidebar-item a[href*="index.html"]:not([data-action]), .sidebar-item a[href="/"]:not([data-action]), .sidebar-item a[href=""]:not([data-action])');
            if (homeItem) {
                const parentItem = homeItem.closest('.sidebar-item');
                if (parentItem) {
                    parentItem.classList.add('active-link');
                    homeItem.classList.add('active-link');
                }
            }
        }
    }
    
    // دالة لمراقبة تغييرات DOM وتطبيق الحالة النشطة
    function initializeActivePageDetection() {
        // تطبيق الحالة النشطة عند تحميل الصفحة
        setActivePage();
        
        // مراقبة تغييرات DOM للقائمة الجانبية
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // التحقق من إضافة عناصر جديدة للقائمة الجانبية
                    const addedNodes = Array.from(mutation.addedNodes);
                    const hasSidebarContent = addedNodes.some(node => 
                        node.nodeType === Node.ELEMENT_NODE && 
                        (node.id === 'sidebar' || node.querySelector && node.querySelector('#sidebar'))
                    );
                    
                    if (hasSidebarContent) {
                        // تأخير قصير للتأكد من اكتمال تحميل المحتوى
                        setTimeout(setActivePage, 100);
                    }
                }
            });
        });
        
        // بدء مراقبة التغييرات في body
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // إعادة تطبيق الحالة النشطة عند تغيير الصفحة (للتطبيقات أحادية الصفحة)
        window.addEventListener('popstate', setActivePage);
        
        // إعادة تطبيق الحالة النشطة عند تحميل محتوى جديد
        document.addEventListener('DOMContentLoaded', setActivePage);
    }
    
    // تصدير الدوال للاستخدام العام
    window.setActivePage = setActivePage;
    window.initializeActivePageDetection = initializeActivePageDetection;
    
    // تشغيل تلقائي عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeActivePageDetection);
    } else {
        initializeActivePageDetection();
    }
    
})();