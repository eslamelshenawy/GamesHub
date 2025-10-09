<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    // حفظ الصفحة المطلوبة للعودة إليها بعد تسجيل الدخول
    $return_url = $_SERVER['REQUEST_URI'];
    header("Location: login.html?return=" . urlencode($return_url));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>الرسائل — GamesHub</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'neon-blue': '#00d4ff',
                        'neon-purple': '#9b59ff', 
                        'neon-green': '#32ff7e',
                        'neon-pink': '#ff00ff',
                        'bg-dark': '#0b0f14',
                        'card-dark': 'rgba(255, 255, 255, 0.03)',
                        'text-dark': '#ffffff',
                        'muted-text': '#cbd5e1',
                        'border-light': 'rgba(255, 255, 255, 0.06)'
                    },
                    fontFamily: {
                        'tajawal': ['Tajawal', 'sans-serif']
                    },
                    animation: {
                        'gradient': 'gradient 8s ease infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-glow': 'pulse-glow 2s ease-in-out infinite alternate'
                    }
                }
            }
        }
    </script>
<script src="js/online-status.js"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <link rel="stylesheet" href="css/massages.css">
   
    <style>
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        @keyframes pulse-glow {
            from { box-shadow: 0 0 20px rgba(0, 212, 255, 0.3); }
            to { box-shadow: 0 0 30px rgba(155, 89, 255, 0.5); }
        }
        .bg-animated {
            background: linear-gradient(-45deg, #0b0f14, #1a1f2e, #0f1419, #1e293b);
            background-size: 400% 400%;
            animation: gradient 8s ease infinite;
        }
    </style>
</head>
<body class="font-tajawal bg-animated text-white min-h-screen">

<div id="toast" class="toast">ميزة قريبا! ✨</div>

<div id="overlay" class="fixed inset-0 bg-black bg-opacity-60 hidden z-40" onclick="closeSidebar()"></div>



<div id="sidebar-container">
    <div class="sidebar-container fixed inset-y-0 right-0 w-64 bg-black/60 backdrop-blur-xl border-l border-white/10 transform translate-x-full md:translate-x-0 transition-transform duration-300 z-50 shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-xl font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent">المحادثات</h2>
                <button class="md:hidden p-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200" onclick="closeSidebar()">
                    <i class="fa fa-times text-neon-blue"></i>
                </button>
            </div>
            
            <div class="space-y-3">
                <div class="conversation-item p-4 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 hover:border-neon-blue/30 transition-all duration-200 group" onclick="openChat('user1')">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-neon-purple to-neon-blue flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform duration-200">
                            <span class="text-sm font-bold text-black">أ</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-sm text-white group-hover:text-neon-blue transition-colors duration-200">أحمد محمد</h3>
                            <p class="text-xs text-white/60">مرحبا، هل الحساب متاح؟</p>
                        </div>
                        <div class="text-xs text-white/40">الآن</div>
                    </div>
                </div>
                
                <div class="conversation-item p-4 rounded-xl bg-white/5 border border-white/10 cursor-pointer hover:bg-white/10 hover:border-neon-blue/30 transition-all duration-200 group" onclick="openChat('user2')">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-neon-green to-neon-blue flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform duration-200">
                            <span class="text-sm font-bold text-black">س</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-sm text-white group-hover:text-neon-blue transition-colors duration-200">سارة أحمد</h3>
                            <p class="text-xs text-white/60">شكراً لك</p>
                        </div>
                        <div class="text-xs text-white/40">5 د</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- sidebar يتم تحميله ديناميكياً من js/sidebar.js -->

<div class="min-h-screen md:mr-64 relative z-10">

    <header class="sticky top-0 z-30 backdrop-blur-md bg-black/20 border-b border-white/10 p-4 flex items-center justify-between md:hidden">
        <div class="flex items-center gap-3">
            <button class="p-3 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200" onclick="openSidebar()">
                <i class="fa fa-bars text-lg text-neon-blue"></i>
            </button>
            <a href="index.html" class="text-xl font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent">
                GamesHub
            </a>
        </div>
        <div class="flex items-center gap-3">
            <a href="login.html" class="px-4 py-2 rounded-xl bg-gradient-to-r from-neon-purple/20 to-neon-blue/20 border border-neon-blue/30 text-white hover:from-neon-purple/30 hover:to-neon-blue/30 transition-all duration-200 text-sm font-medium" onclick="try{sessionStorage.setItem('post_auth_return', window.location.pathname+window.location.search+window.location.hash);}catch(e){}">
                <i class="fa fa-sign-in-alt ml-1"></i>
                تسجيل دخول
            </a>
        </div>
    </header>

    <header class="hidden md:flex items-center justify-between px-8 py-6 sticky top-0 z-30 backdrop-blur-md bg-black/20 border-b border-white/10">
        <div class="flex items-center gap-6">
            <a href="index.html" class="text-2xl font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent hover:scale-105 transition-transform duration-200">
                GamesHub
            </a>
    
        </div>
        <div class="flex items-center gap-4">
            <a href="add-account.html" class="px-6 py-3 rounded-xl bg-gradient-to-r from-neon-purple to-neon-blue text-black font-bold hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-neon-blue/25">
                <i class="fa fa-plus ml-1"></i>
                أضف حسابك
            </a>
            <a href="login.html" class="px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white hover:bg-white/10 hover:border-neon-blue/30 transition-all duration-200 font-medium" onclick="try{sessionStorage.setItem('post_auth_return', window.location.pathname+window.location.search+window.location.hash);}catch(e){}">
                <i class="fa fa-sign-in-alt ml-1"></i>
                تسجيل دخول
            </a>
        </div>
    </header>

    <div class="p-4 md:p-8">
        <h1 class="text-2xl md:text-3xl font-bold mb-6 hidden md:block">الرسائل</h1>
        
        <div class="card-bg rounded-xl p-0 shadow-lg min-h-[500px] md:min-h-[600px] flex flex-col md:flex-row messages-layout">
            
            <div id="conversation-list" class="w-full md:w-80 lg:w-96 border-b md:border-b-0 md:border-r border-white/10 flex-shrink-0 chat-list overflow-y-auto max-h-[400px] md:max-h-none">
                <div class="p-3 md:p-4 border-b border-white/10 bg-black/20">
                    <h2 class="text-lg md:text-xl font-bold text-white mb-2">المحادثات</h2>
                    <div class="relative">
                        <input type="text" placeholder="ابحث في المحادثات..." class="w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/60 focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200">
                        <i class="fa fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-white/40 text-sm"></i>
                    </div>
                </div>
                <div id="no-conversations" class="p-6 md:p-8 text-center hidden">
                    <i class="fa-solid fa-inbox text-4xl md:text-5xl mb-4 text-white/40"></i>
                    <h3 class="text-lg md:text-xl font-bold mb-2 text-white">لا يوجد لديك محادثات</h3>
                    <p class="text-sm md:text-base text-white/60 max-w-sm mx-auto">عندما تتواصل مع بائع، ستظهر محادثتك هنا.</p>
                </div>
            </div>
            
            <div id="chat-window" class="w-full flex-1 flex flex-col p-0 md:flex hidden">
                <div class="border-b border-white/10 flex-shrink-0 bg-black/20 backdrop-blur-md rounded-t-xl">
                    <!-- الصف الأول: معلومات المستخدم والأزرار -->
                    <div class="flex items-center justify-between px-4 md:px-6 py-3 ">
                        <div class="flex items-center gap-3 md:gap-4 flex-1 min-w-0">
                            <button id="chat-back-btn" onclick="closeChat()" class="p-2 md:p-3 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200 md:hidden flex-shrink-0">
                                <i class="fa fa-arrow-right text-neon-blue text-sm"></i>
                            </button>
                            <a href="user-profile.html" class="flex items-center gap-3 md:gap-4 flex-1 min-w-0">
                                <img id="chat-user-avatar" src="uploads/default-avatar.svg" class="w-12 h-12 md:w-14 md:h-14 rounded-full object-cover border-2 border-neon-blue shadow-lg flex-shrink-0">
                                <div class="min-w-0 flex-1">
                                    <h3 id="chat-username" class="font-bold text-base md:text-lg text-white truncate leading-tight mb-1"></h3>
                                    <div class="flex items-center gap-2">
                                        <p id="chat-user-status" class="text-xs md:text-sm text-neon-green flex items-center gap-1">
                                            <i class="fa fa-circle text-xs"></i>
                                            <span class="hidden md:inline">متصل الآن</span>
                                        </p>
                                        <p id="chat-user-typing" class="text-xs md:text-sm text-neon-green flex items-center gap-1" style="display:none">
                                            <i class="fa fa-circle text-xs animate-pulse"></i>
                                            <span class="hidden sm:inline">يكتب الآن...</span>
                                            <span class="sm:hidden">يكتب...</span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="flex items-center gap-2 md:gap-3 flex-shrink-0">
                            <button id="start-deal-btn" class="px-3 md:px-4 py-2 md:py-2.5 rounded-lg md:rounded-xl bg-gradient-to-r from-neon-purple to-neon-blue text-black font-semibold hover:scale-105 transition-all duration-200 shadow-lg text-xs md:text-sm whitespace-nowrap">
                                <i class="fa-solid fa-handshake ml-1 hidden md:inline"></i>
                                <span class="hidden sm:inline">بدء صفقة آمنة</span>
                                <span class="sm:hidden">صفقة</span>
                            </button>
                            <button id="send-money-btn" class="p-2 md:p-2.5 rounded-lg md:rounded-xl bg-white/5 border border-white/10 text-white hover:bg-white/10 hover:border-neon-blue/30 transition-all duration-200">
                                <i class="fa-solid fa-money-bill-transfer text-neon-blue text-sm"></i>
                            </button>
                            <button id="report-conversation-btn" class="p-2 md:p-2.5 rounded-lg md:rounded-xl bg-white/5 border border-white/10 text-white hover:bg-white/10 hover:border-red-400/30 transition-all duration-200" title="الإبلاغ عن المحادثة">
                                <i class="fa-solid fa-flag text-red-400 text-sm"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- الصف الثاني: معلومات الحساب المرتبط -->
                    <div id="linked-account-info" class="px-4 md:px-6 pb-3 hidden pt-3">
                        <div class="bg-gradient-to-r from-white/5 to-white/10 rounded-lg border border-white/20 backdrop-blur-sm p-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fa fa-gamepad text-neon-blue text-sm"></i>
                                    <span class="text-sm text-white/90 font-medium">الحساب المرتبط:</span>
                                    <span id="linked-account-title" class="text-sm font-semibold text-neon-blue"></span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <i class="fa fa-tag text-neon-green text-sm"></i>
                                    <span id="linked-account-price" class="text-sm text-neon-green font-bold"></span>
                                    <span class="text-sm text-white/60">ج.م</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="messages-container" class="flex-1 chat-container px-2 md:px-4 py-2 md:py-4 bg-gradient-to-b from-transparent to-black/10 overflow-y-auto"></div>
                <div id="deal-status-section" class="flex-shrink-0 mt-auto flex flex-col items-center gap-2 md:gap-3 border-t pt-2 md:pt-4 border-white/10 bg-white/5 px-2 md:px-4 hidden">
                  <div id="deal-status-card" class="w-full bg-gradient-to-r from-blue-500/20 to-green-500/20 rounded-lg p-2 md:p-3 border border-blue-400/30">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-xs md:text-sm font-medium text-blue-300">حالة الصفقة</span>
                    </div>
                    <div id="deal-info-container"></div>
                    <p id="transaction-status" class="text-xs md:text-sm text-center text-white/90 mb-2"></p>
                    <div id="deal-action-buttons" class="flex gap-1 md:gap-2 justify-center flex-wrap"></div>
                  </div>
                </div>
                <div class="flex-shrink-0 mt-auto border-t border-white/10 bg-black/20 backdrop-blur-md rounded-b-xl" style="min-height:50px;">
                    <div class="p-2 md:p-4">
                        <div class="flex items-center gap-1 md:gap-3">
                            <form id="message-form" class="flex-1 flex items-center gap-1 md:gap-3" onsubmit="sendMessage(event)" enctype="multipart/form-data">
                                <input id="file-input" name="file" type="file" class="hidden" accept="image/*,video/*,audio/*,.pdf,.doc,.docx" />
                                <button id="attach-btn" type="button" class="p-2 md:p-3 rounded-lg md:rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 hover:border-neon-blue/30 transition-all duration-200">
                                    <i class="fa-solid fa-paperclip text-neon-blue text-sm"></i>
                                </button>
                                <div class="flex-1 relative">
                                    <input id="message-input" name="message" type="text" placeholder="اكتب رسالتك..." class="w-full bg-white/5 border border-white/10 rounded-lg md:rounded-xl px-3 md:px-4 py-2 md:py-3 outline-none focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200 text-white placeholder-white/60 text-sm md:text-base" autocomplete="off">
                                </div>
                                <button id="send-message-btn" type="submit" class="px-3 md:px-4 py-2 md:py-3 rounded-lg md:rounded-xl bg-gradient-to-r from-neon-purple to-neon-blue text-black font-semibold hover:scale-105 transition-all duration-200 shadow-lg">
                                    <i class="fa fa-paper-plane text-sm"></i>
                                </button>
                            </form>
                        </div>
                        <div id="file-preview" class="hidden mt-2 md:mt-3 p-2 md:p-3 bg-white/5 rounded-lg md:rounded-xl border border-white/10">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 md:gap-3">
                                    <div id="preview-content"></div>
                                    <div class="min-w-0 flex-1">
                                        <p id="file-name" class="text-xs md:text-sm font-medium text-white truncate"></p>
                                        <p id="file-size" class="text-xs text-white/60"></p>
                                    </div>
                                </div>
                                <button onclick="cancelFile()" class="p-1.5 md:p-2 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 hover:bg-red-500/30 transition-all duration-200 flex-shrink-0">
                                    <i class="fa fa-times text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
<script>
// معاينة الملف قبل الإرسال (صورة أو فيديو أو اسم الملف) مع زر إلغاء
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('file-input');
    var preview = document.getElementById('file-preview');
    var previewContent = document.getElementById('preview-content');
    var fileName = document.getElementById('file-name');
    var fileSize = document.getElementById('file-size');

    if (fileInput && preview) {
        fileInput.addEventListener('change', function() {
            if (!fileInput.files || !fileInput.files[0]) {
                preview.classList.add('hidden');
                if (previewContent) previewContent.innerHTML = '';
                if (fileName) fileName.textContent = '';
                if (fileSize) fileSize.textContent = '';
                return;
            }

            var file = fileInput.files[0];
            var ext = file.name.split('.').pop().toLowerCase();
            var fileSizeKB = (file.size / 1024).toFixed(2);

            // إظهار الـ preview
            preview.classList.remove('hidden');

            // تحديث اسم الملف وحجمه
            if (fileName) fileName.textContent = file.name;
            if (fileSize) fileSize.textContent = fileSizeKB + ' KB';

            // عرض المحتوى
            if (previewContent) {
                previewContent.innerHTML = '';
                if (["jpg","jpeg","png","gif","webp","bmp"].includes(ext)) {
                    var img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.style.maxWidth = '60px';
                    img.style.maxHeight = '60px';
                    img.className = 'rounded-lg border border-white/10 object-cover';
                    previewContent.appendChild(img);
                } else if (["mp4","webm","mov"].includes(ext)) {
                    var videoIcon = document.createElement('div');
                    videoIcon.innerHTML = '<i class="fa-solid fa-video text-3xl text-neon-blue"></i>';
                    previewContent.appendChild(videoIcon);
                } else {
                    var fileIcon = document.createElement('div');
                    fileIcon.innerHTML = '<i class="fa-solid fa-file text-3xl text-white/60"></i>';
                    previewContent.appendChild(fileIcon);
                }
            }
        });
    }
});

// دالة لإلغاء اختيار الملف
function cancelFile() {
    var fileInput = document.getElementById('file-input');
    var preview = document.getElementById('file-preview');
    var previewContent = document.getElementById('preview-content');
    var fileName = document.getElementById('file-name');
    var fileSize = document.getElementById('file-size');

    if (fileInput) fileInput.value = '';
    if (preview) preview.classList.add('hidden');
    if (previewContent) previewContent.innerHTML = '';
    if (fileName) fileName.textContent = '';
    if (fileSize) fileSize.textContent = '';
}
</script>

                    </form>
                </div>
            </div>
            
        </div>
    </div>

    <footer class="p-3 md:p-8 border-t border-white/6 mt-6">
        <div class="max-w-6xl mx-auto flex flex-col md:flex-row md:justify-between gap-3 md:gap-4 mobile-hide">
            <div>
                <h4 class="font-bold text-sm md:text-base">GamesHub</h4>
                <p class="muted text-xs md:text-sm">منصة وسيط لبيع وشراء حسابات الألعاب — آمنة وسهلة</p>
            </div>
            <div class="flex gap-2 md:gap-3 items-center flex-wrap">
                <a href="#" class="muted text-xs md:text-sm">سياسة الخصوصية</a>
                <a href="#" class="muted text-xs md:text-sm">شروط الاستخدام</a>
                <a href="#" class="muted text-xs md:text-sm">اتصل بنا</a>
            </div>
        </div>
    </footer>

</div>

<script>
var PHP_BUYER_ID = "<?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : ''; ?>";
</script>
<script src="js/massage.js"></script>
<script>
// تفعيل زر إرفاق الملفات
document.addEventListener('DOMContentLoaded', function() {
    var attachBtn = document.getElementById('attach-btn');
    var fileInput = document.getElementById('file-input');
    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function(e) {
            fileInput.click();
        });
    }
    // تحديث رابط الملف الشخصي ليضيف معرف المستخدم النشط
    function updateUserProfileLink(userId) {
        var profileLink = document.querySelector('#chat-window a[href^="user-profile.html"]');
        if (profileLink && userId) {
            profileLink.href = 'user-profile.html?id=' + encodeURIComponent(userId);
        }
    }
    // إذا كان معرف المستخدم النشط متوفر عند تحميل الصفحة
    if (window.ACTIVE_CHAT_USER) {
        updateUserProfileLink(window.ACTIVE_CHAT_USER);
    }
    // إذا كان يتم تغيير المستخدم النشط عند فتح المحادثة، يمكنك استدعاء updateUserProfileLink(userId) من js/massage.js
});
</script>
<!-- Modal لعرض الصور -->
<div id="image-modal" style="display:none; position:fixed;z-index:9999;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;">
    <span id="close-image-modal" style="position:absolute;top:24px;right:32px;font-size:2.5rem;color:#fff;cursor:pointer;z-index:10001">&times;</span>
    <img id="modal-img" src="" alt="صورة مكبرة" style="max-width:90vw;max-height:80vh;border-radius:1rem;box-shadow:0 8px 32px #000;z-index:10000">
</div>
<script>
// دعم عرض الصور في نافذة منبثقة
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('image-modal');
    var modalImg = document.getElementById('modal-img');
    var closeBtn = document.getElementById('close-image-modal');
    // تفويض الحدث على الرسائل
    document.body.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG' && e.target.classList.contains('chat-image-popup')) {
            modalImg.src = e.target.src;
            modal.style.display = 'flex';
        }
    });
    if (closeBtn) closeBtn.onclick = function() { modal.style.display = 'none'; modalImg.src = ''; };
    if (modal) modal.onclick = function(e) { if (e.target === modal) { modal.style.display = 'none'; modalImg.src = ''; } };
});
</script>
<script src="js/sidebar.js"></script>
<script>
// عند تحميل الصفحة، تظهر قائمة المحادثات فقط
document.addEventListener('DOMContentLoaded', function() {
    const chatWindow = document.getElementById('chat-window');
    const conversationList = document.getElementById('conversation-list');
    if (chatWindow) chatWindow.classList.add('hidden');
    if (conversationList) conversationList.classList.remove('hidden');

    // زر العودة من نافذة الدردشة
    const backBtn = document.getElementById('chat-back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            if (chatWindow) chatWindow.classList.add('hidden');
            if (conversationList) conversationList.classList.remove('hidden');
        });
    }

    // تعديل دالة فتح المحادثة في js/massage.js لتقوم بما يلي:
    // عند الضغط على بطاقة محادثة:
    // - إخفاء قائمة المحادثات
    // - إظهار نافذة الدردشة
    // (تم ذلك بالفعل في massage.js)
});
</script>

<script>
// تعيين رقم المعاملة عند فتح نافذة الدردشة
function setTransactionId(transactionId) {
    const confirmButton = document.getElementById('confirm-receipt-btn');
    if (confirmButton) {
        confirmButton.dataset.transactionId = transactionId;
    }
}

// مثال: تعيين رقم معاملة عند فتح محادثة معينة
// استبدل هذا الجزء بالمنطق الخاص بك لجلب رقم المعاملة الصحيح
const exampleTransactionId = 12345; // رقم معاملة تجريبي
setTransactionId(exampleTransactionId);
</script>
<!-- Modal لبدء الصفقة الآمنة -->
<div id="start-deal-modal" class="fixed inset-0 bg-black/70 backdrop-blur-md flex items-center justify-center hidden z-50 p-2">
    <div class="bg-black/90 backdrop-blur-xl border border-white/20 rounded-xl md:rounded-3xl shadow-2xl p-4 md:p-8 w-full max-w-md mx-2 md:mx-4">
        <div class="flex items-center justify-between mb-4 md:mb-8">
            <h2 class="text-lg md:text-2xl font-bold bg-gradient-to-r from-neon-blue to-neon-purple bg-clip-text text-transparent">بدء صفقة آمنة</h2>
            <button onclick="document.getElementById('start-deal-modal').classList.add('hidden')" class="p-2 md:p-3 rounded-lg md:rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                <i class="fa fa-times text-neon-blue text-sm"></i>
            </button>
        </div>
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-gradient-to-r from-neon-purple to-neon-blue rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                <i class="fa-solid fa-shield-halved text-2xl text-black"></i>
            </div>
            <p class="text-white/80 text-sm">سيتم حجز المبلغ حتى تأكيد الاستلام</p>
        </div>
        
        <form id="start-deal-form">
            <div class="mb-6">
                <label for="deal-account-select" class="block text-sm font-medium text-white mb-3">
                    <i class="fa-solid fa-gamepad ml-1 text-neon-blue"></i> اختر الحساب
                </label>
                <select id="deal-account-select" name="account-id"class="w-full p-4 rounded-xl bg-gray-900 border border-white/10 text-white focus:border-neon-blue/50 focus:bg-gray-800 transition-all duration-200"
>
                    <option value="">جاري تحميل الحسابات...</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label for="deal-amount" class="block text-sm font-medium text-white mb-3">
                    <i class="fa-solid fa-coins ml-1 text-neon-green"></i> المبلغ (ج.م)
                </label>
                <input type="number" id="deal-amount" name="amount" 
                       class="w-full p-4 rounded-xl bg-white/5 border border-white/10 text-white placeholder-white/60 focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200" 
                       placeholder="سيتم تعبئته تلقائياً" readonly>
            </div>
            
            <div class="bg-gradient-to-r from-neon-blue/10 to-neon-purple/10 border border-neon-blue/20 rounded-xl p-5 mb-6">
                <h3 class="text-white font-semibold mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-info-circle text-neon-blue"></i> كيف تعمل الصفقة الآمنة؟
                </h3>
                <ul class="text-white/80 text-sm space-y-2">
                    <li class="flex items-start gap-2"><span class="text-neon-green">•</span> سيتم سحب المبلغ من رصيدك وحجزه</li>
                    <li class="flex items-start gap-2"><span class="text-neon-green">•</span> البائع سيقوم بتسليم الحساب</li>
                    <li class="flex items-start gap-2"><span class="text-neon-green">•</span> بعد التأكد، اضغط "تأكيد الاستلام"</li>
                    <li class="flex items-start gap-2"><span class="text-neon-green">•</span> الإدارة ستراجع وتحول المبلغ للبائع</li>
                </ul>
            </div>
            
            <input type="hidden" id="deal-seller-id" name="seller-id" value="">
            <div class="flex gap-4">
                <button type="button" id="cancel-deal" class="flex-1 px-6 py-4 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 hover:border-red-500/30 transition-all duration-200 font-semibold">
                    <i class="fa-solid fa-times ml-1"></i> إلغاء
                </button>
                <button type="submit" class="flex-1 px-6 py-4 bg-gradient-to-r from-neon-purple to-neon-blue text-black rounded-xl hover:scale-105 transition-all duration-200 font-bold shadow-lg">
                    <i class="fa-solid fa-handshake ml-1"></i> بدء الصفقة
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal لإرسال الأموال -->
<div id="send-money-modal" class="fixed inset-0 bg-black/70 backdrop-blur-md flex items-center justify-center hidden z-50 p-2">
    <div class="bg-black/90 backdrop-blur-xl border border-white/20 rounded-xl md:rounded-3xl shadow-2xl p-4 md:p-8 w-full max-w-md mx-2 md:mx-4">
        <div class="flex items-center justify-between mb-4 md:mb-8">
            <h2 class="text-lg md:text-2xl font-bold bg-gradient-to-r from-neon-green to-neon-blue bg-clip-text text-transparent">إرسال الأموال</h2>
            <button onclick="document.getElementById('send-money-modal').classList.add('hidden')" class="p-2 md:p-3 rounded-lg md:rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                <i class="fa fa-times text-neon-blue text-sm"></i>
            </button>
        </div>
        <form id="send-money-form">
            <div class="mb-6">
                <label for="payment-method" class="block text-sm font-medium text-white mb-3">
                    <i class="fa-solid fa-credit-card ml-1 text-neon-green"></i> طريقة الدفع
                </label>
                <select id="payment-method" name="payment-method" class="w-full p-4 rounded-xl bg-white/5 border border-white/10 text-white focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200">
                    <option value="istapay">IstaPay</option>
                    <option value="vodafone_cash">فودافون كاش</option>
                </select>
            </div>
            <div class="mb-6">
                <label for="amount" class="block text-sm font-medium text-white mb-3">
                    <i class="fa-solid fa-coins ml-1 text-neon-blue"></i> المبلغ
                </label>
                <input type="number" id="amount" name="amount" class="w-full p-4 rounded-xl bg-white/5 border border-white/10 text-white placeholder-white/60 focus:border-neon-blue/50 focus:bg-white/10 transition-all duration-200" placeholder="أدخل المبلغ" required>
            </div>
            <input type="hidden" id="seller-id" name="seller-id" value="">
            <div class="flex gap-4">
                <button type="button" id="cancel-send-money" class="flex-1 px-6 py-4 bg-white/5 border border-white/10 text-white rounded-xl hover:bg-white/10 hover:border-red-500/30 transition-all duration-200 font-semibold">إلغاء</button>
                <button type="submit" class="flex-1 px-6 py-4 bg-gradient-to-r from-neon-green to-neon-blue text-black rounded-xl hover:scale-105 transition-all duration-200 font-bold shadow-lg">إرسال</button>
            </div>
        </form>
    </div>
</div>
<script>
// دوال مساعدة للصفقة الآمنة
function getCurrentSellerId() {
    return window.ACTIVE_CHAT_USER || null;
}

function loadSellerAccounts(sellerId) {
    const select = document.getElementById('deal-account-select');
    if (!select) return;
    
    select.innerHTML = '<option value="">جاري تحميل الحسابات...</option>';
    
    fetch(`get_seller_accounts.php?seller_id=${sellerId}`)
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">اختر حساب...</option>';
            if (data.success && data.accounts) {
                data.accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    option.textContent = `${account.game_name} - ${account.title}`;
                    option.dataset.price = account.price;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">لا توجد حسابات متاحة</option>';
            }
        })
        .catch(error => {
            console.error('Error loading accounts:', error);
            select.innerHTML = '<option value="">خطأ في تحميل الحسابات</option>';
        });
}

function startSecureDeal() {
     const formData = new FormData(document.getElementById('start-deal-form'));
     const urlParams = new URLSearchParams(window.location.search);
     const conversationId = urlParams.get('conversation_id') || window.CONVERSATION_ID;
     const linkedAccount = window.LINKED_ACCOUNT;
     
     const dealData = {
         account_id: formData.get('account-id'),
         amount: formData.get('amount'),
         seller_id: formData.get('seller-id')
     };
     
     // إضافة conversation_id إذا كان متوفراً
     if (conversationId) {
         dealData.conversation_id = conversationId;
     }
     
     // استخدام account_id من الحساب المرتبط إذا لم يتم تحديده في النموذج
     if (!dealData.account_id && linkedAccount && linkedAccount.id) {
         dealData.account_id = linkedAccount.id;
     }
     
     if (!dealData.account_id || !dealData.amount || !dealData.seller_id) {
         alert('يرجى ملء جميع الحقول المطلوبة.');
         return;
     }
     
     console.log('Starting secure deal with data:', dealData);
     
     fetch('start_secure_deal.php', {
         method: 'POST',
         headers: {
             'Content-Type': 'application/json'
         },
         body: JSON.stringify(dealData)
     })
     .then(response => response.json())
     .then(data => {
         if (data.success) {
             alert('تم بدء الصفقة الآمنة بنجاح!');
             document.getElementById('start-deal-modal').classList.add('hidden');
             // تحديث واجهة المستخدم لإظهار حالة الصفقة
             updateDealUI(data.deal);
             document.getElementById('deal-status-section').classList.remove('hidden');
             // إعادة تحميل المحادثة لإظهار رسالة الصفقة
             if (typeof loadMessages === 'function') {
                 loadMessages();
             }
         } else {
             alert(data.error || 'حدث خطأ أثناء بدء الصفقة.');
         }
     })
     .catch(error => {
         console.error('Error starting deal:', error);
         alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
     });
 }
 
 function updateDealUI(deal) {
     if (!deal) return;
     
     const dealId = document.getElementById('deal-id');
     const transactionStatus = document.getElementById('transaction-status');
     const dealActionButtons = document.getElementById('deal-action-buttons');
     
     if (dealId) dealId.textContent = `#${deal.id}`;
     
     let statusText = '';
     let buttonsHTML = '';
     
     switch (deal.status) {
         case 'CREATED':
             statusText = `⏳ في انتظار تسليم الحساب من ${deal.seller_name || 'البائع'}`;
             if (window.CURRENT_USER_ID == deal.buyer_id) {
                 buttonsHTML = `
                     <button onclick="confirmDelivery(${deal.id})" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                         <i class="fa-solid fa-check ml-1"></i> تأكيد الاستلام
                     </button>
                 `;
             }
             break;
         case 'PENDING':
             statusText = `⏳ في انتظار تسليم الحساب من ${deal.seller_name || 'البائع'}`;
             if (window.CURRENT_USER_ID == deal.buyer_id) {
                 buttonsHTML = `
                     <button onclick="confirmDelivery(${deal.id})" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                         <i class="fa-solid fa-check ml-1"></i> تأكيد الاستلام
                     </button>
                 `;
             }
             break;
         case 'DELIVERED':
             statusText = '📋 تم تأكيد الاستلام - في انتظار مراجعة الإدارة';
             break;
         case 'COMPLETED':
             statusText = '✅ تمت الصفقة بنجاح';
             break;
         case 'CANCELLED':
             statusText = '❌ تم إلغاء الصفقة';
             break;
         default:
             statusText = '❓ حالة غير معروفة';
     }
     
     if (transactionStatus) transactionStatus.textContent = statusText;
     if (dealActionButtons) dealActionButtons.innerHTML = buttonsHTML;
 }
 
 function confirmDelivery(dealId) {
     if (!confirm('هل تأكدت من استلام الحساب بشكل صحيح؟\nبعد التأكيد ستتم مراجعة الصفقة من قبل الإدارة.')) {
         return;
     }
     
     fetch('api/confirm_delivery.php', {
         method: 'POST',
         headers: {
             'Content-Type': 'application/json'
         },
         body: JSON.stringify({ deal_id: dealId })
     })
     .then(response => response.json())
     .then(data => {
         if (data.success) {
             alert('تم تأكيد الاستلام بنجاح! ستتم مراجعة الصفقة من قبل الإدارة.');
             // تحديث واجهة المستخدم
             updateDealUI({ id: dealId, status: 'DELIVERED' });
             // إعادة تحميل المحادثة
             if (typeof loadMessages === 'function') {
                 loadMessages();
             }
         } else {
             alert(data.error || 'حدث خطأ أثناء تأكيد الاستلام.');
         }
     })
     .catch(error => {
         console.error('Error confirming delivery:', error);
         alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
     });
 }
 
 function loadActiveDeal(conversationId) {
     if (!conversationId) return;
     
     fetch(`get_active_deal.php?conversation_id=${conversationId}`)
         .then(response => response.json())
         .then(data => {
             if (data.success && data.deal) {
                 updateDealUI(data.deal);
                 document.getElementById('deal-status-section').classList.remove('hidden');
             } else {
                 document.getElementById('deal-status-section').classList.add('hidden');
             }
         })
         .catch(error => {
             console.error('Error loading active deal:', error);
         });
 }

// فتح وإغلاق مودال بدء الصفقة الآمنة ومودال إرسال الأموال
document.addEventListener('DOMContentLoaded', function() {
    // مودال بدء الصفقة الآمنة
    var startDealBtn = document.getElementById('start-deal-btn');
    var startDealModal = document.getElementById('start-deal-modal');
    var cancelDealBtn = document.getElementById('cancel-deal');
    var startDealForm = document.getElementById('start-deal-form');
    var dealAccountSelect = document.getElementById('deal-account-select');
    
    if (startDealBtn && startDealModal && cancelDealBtn && startDealForm) {
        startDealBtn.addEventListener('click', function() {
            const sellerId = getCurrentSellerId();
            if (!sellerId) {
                alert('معرف البائع غير محدد.');
                return;
            }
            document.getElementById('deal-seller-id').value = sellerId;
            loadSellerAccounts(sellerId);
            startDealModal.classList.remove('hidden');
        });

        cancelDealBtn.addEventListener('click', function() {
            startDealModal.classList.add('hidden');
        });

        if (dealAccountSelect) {
            dealAccountSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.dataset.price || 0;
                document.getElementById('deal-amount').value = price;
            });
        }

        startDealForm.addEventListener('submit', function(e) {
            e.preventDefault();
            startSecureDeal();
        });
    }
    
    // مودال إرسال الأموال
    var sendMoneyBtn = document.getElementById('send-money-btn');
    var sendMoneyModal = document.getElementById('send-money-modal');
    var cancelSendMoneyBtn = document.getElementById('cancel-send-money');
    var sendMoneyForm = document.getElementById('send-money-form');

    if (sendMoneyBtn && sendMoneyModal && cancelSendMoneyBtn && sendMoneyForm) {
        sendMoneyBtn.addEventListener('click', function() {
            const sellerId = getCurrentSellerId();
            if (!sellerId) {
                alert('معرف البائع غير محدد.');
                return;
            }
            document.getElementById('seller-id').value = sellerId;
            sendMoneyModal.classList.remove('hidden');
        });

        cancelSendMoneyBtn.addEventListener('click', function() {
            sendMoneyModal.classList.add('hidden');
        });

        sendMoneyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(sendMoneyForm);
            // الحصول على معرف المستلم من الحقل المخفي أو من متغير جافاسكريبت
            var toId = formData.get('seller-id') || window.ACTIVE_CHAT_USER;
            if (!toId || isNaN(toId) || parseInt(toId) <= 0) {
                alert('معرف المستلم غير صالح أو غير محدد.');
                return;
            }
            fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    payment_method: formData.get('payment-method'),
                    amount: formData.get('amount'),
                    to_id: parseInt(toId)
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    alert('تم التحويل بنجاح!');
                } else {
                    alert(data.error || 'حدث خطأ أثناء التحويل.');
                }
                sendMoneyModal.classList.add('hidden');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
            });
        });
    }
});
</script>

<!-- نافذة الإبلاغ عن المحادثة -->
<div id="report-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl border border-white/10 shadow-2xl w-full max-w-md transform transition-all duration-300 scale-95 opacity-0" id="report-modal-content">
        <div class="p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-flag text-red-400"></i>
                    الإبلاغ عن المحادثة
                </h3>
                <button id="close-report-modal" class="p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200">
                    <i class="fa fa-times text-white/60"></i>
                </button>
            </div>
            
            <form id="report-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-white/90 mb-2">سبب الإبلاغ</label>
                    <textarea id="report-reason" name="reason" rows="4" placeholder="اكتب سبب الإبلاغ بالتفصيل..." class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-3 text-white placeholder-white/60 focus:border-red-400/50 focus:bg-white/10 transition-all duration-200 resize-none" required></textarea>
                </div>
                
                <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3 mb-4">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-exclamation-triangle text-yellow-400 text-sm mt-0.5"></i>
                        <div class="text-xs text-yellow-200">
                            <p class="font-medium mb-1">تنبيه مهم:</p>
                            <p>سيتم مراجعة بلاغك من قبل فريق الإدارة. الإبلاغات الكاذبة قد تؤدي إلى تعليق حسابك.</p>
                        </div>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" id="cancel-report" class="flex-1 px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white hover:bg-white/10 transition-all duration-200 font-medium">
                        إلغاء
                    </button>
                    <button type="submit" id="submit-report" class="flex-1 px-4 py-3 rounded-lg bg-gradient-to-r from-red-500 to-red-600 text-white font-medium hover:from-red-600 hover:to-red-700 transition-all duration-200 shadow-lg">
                        <i class="fa-solid fa-flag ml-1"></i>
                        إرسال البلاغ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/massage.js"></script>

<style>
    select#payment-method option {
        color: black; /* النصوص داخل الخيارات تظهر باللون الأسود */
    }
    input#amount {
        color: black; /* النصوص داخل حقل المبلغ تظهر باللون الأسود */
    }
    
    /* تأثيرات نافذة الإبلاغ */
    #report-modal.show #report-modal-content {
        transform: scale(1);
        opacity: 1;
    }
</style>

<script src="js/ban-check.js"></script>
</body>
</html>