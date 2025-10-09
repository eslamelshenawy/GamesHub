console.log('massage.js file loaded successfully!');

// متغير لتتبع حالة الـ modals لمنع فتح أكثر من modal في نفس الوقت
window.MODAL_OPEN = false;

// استعادة بيانات الصفقة المحفوظة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    console.log('First DOMContentLoaded event fired');
    setTimeout(() => {
        if (window.ACTIVE_CHAT_USER) {
            restoreSavedDealData();
        }
    }, 500);
});
// جلب بيانات مستخدم (الاسم والصورة) عبر API
function fetchUserProfile(userId, cb) {
    if (!userId) return cb && cb(null);
    fetch('api/get_user.php?id=' + encodeURIComponent(userId), { credentials: 'include' })
        .then(r => r.json())
        .then(data => {
            if (data && data.profile) {
                cb && cb(data.profile);
            } else {
                cb && cb(null);
            }
        })
        .catch(() => cb && cb(null));
}
// Sidebar controls
function openSidebar() {
    const el = document.getElementById('sidebar');
    if (el) el.classList.remove('translate-x-full');
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.classList.remove('hidden');
        overlay.classList.add('block');
    }
}
function closeSidebar() {
    const el = document.getElementById('sidebar');
    if (el) el.classList.add('translate-x-full');
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('block');
    }
}

// Toast
function showPlaceholder(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg || 'الميزة غير متاحة الآن — قريبًا ✨';
    t.style.display = 'block';
    t.style.opacity = '1';
    clearTimeout(window._toastTimer);
    window._toastTimer = setTimeout(() => {
        t.style.transition = 'opacity 300ms';
        t.style.opacity = '0';
        setTimeout(() => t.style.display = 'none', 300);
    }, 2200);
}

// Global vars
window.ACTIVE_CHAT_USER = null; // ID المستخدم الذي يتم الدردشة معه
window.ACTIVE_CHAT_USER = null;   // ID المستخدم الحالي (من السيرفر)

// Close chat
function closeChat() {
    const cw = document.getElementById('chat-window');
    const cl = document.getElementById('conversation-list');
    if (cw) cw.classList.add('hidden');
    if (cl) cl.classList.remove('hidden');
}

// Fetch messages (safe DOM updates to avoid XSS)
function fetchMessages() {
    if (!ACTIVE_CHAT_USER) return;
    // ensure ACTIVE_CHAT_USER is an integer
    const chatId = parseInt(ACTIVE_CHAT_USER, 10);
    console.log('fetchMessages: requesting messages for user_id=', chatId);
    // حفظ آخر معرف رسالة تم عرضه
    if (!window._lastMessageId) window._lastMessageId = null;
    fetch(`api/get_messages.php?user_id=${chatId}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
        // process response
        if (!data) {
            showPlaceholder('استجابة خادم غير متوقعة');
            return;
        }
        if (!data.success) {
            console.error('get_messages failed', data);
            showPlaceholder((data.error) ? data.error : 'فشل تحميل الرسائل');
            return;
        }

        let container = document.getElementById('messages-container');
        // create fallback container if missing (some templates may vary)
        if (!container) {
            const chatWindow = document.getElementById('chat-window');
            if (chatWindow) {
                container = document.createElement('div');
                container.id = 'messages-container';
                container.className = 'flex-1 overflow-y-auto space-y-4 py-4 chat-container';
                chatWindow.insertBefore(container, chatWindow.querySelector('form') || null);
            } else {
                console.error('fetchMessages: chat-window not found, cannot display messages');
                showPlaceholder('نافذة المحادثة غير متاحة');
                return;
            }
        }

        container.innerHTML = '';
        CURRENT_USER_ID = data.me;

        if (Array.isArray(data.messages) && data.messages.length > 0) {
            // تحقق من وجود رسالة جديدة
            const lastMsg = data.messages[data.messages.length - 1];
            if (lastMsg && lastMsg.id && lastMsg.sender_id != CURRENT_USER_ID) {
                if (window._lastMessageId && lastMsg.id != window._lastMessageId) {
                    // إشعار فوري
                    if (window.Notification && Notification.permission === 'granted') {
                        new Notification('رسالة جديدة', {
                            body: lastMsg.message_text && lastMsg.message_text.length < 60 ? lastMsg.message_text : 'لديك رسالة جديدة',
                            icon: 'uploads/default-avatar.svg'
                        });
                    } else if (window.Notification && Notification.permission !== 'denied') {
                        Notification.requestPermission();
                    }
                }
                window._lastMessageId = lastMsg.id;
            } else if (lastMsg && lastMsg.id) {
                window._lastMessageId = lastMsg.id;
            }
            data.messages.forEach(msg => {
                const isSent = msg.sender_id == CURRENT_USER_ID;
                const row = document.createElement('div');
                row.className = 'flex items-start gap-3';
                if (isSent) row.classList.add('justify-end');
                const bubble = document.createElement('div');
                bubble.className = 'message-bubble max-w-[70%] relative';
                bubble.classList.add(isSent ? 'sent' : 'received');
                // دعم عرض الملفات
                let isFile = false;
                let fileUrl = '';
                if (msg && typeof msg.message_text === 'string' && msg.message_text.startsWith('[file]')) {
                    isFile = true;
                    fileUrl = msg.message_text.replace('[file]', '');
                }
                if (isFile) {
                    // عرض صورة أو فيديو أو رابط ملف
                    const ext = fileUrl.split('.').pop().toLowerCase();
                    if (["jpg","jpeg","png","gif","webp","bmp"].includes(ext)) {
                        const img = document.createElement('img');
                        img.src = fileUrl;
                        img.alt = 'صورة مرفقة';
                        img.style.maxWidth = '180px';
                        img.style.maxHeight = '180px';
                        img.className = 'rounded-lg border border-white/10 mb-1 chat-image-popup';
                        img.style.cursor = 'zoom-in';
                        bubble.appendChild(img);
                    } else if (["mp4","webm","mov"].includes(ext)) {
                        const video = document.createElement('video');
                        video.src = fileUrl;
                        video.controls = true;
                        video.style.maxWidth = '220px';
                        video.style.maxHeight = '180px';
                        video.className = 'rounded-lg border border-white/10 mb-1';
                        bubble.appendChild(video);
                    } else {
                        const a = document.createElement('a');
                        a.href = fileUrl;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.textContent = '📎 ملف مرفق';
                        a.className = 'text-blue-400 underline break-all';
                        bubble.appendChild(a);
                    }
                } else {
                    const p = document.createElement('p');
                    p.textContent = (msg && typeof msg.message_text !== 'undefined') ? msg.message_text : '';
                    bubble.appendChild(p);
                }
                const time = document.createElement('span');
                time.className = 'text-xs muted block mt-1';
                time.textContent = (msg && typeof msg.created_at !== 'undefined') ? msg.created_at : '';
                bubble.appendChild(time);

                // زر حذف يظهر عند الضغط المطول
                if (isSent) {
                    let deleteBtn = document.createElement('button');
                    deleteBtn.innerHTML = '🗑️';
                    deleteBtn.title = 'حذف الرسالة';
                    deleteBtn.style.display = 'none';
                    deleteBtn.style.position = 'absolute';
                    deleteBtn.style.top = '8px';
                    deleteBtn.style.left = '8px';
                    deleteBtn.style.background = 'rgba(34,34,34,0.9)';
                    deleteBtn.style.color = '#fff';
                    deleteBtn.style.border = 'none';
                    deleteBtn.style.borderRadius = '50%';
                    deleteBtn.style.width = '28px';
                    deleteBtn.style.height = '28px';
                    deleteBtn.style.cursor = 'pointer';
                    deleteBtn.style.zIndex = '10';
                    deleteBtn.onclick = function(e) {
                        e.stopPropagation();
                        if (!confirm('هل تريد حذف هذه الرسالة نهائياً؟')) return;
                        deleteBtn.disabled = true;
                        fetch('api/delete_message.php', {
                            method: 'POST',
                            credentials: 'include',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'message_id=' + encodeURIComponent(msg.id)
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res && res.success) {
                                row.remove();
                            } else {
                                showPlaceholder(res && res.error ? res.error : 'فشل حذف الرسالة');
                                deleteBtn.disabled = false;
                            }
                        })
                        .catch(()=>{
                            showPlaceholder('خطأ في الاتصال بالخادم');
                            deleteBtn.disabled = false;
                        });
                    };
                    // دعم الضغط المطول (touch & mouse)
                    let pressTimer = null;
                    bubble.addEventListener('mousedown', function(e) {
                        if (pressTimer) clearTimeout(pressTimer);
                        pressTimer = setTimeout(()=>{ deleteBtn.style.display = 'block'; }, 500);
                    });
                    bubble.addEventListener('mouseup', function(e) {
                        if (pressTimer) clearTimeout(pressTimer);
                    });
                    bubble.addEventListener('mouseleave', function(e) {
                        if (pressTimer) clearTimeout(pressTimer);
                    });
                    // دعم اللمس
                    bubble.addEventListener('touchstart', function(e) {
                        if (pressTimer) clearTimeout(pressTimer);
                        pressTimer = setTimeout(()=>{ deleteBtn.style.display = 'block'; }, 500);
                    });
                    bubble.addEventListener('touchend', function(e) {
                        if (pressTimer) clearTimeout(pressTimer);
                    });
                    // إخفاء الزر عند الضغط خارج الفقاعة
                    document.addEventListener('click', function hideDeleteBtn(e) {
                        if (!bubble.contains(e.target)) deleteBtn.style.display = 'none';
                    });
                    bubble.appendChild(deleteBtn);
                }

                row.appendChild(bubble);
                container.appendChild(row);
            });
            // scroll to bottom safely
            if (container && container instanceof HTMLElement && typeof container.scrollTop === 'number' && typeof container.scrollHeight === 'number') {
                container.scrollTop = container.scrollHeight;
            }
        } else {
            // لا توجد رسائل
            const noMsg = document.createElement('div');
            noMsg.className = 'no-messages-hint';
            noMsg.textContent = 'لا توجد رسائل بعد. ابدأ المحادثة الآن!';
            container.appendChild(noMsg);
        }
        
        // تحميل حالة الصفقة النشطة بعد تحديث الرسائل
        if (window.CONVERSATION_ID) {
            loadActiveDeal(window.CONVERSATION_ID);
        } else if (ACTIVE_CHAT_USER && window.buyerId) {
            // استخدام API الجديد للحصول على conversation_id
            fetch(`api/get_conversation_by_users.php?buyer_id=${window.buyerId}&seller_id=${ACTIVE_CHAT_USER}`, { credentials: 'include' })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.conversation_id) {
                        window.CONVERSATION_ID = data.conversation_id;
                        loadActiveDeal(data.conversation_id);
                    } else {
                        // استخدام ACTIVE_CHAT_USER كبديل
                        loadActiveDeal(ACTIVE_CHAT_USER);
                    }
                })
                .catch(e => {
                    console.warn('خطأ في الحصول على conversation_id في fetchMessages:', e);
                    loadActiveDeal(ACTIVE_CHAT_USER);
                });
        } else if (ACTIVE_CHAT_USER) {
            // استخدام ACTIVE_CHAT_USER كبديل إذا لم يكن buyerId متوفراً
            loadActiveDeal(ACTIVE_CHAT_USER);
        }
    })
    .catch((err) => { console.error('fetchMessages error', err); showPlaceholder('خطأ في تحميل الرسائل'); });
}

// دالة فتح المحادثة الإدارية للمستخدم العادي
function openAdminChatForUser(chatId, adminName) {
    // تعيين المحادثة النشطة
    window.ACTIVE_ADMIN_CHAT_ID = chatId;
    window.ACTIVE_CHAT_USER = null; // إلغاء المحادثة العادية
    
    // تحديث واجهة المستخدم
    const chatHeader = document.querySelector('.chat-header h3');
    if (chatHeader) {
        chatHeader.innerHTML = '<i class="fas fa-shield-halved text-neon-blue ml-2"></i>' + adminName;
    }
    
    // إخفاء عناصر الصفقة
    const dealElements = document.querySelectorAll('.deal-section, .deal-info, .deal-actions');
    dealElements.forEach(el => el.style.display = 'none');
    
    // تحميل رسائل المحادثة الإدارية
    loadAdminMessages(chatId);
    
    // تحديث منطقة الإدخال
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.placeholder = 'اكتب رسالتك للإدارة...';
    }
    
    // إظهار منطقة المحادثة
    const chatWindow = document.getElementById('chat-window');
    if (chatWindow) {
        chatWindow.classList.remove('hidden');
    }
}

// دالة تحميل رسائل المحادثة الإدارية
function loadAdminMessages(chatId) {
    fetch(`api/get_user_admin_messages.php?chat_id=${chatId}`, { credentials: 'include' })
    .then(r => r.json())
    .then(data => {
        if (data.success && Array.isArray(data.messages)) {
            displayAdminMessages(data.messages);
        } else {
            console.error('فشل في تحميل الرسائل الإدارية:', data.error);
        }
    })
    .catch(err => {
        console.error('خطأ في تحميل الرسائل الإدارية:', err);
    });
}

// دالة عرض الرسائل الإدارية
function displayAdminMessages(messages) {
    const container = document.getElementById('messages-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    messages.forEach(msg => {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${msg.sender_type === 'user' ? 'sent' : 'received'}`;
        
        const time = new Date(msg.created_at).toLocaleTimeString('ar-SA', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const senderName = msg.sender_name || (msg.sender_type === 'admin' ? 'إدارة واسطة' : 'أنت');
        
        messageDiv.innerHTML = `
            <div class="message-content">
                <div class="sender-name">${senderName}</div>
                <p>${msg.message_text || msg.message || ''}</p>
                <span class="message-time">${time}</span>
            </div>
        `;
        
        container.appendChild(messageDiv);
    });
    
    // التمرير إلى أسفل
    container.scrollTop = container.scrollHeight;
}

// دالة إرسال رسالة إدارية
function sendAdminMessage(chatId, message) {
    if (!message || !message.trim()) {
        showPlaceholder('يرجى كتابة رسالة');
        return;
    }
    
    const data = {
        chat_id: chatId,
        message: message.trim()
    };
    
    fetch('api/send_user_admin_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
        credentials: 'include'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // إعادة تحميل الرسائل
            loadAdminMessages(chatId);
            
            // مسح النموذج
            const form = document.getElementById('message-form');
            if (form) form.reset();
            
            showPlaceholder('تم إرسال الرسالة بنجاح');
        } else {
            console.error('فشل في إرسال الرسالة الإدارية:', data.error);
            showPlaceholder(data.error || 'فشل في إرسال الرسالة');
        }
    })
    .catch(err => {
        console.error('خطأ في إرسال الرسالة الإدارية:', err);
        showPlaceholder('خطأ في الاتصال بالخادم');
    });
}

// Fetch conversation list (use safe DOM updates)
function fetchConversations() {
    // جلب المحادثات العادية والإدارية
    Promise.all([
        fetch('api/get_conversations.php', { credentials: 'include' }).then(r => r.json()),
        fetch('api/get_user_admin_chats.php', { credentials: 'include' }).then(r => r.json())
    ])
    .then(([regularData, adminData]) => {
        const list = document.getElementById('conversation-list');
        if (!list) return;
        
        let allConversations = [];
        
        // إضافة المحادثات العادية
        if (regularData && regularData.success && Array.isArray(regularData.conversations)) {
            allConversations = [...regularData.conversations];
        }
        
        // إضافة المحادثات الإدارية
        if (adminData && adminData.success && Array.isArray(adminData.chats)) {
            allConversations = [...allConversations, ...adminData.chats];
        }
        
        if (allConversations.length === 0) {
            const nc = document.getElementById('no-conversations');
            if (nc) nc.classList.remove('hidden');
            return;
        }
        
        const nc = document.getElementById('no-conversations');
        if (nc) nc.classList.add('hidden');
        list.innerHTML = '';
        
        // ترتيب المحادثات حسب آخر رسالة
        allConversations.sort((a, b) => {
            const timeA = new Date(a.last_message_at || a.created_at || 0);
            const timeB = new Date(b.last_message_at || b.created_at || 0);
            return timeB - timeA;
        });
        allConversations.forEach(c => {
            const last = c.last_message ? c.last_message : '';
            const isAdminChat = c.type === 'admin_chat';
            const item = document.createElement('div');
            item.className = 'p-3 border-b border-white/5 cursor-pointer conversation-item flex items-center gap-3';
            
            // إضافة تمييز بصري للمحادثات الإدارية
            if (isAdminChat) {
                item.classList.add('admin-chat-item');
                item.style.background = 'linear-gradient(90deg, rgba(0, 212, 255, 0.1), rgba(155, 89, 255, 0.1))';
                item.style.borderLeft = '3px solid #00d4ff';
            }

            const img = document.createElement('img');
            img.className = 'w-12 h-12 rounded-full object-cover';
            
            if (isAdminChat) {
                const adminImageUrl = c.admin_image && c.admin_image.trim() ? c.admin_image : 'uploads/default-avatar.svg';
            img.src = adminImageUrl;
            img.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
                img.alt = 'صورة الإدارة';
            } else {
                const avatarUrl = c.avatar && c.avatar.trim() ? c.avatar : 'uploads/default-avatar.svg';
            img.src = avatarUrl;
            img.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
                img.alt = (c.full_name || c.username) + ' avatar';
            }

            const meta = document.createElement('div');
            meta.className = 'flex-1 flex flex-col justify-center';
            const name = document.createElement('div');
            name.className = 'font-medium';
            
            if (isAdminChat) {
                name.innerHTML = '<i class="fas fa-shield-halved text-neon-blue ml-1"></i>' + (c.admin_name || 'الإدارة');
            } else {
                name.textContent = c.full_name || c.username || '';
            }

            // إضافة بادج للرسائل غير المقروءة
            if (c.unread_count && parseInt(c.unread_count) > 0) {
                const badge = document.createElement('span');
                badge.textContent = c.unread_count > 99 ? '99+' : c.unread_count;
                badge.className = 'inline-block ml-2 px-2 py-1 rounded-full bg-red-500 text-white text-xs font-bold';
                name.appendChild(badge);
            }

            const preview = document.createElement('div');
            preview.className = 'muted text-sm truncate';
            if (last && last.length > 15) {
                preview.textContent = last.substring(0, 10) + '...';
            } else {
                preview.textContent = last;
            }

            meta.appendChild(name);
            meta.appendChild(preview);
            item.appendChild(img);
            item.appendChild(meta);

            item.addEventListener('click', function(){
                if (isAdminChat) {
                    // فتح المحادثة الإدارية
                    openAdminChatForUser(c.id, c.admin_name || 'الإدارة');
                    return;
                }
                
                // set active chat and load messages
                ACTIVE_CHAT_USER = c.id;
                window.ACTIVE_CHAT_USER = c.id; // تحديث المتغير العام أيضاً
                console.log('ACTIVE_CHAT_USER set to:', ACTIVE_CHAT_USER); // تسجيل القيمة للتأكد من تعيينها بشكل صحيح
                
                // الحصول على conversation_id الصحيح وتحميل حالة الصفقة النشطة
                setTimeout(() => {
                    // استخدام API الجديد للحصول على conversation_id
                    if (window.buyerId && c.id) {
                        // تحديد من هو المشتري ومن هو البائع
                        const buyerId = window.buyerId;
                        const sellerId = c.id;
                        
                        fetch(`api/get_conversation_by_users.php?buyer_id=${buyerId}&seller_id=${sellerId}`, { credentials: 'include' })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.conversation_id) {
                                    window.CONVERSATION_ID = data.conversation_id;
                                    console.log('تم الحصول على conversation_id:', data.conversation_id);
                                    loadActiveDeal(data.conversation_id);
                                } else {
                                    console.warn('فشل في الحصول على conversation_id:', data.message || 'خطأ غير معروف');
                                    // استخدام user_id كبديل
                                    loadActiveDeal(ACTIVE_CHAT_USER);
                                }
                            })
                            .catch(e => {
                                console.warn('خطأ في الاتصال بـ get_conversation_by_users.php:', e);
                                loadActiveDeal(ACTIVE_CHAT_USER);
                            });
                    } else {
                         console.warn('buyerId أو seller_id غير متوفر');
                         loadActiveDeal(ACTIVE_CHAT_USER);
                     }
                 }, 100);
                // تحديث رابط الملف الشخصي في نافذة الدردشة
                if (typeof updateUserProfileLink === 'function') {
                    updateUserProfileLink(c.id);
                }
                const cw = document.getElementById('chat-window');
                const cl = document.getElementById('conversation-list');
                // إظهار نافذة الدردشة وإخفاء القائمة بشكل صريح
                if (cw) {
                    cw.classList.remove('hidden');
                    cw.classList.add('flex');
                }
                if (cl) {
                    cl.classList.add('hidden');
                    cl.classList.remove('flex');
                }
                // جلب بيانات المستخدم الآخر وتحديث الصورة والاسم
                fetchUserProfile(c.id, function(profile) {
                    const cu = document.getElementById('chat-username');
                    const ca = document.getElementById('chat-user-avatar');
                    const statusEl = document.getElementById('chat-user-status');
                    const typingEl = document.getElementById('chat-user-typing');
                    // تحديث رابط اسم المستخدم ليحتوي على id
                    var profileLink = document.querySelector('#chat-window a[href^="user-profile.html"]');
                    if (profileLink) {
                        profileLink.href = 'user-profile.html?id=' + encodeURIComponent(c.id);
                    }
                    if (cu) cu.textContent = (profile && profile.name) ? profile.name : (c.full_name || c.username || '');
                    if (ca) {
            const profileImageUrl = (profile && profile.image && profile.image.trim()) ? profile.image : (c.avatar && c.avatar.trim() ? c.avatar : 'uploads/default-avatar.svg');
            ca.src = profileImageUrl;
            ca.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
        }
                    if (statusEl) {
                        if (profile && profile.is_online == 1) {
                            statusEl.textContent = 'متصل الآن';
                            statusEl.classList.remove('text-gray-400');
                            statusEl.classList.add('text-green-400');
                        } else {
                            statusEl.textContent = 'غير متصل';
                            statusEl.classList.remove('text-green-400');
                            statusEl.classList.add('text-gray-400');
                        }
                    }
                    if (typingEl) typingEl.style.display = 'none';
                });
                // حفظ آخر معرف مستخدم نشط لعرض حالة الكتابة
                window._activeChatUserId = c.id;
                // mark messages from this user as read on the server
                markRead(c.id).finally(() => {
                    fetchMessages();
                    // تحميل الصفقة النشطة للمحادثة باستخدام API الجديد
                    if (window.buyerId) {
                        fetch(`api/get_conversation_by_users.php?buyer_id=${window.buyerId}&seller_id=${c.id}`, { credentials: 'include' })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.conversation_id) {
                                    loadActiveDeal(data.conversation_id);
                                } else {
                                    loadActiveDeal(c.id);
                                }
                            })
                            .catch(() => loadActiveDeal(c.id));
                    } else {
                        loadActiveDeal(c.id);
                    }
                   // startStatusPolling();
                });
            });
            list.appendChild(item);
        });
    })
    .catch((err)=>{
        console.error('fetchConversations error:', err);
        showPlaceholder('تعذر تحميل قائمة المحادثات. يرجى التحقق من الاتصال أو المحاولة لاحقاً');
    });
}

// دالة عرض مودال إنهاء المناقشة
// تم إزالة ميزة إنهاء المناقشة

// Send message (يدعم إرسال الملفات)
function sendMessage(event) {
    try {
        if (event && event.preventDefault) event.preventDefault();
        let form = (event && event.target && event.target.tagName === 'FORM') ? event.target : document.getElementById('message-form');
        if (!form) {
            console.error('sendMessage: form element not found');
            showPlaceholder('نموذج الرسائل غير متاح');
            return;
        }
        const sendBtn = document.getElementById('send-message-btn');
        if (!sendBtn) {
            console.error('sendMessage: send button not found');
            showPlaceholder('زر الإرسال غير متاح');
            return;
        }
        if (sendBtn.disabled) return;
        
        // إنشاء FormData أولاً
        const formData = new FormData(form);
        
        // التحقق من نوع المحادثة
        if (window.ACTIVE_ADMIN_CHAT_ID) {
            // إرسال رسالة إدارية
            sendAdminMessage(window.ACTIVE_ADMIN_CHAT_ID, formData.get('message'));
            sendBtn.disabled = false;
            return;
        }
        
        if (!ACTIVE_CHAT_USER) {
            showPlaceholder('يرجى فتح محادثة أولاً');
            return;
        }
        sendBtn.disabled = true;
        const fileInput = document.getElementById('file-input');
        const file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        formData.append('to', ACTIVE_CHAT_USER);
        // get CSRF then send
        fetch('api/get_csrf.php', { credentials: 'include' })
        .then(r => { if (!r.ok) throw new Error('Failed to get CSRF'); return r.json(); })
        .then(cs => {
            if (cs && cs.csrf_token) formData.append('csrf_token', cs.csrf_token);
            // إذا كان هناك ملف، أرسل عبر send_file.php
            if (file) {
                return fetch('api/send_file.php', { method: 'POST', body: formData, credentials: 'include' });
            } else {
                return fetch('api/send_message.php', { method: 'POST', body: formData, credentials: 'include' });
            }
        })
        .then(res => res.json().catch(e => ({ success: false, error: 'invalid_server_response' })))
        .then(data => {
            sendBtn.disabled = false;
            if (data && data.success) {
                try {
                    form.reset();
                    if (fileInput) fileInput.value = '';

                    // إخفاء معاينة الملف
                    const preview = document.getElementById('file-preview');
                    const previewContent = document.getElementById('preview-content');
                    const fileName = document.getElementById('file-name');
                    const fileSize = document.getElementById('file-size');

                    if (preview) preview.classList.add('hidden');
                    if (previewContent) previewContent.innerHTML = '';
                    if (fileName) fileName.textContent = '';
                    if (fileSize) fileSize.textContent = '';
                } catch(e){}
                fetchMessages();
                if (data.file) {
                    showPlaceholder('تم إرسال الملف بنجاح');
                } else {
                    showPlaceholder('تم إرسال الرسالة بنجاح');
                }
            } else {
                console.error('sendMessage failed', data);
                showPlaceholder((data && data.error) ? data.error : 'فشل إرسال الرسالة');
            }
        })
        .catch(err => { sendBtn.disabled = false; console.error('sendMessage error', err); showPlaceholder('خطأ في الاتصال بالخادم'); });
    } catch (err) {
        console.error('sendMessage unexpected error', err);
        showPlaceholder('خطأ داخلي');
    }
}

// Mark messages from a specific user as read (server-side)
function markRead(userId) {
    if (!userId) return Promise.resolve();
    // get CSRF then post
    return fetch('api/get_csrf.php', { credentials: 'include' })
    .then(r => r.json())
    .then(cs => {
        const token = cs && cs.csrf_token ? cs.csrf_token : '';
        const body = new URLSearchParams({ user_id: userId, csrf_token: token });
        return fetch('api/mark_read.php', { method: 'POST', body: body.toString(), credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    })
    .then(r => r.json())
    .catch((err) => { console.error('markRead error:', err); });
}

window.addEventListener('load', () => {
    if (window.innerWidth >= 768) {
        const list = document.getElementById('conversation-list');
        const cw = document.getElementById('chat-window');
        if (list && list instanceof HTMLElement) list.style.display = 'block';
        if (cw && cw instanceof HTMLElement) cw.style.display = 'flex';
    }
    fetchConversations();
    setInterval(() => {
        fetchConversations();
        if (ACTIVE_CHAT_USER) fetchMessages();
    }, 5000); // تحديث كل 5 ثواني
    // عند تحميل الصفحة، أرسل حالة الاتصال للخادم
    fetch('api/update_status.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=online'
    });
    // عند إغلاق الصفحة أو إعادة تحميلها، أرسل حالة غير متصل
    window.addEventListener('beforeunload', function() {
        navigator.sendBeacon && navigator.sendBeacon('api/update_status.php', new URLSearchParams({action:'offline'}));
    });

    // Initialize ACTIVE_CHAT_USER from URL or sessionStorage if not set
    (function initActiveChatFromUrl(){
        try {
            if (!ACTIVE_CHAT_USER) {
                const params = new URLSearchParams(window.location.search);
                let uid = params.get('seller_id') || params.get('user_id') || params.get('chat_with') || sessionStorage.getItem('active_chat_user');
                if (!uid && window.__contactSellerId) {
                    uid = window.__contactSellerId;
                    try { sessionStorage.setItem('active_chat_user', uid); } catch (e) {}
                }
                if (uid) {
                    const id = parseInt(uid, 10);
                    if (!isNaN(id) && id > 0) {
                        ACTIVE_CHAT_USER = id;
                        console.log('ACTIVE_CHAT_USER set to:', ACTIVE_CHAT_USER); // تسجيل القيمة للتأكد من تعيينها بشكل صحيح
                        try { sessionStorage.setItem('active_chat_user', id); } catch (e) {}
                    }
                }
            }
        } catch (e) {
            console.warn('initActiveChatFromUrl error', e);
        }

        if (ACTIVE_CHAT_USER) {
            // جلب بيانات المستخدم الآخر وتحديث الصورة والاسم
            fetchUserProfile(ACTIVE_CHAT_USER, function(profile) {
                const cu = document.getElementById('chat-username');
                const ca = document.getElementById('chat-user-avatar');
                if (cu) cu.textContent = (profile && profile.name) ? profile.name : '';
                if (ca) {
            const userImageUrl = (profile && profile.image && profile.image.trim()) ? profile.image : 'uploads/default-avatar.svg';
            ca.src = userImageUrl;
            ca.onerror = function() {
                this.src = 'uploads/default-avatar.svg';
            };
        }
            });
            fetchMessages(); // Load messages for the active seller
            
            // استعادة بيانات الصفقة للمحادثة النشطة
            if (window.CONVERSATION_ID) {
                loadActiveDeal(window.CONVERSATION_ID);
            } else {
                loadActiveDeal(ACTIVE_CHAT_USER);
            }
        }
    })();
});

// Consolidated updateDealUI logic
function updateDealUI(deal) {
    const statusMessage = document.getElementById('transaction-status');
    const actionButtons = document.getElementById('deal-action-buttons');
    if (!deal || !statusMessage || !actionButtons) {
        return;
    }

    // حفظ بيانات الصفقة في localStorage مع ضمان الاستمرارية
    saveDealToLocalStorage(deal);
    
    // حفظ الصفقة النشطة في المتغير العام
    window.ACTIVE_DEAL = deal;

    let statusMsg = '';
    let buttonsHTML = '';
    
    // عرض معلومات الصفقة
    const dealInfoHTML = `
        <div class="bg-gray-700/80 rounded-xl p-3 mb-3 text-xs shadow-lg border border-gray-600/50">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-xs">صاحب الحساب:</span> 
                    <span class="text-white font-medium text-xs">${deal.account_owner_name || deal.seller_name}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-xs">المشتري:</span> 
                    <span class="text-white font-medium text-xs">${deal.buyer_name}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-xs">البائع:</span> 
                    <span class="text-white font-medium text-xs">${deal.seller_name}</span>
                </div>
            </div>
        </div>
    `;
    
    // إضافة معلومات الصفقة قبل رسالة الحالة
    const dealInfoContainer = document.getElementById('deal-info-container');
    if (dealInfoContainer) {
        dealInfoContainer.innerHTML = dealInfoHTML;
    }

    // التحقق من وجود أزرار مخفية مؤقتاً (عند الضغط على زر التمويل)
    const existingButtons = actionButtons.innerHTML;
    const hasHiddenFundButton = existingButtons.includes('fund-processing') || 
                               localStorage.getItem(`deal_${deal.id}_fund_clicked`) === 'true';

    switch (deal.status) {
        case 'CREATED':
            statusMsg = '⏳ تم إنشاء الصفقة - في انتظار تأكيد استلام بيانات الحساب';
            if (window.buyerId && window.buyerId == deal.buyer_id) {
                // إذا تم الضغط على زر التمويل مسبقاً، لا نعيد إظهاره
                if (!hasHiddenFundButton) {
                    buttonsHTML = `
<div class="flex flex-col gap-3 items-center">
    <!-- التحذير -->
    <div class="text-sm text-yellow-800 bg-yellow-100 border border-yellow-300 rounded-lg p-3 w-full text-center">
        ⚠️ تنبيه: بعد <b>تأكيد استلام البيانات</b> لا يمكن التراجع عن هذه الخطوة
    </div>

    <!-- الأزرار -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center w-full">
        <button id="confirm-receipt-btn" data-deal-id="${deal.id}"
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm font-medium flex-1 sm:flex-none">
            <i class="fa-solid fa-check ml-2"></i> تأكيد استلام البيانات
        </button>

        <button id="cancel-deal-btn" data-deal-id="${deal.id}"
            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm font-medium flex-1 sm:flex-none">
            <i class="fa-solid fa-times ml-2"></i> إلغاء الصفقة
        </button>
    </div>
</div>
  
                    `;
                } else {
                    // إظهار رسالة معالجة إذا تم الضغط على الزر
                    buttonsHTML = `
                        <div class="text-center text-yellow-300 bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-3">
                            <i class="fa-solid fa-spinner fa-spin ml-1"></i> 
                            <span class="text-xs">جاري معالجة طلب التمويل...</span>
                        </div>
                    `;
                }
            } else {
                // رسالة للبائع في حالة CREATED
                buttonsHTML = `
                    <div class="bg-green-900/20 border border-green-500/30 rounded-lg p-3">
                        <p class="text-green-300 text-xs text-center">
                            💬 يمكنك إرسال بيانات الحساب للمشتري عبر الرسائل
                        </p>
                    </div>
                `;
            }
            break;
        case 'FUNDED':
            statusMsg = '💰 تم تأكيد استلام البيانات - الصفقة في انتظار موافقة الإدارة';
            // مسح حالة الضغط على زر التمويل عند تغيير الحالة
            localStorage.removeItem(`deal_${deal.id}_fund_clicked`);
            buttonsHTML = `
                <div class="bg-purple-900/20 border border-purple-500/30 rounded-lg p-3">
                    <p class="text-purple-300 text-xs text-center">
                        ⏳ الصفقة قيد المراجعة من قبل الإدارة - يمكنكم التواصل لأي استفسارات
                    </p>
                </div>
            `;
            break;
        case 'ON_HOLD':
            statusMsg = 'الصفقة معلقة. في انتظار التسليم من البائع.';
            buttonsHTML = '<button id="deliver-btn" class="btn">تم التسليم</button>';
            break;
        case 'DELIVERED':
            statusMsg = 'تم التسليم. في انتظار تأكيد المشتري أو تحرير الأموال من قبل من بدأ الصفقة.';
            // إظهار زر تحرير الأموال فقط لمن بدأ الصفقة
            if (window.userId && deal.deal_initiator_id && window.userId == deal.deal_initiator_id) {
                // التحقق من حالة معالجة تحرير الأموال من قاعدة البيانات
                const hasReleaseFundsProcessing = deal.release_funds_processing === true || deal.release_funds_processing === 1;
                
                if (!hasReleaseFundsProcessing) {
                    buttonsHTML = `
                        <div class="flex justify-center">
                            <button id="release-funds-btn" data-deal-id="${deal.id}" class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-xs">
                                <i class="fa-solid fa-money-bill-wave ml-1"></i> تحرير الأموال
                            </button>
                        </div>
                    `;
                } else {
                    // إظهار رسالة معالجة مع إبقاء الزر ظاهراً
                    buttonsHTML = `
                        <div class="flex flex-col gap-2 justify-center">
                            <div class="text-center text-yellow-300 bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-2">
                                <i class="fa-solid fa-spinner fa-spin ml-1"></i> 
                                <span class="text-xs">جاري معالجة طلب تحرير الأموال...</span>
                            </div>
                            <button id="release-funds-btn" data-deal-id="${deal.id}" class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-xs">
                                <i class="fa-solid fa-money-bill-wave ml-1"></i> تحرير الأموال
                            </button>
                        </div>
                    `;
                }
            } else if (window.userId && window.userId == deal.buyer_id) {
                buttonsHTML = `
                    <button id="confirm-btn" data-deal-id="${deal.id}" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-sm">
                        <i class="fa-solid fa-check ml-1"></i> تأكيد الاستلام (FUNDED)
                    </button>
                    <div class="text-xs text-green-300 mt-2 text-center">
                        ✅ سيتم تحويل حالة الصفقة إلى FUNDED عند التأكيد
                    </div>
                `;
            }
            break;
        case 'RELEASED':
            statusMsg = '✅ تم تحرير الأموال - الصفقة مكتملة';
            buttonsHTML = `
                <div class="bg-green-900/20 border border-green-500/30 rounded-lg p-3">
                    <p class="text-green-300 text-xs text-center">
                        🎉 تمت الصفقة بنجاح! تم تحرير الأموال للبائع
                    </p>
                </div>
            `;
            break;
        case 'REFUNDED':
            statusMsg = '↩️ تم استرداد الأموال';
            buttonsHTML = `
                <div class="bg-blue-900/20 border border-blue-500/30 rounded-lg p-3">
                    <p class="text-blue-300 text-xs text-center">
                        💰 تم استرداد الأموال إلى المشتري
                    </p>
                </div>
            `;
            break;
        case 'DISPUTED':
            statusMsg = '⚠️ الصفقة محل نزاع';
            buttonsHTML = `
                <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-3">
                    <p class="text-red-300 text-xs text-center">
                        🔍 الصفقة قيد المراجعة من قبل فريق الدعم
                    </p>
                </div>
            `;
            break;
        case 'CANCELLED':
        case 'CANCELED':
            statusMsg = '❌ تم إلغاء الصفقة';
            buttonsHTML = `
                <div class="bg-red-900/30 border border-red-500/50 rounded-lg p-3">
                    <p class="text-red-300 text-sm text-center">
                        🚫 تم إلغاء هذه الصفقة - لا يمكن إجراء أي عمليات عليها
                    </p>
                </div>
            `;
            break;
        case 'COMPLETED':
            statusMsg = '✅ تم إكمال الصفقة بنجاح - تمت الموافقة من الإدارة';
            buttonsHTML = `
                <div class="bg-green-900/30 border border-green-500/50 rounded-lg p-3">
                    <p class="text-green-300 text-sm text-center">
                        🎉 تهانينا! تم إكمال الصفقة بنجاح وتمت الموافقة عليها من الإدارة
                    </p>
                    <p class="text-green-400 text-xs text-center mt-2">
                        💬 يمكنكم الاستمرار في التواصل لأي استفسارات مستقبلية
                    </p>
                </div>
            `;
            break;
        default:
            statusMsg = '❓ حالة غير معروفة';
            buttonsHTML = `
                <div class="bg-gray-900/30 border border-gray-500/50 rounded-lg p-3">
                    <p class="text-gray-300 text-sm text-center">
                        ❓ حالة الصفقة غير واضحة - يرجى التواصل مع الدعم الفني
                    </p>
                </div>
            `;
    }

    statusMessage.textContent = statusMsg;
    
    // تم إزالة زر إنهاء المناقشة - لا نضيف أي أزرار إضافية
    // buttonsHTML يحتوي بالفعل على الأزرار المناسبة لكل حالة
    
    actionButtons.innerHTML = buttonsHTML;
    
    // إظهار قسم الصفقة
    const dealSection = document.getElementById('deal-status-section');
    if (dealSection) {
        dealSection.classList.remove('hidden');
        dealSection.style.display = 'block';
    }

    // Add event listeners for dynamically created buttons
    setTimeout(() => {
        const confirmReceiptButton = document.getElementById('confirm-receipt-btn');
        if (confirmReceiptButton) {
            confirmReceiptButton.addEventListener('click', function() {
                const dealId = this.dataset.dealId;
                showConfirmReceiptModal(dealId);
            });
        }

        const cancelDealButton = document.getElementById('cancel-deal-btn');
        if (cancelDealButton) {
            cancelDealButton.addEventListener('click', function() {
                const dealId = this.dataset.dealId;
                showCancelDealModal(dealId);
            });
        }

        const deliverButton = document.getElementById('deliver-btn');
        if (deliverButton) {
            deliverButton.addEventListener('click', function() {
                console.log('تم التسليم');
            });
        }

        const confirmButton = document.getElementById('confirm-btn');
        if (confirmButton) {
            confirmButton.addEventListener('click', function() {
                const dealId = this.dataset.dealId;
                showConfirmReceiptModal(dealId);
            });
        }
        
        const releaseFundsButton = document.getElementById('release-funds-btn');
        if (releaseFundsButton) {
            releaseFundsButton.addEventListener('click', function() {
                const dealId = this.dataset.dealId;
                showReleaseFundsModal(dealId);
            });
        }
        
        // تم إزالة أزرار إنهاء المناقشة - لم تعد هذه الميزة متاحة
    }, 100);
}

// جلب بيانات الصفقة وتحديث الواجهة
function fetchDealAndUpdateUI(dealId) {
    fetch(`api/get_deal.php?deal_id=${dealId}`, { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.deal) {
                updateDealUI(data.deal);
            } else {
                console.error('Failed to fetch deal data:', data.error);
            }
        })
        .catch(error => console.error('Error fetching deal:', error));
}

// إضافة مستمع للأزرار باستخدام event delegation
document.addEventListener('click', function(e) {
    // زر تأكيد استلام البيانات (التمويل)
    if (e.target && e.target.id === 'confirm-receipt-btn') {
        const dealId = e.target.dataset.dealId;
        
        // حفظ حالة الضغط على الزر
        localStorage.setItem(`deal_${dealId}_fund_clicked`, 'true');
        
        // تحديث الواجهة فوراً لإظهار رسالة المعالجة
        const actionButtons = document.getElementById('deal-action-buttons');
        if (actionButtons) {
            actionButtons.innerHTML = `
                <div class="text-center text-yellow-300">
                    <i class="fa-solid fa-spinner fa-spin ml-1"></i> جاري معالجة طلب التمويل...
                </div>
            `;
        }
        
        fetch('api/fund_deal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `deal_id=${dealId}`,
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                fetchDealAndUpdateUI(dealId);
            } else {
                console.error('Failed to fund deal:', data.error);
                // في حالة الفشل، إزالة حالة الضغط
                localStorage.removeItem(`deal_${dealId}_fund_clicked`);
                fetchDealAndUpdateUI(dealId);
            }
        })
        .catch(error => {
            console.error('Error funding deal:', error);
            // في حالة الخطأ، إزالة حالة الضغط
            localStorage.removeItem(`deal_${dealId}_fund_clicked`);
            fetchDealAndUpdateUI(dealId);
        });
    }
    
    // زر إلغاء الصفقة
    if (e.target && e.target.id === 'cancel-deal-btn') {
        const dealId = e.target.dataset.dealId;
        showCancelDealModal(dealId);
    }
});

// إضافة مستمع للأزرار الأخرى
document.addEventListener('DOMContentLoaded', function() {
    console.log('Second DOMContentLoaded event fired - setting up deal buttons');
    const fundButton = document.getElementById('fund-deal-btn');
    const deliverButton = document.getElementById('deliver-deal-btn');
    const disputeButton = document.getElementById('open-dispute-btn');
    const confirmButton = document.getElementById('confirm-receipt-btn');

    if (fundButton) {
        fundButton.addEventListener('click', function() {
            const dealId = this.dataset.dealId;
            fetch('api/fund_deal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `deal_id=${dealId}`,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchDealAndUpdateUI(dealId);
                } else {
                    console.error('Failed to fund deal:', data.error);
                }
            })
            .catch(error => console.error('Error funding deal:', error));
        });
    }

    if (deliverButton) {
        deliverButton.addEventListener('click', function() {
            const dealId = this.dataset.dealId;
            fetch('api/deliver_deal.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `deal_id=${dealId}`,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchDealAndUpdateUI(dealId);
                } else {
                    console.error('Failed to deliver deal:', data.error);
                }
            })
            .catch(error => console.error('Error delivering deal:', error));
        });
    }

    if (confirmButton) {
        confirmButton.addEventListener('click', function() {
            const dealId = this.dataset.dealId;
            fetch('api/confirm_or_dispute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `deal_id=${dealId}&action=confirm`,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchDealAndUpdateUI(dealId);
                } else {
                    console.error('Failed to confirm deal:', data.error);
                }
            })
            .catch(error => console.error('Error confirming deal:', error));
        });
    }

    if (disputeButton) {
        disputeButton.addEventListener('click', function() {
            const dealId = this.dataset.dealId;
            fetch('api/confirm_or_dispute.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `deal_id=${dealId}&action=dispute`,
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    fetchDealAndUpdateUI(dealId);
                } else {
                    console.error('Failed to open dispute:', data.error);
                }
            })
            .catch(error => console.error('Error opening dispute:', error));
        });
    }

    // Ensure "Start Deal" button is always visible
    const sendMoneyBtn = document.getElementById('send-money-btn');
    let startDealBtn = document.getElementById('start-deal-btn');

    if (!startDealBtn) {
        // Create the "Start Deal" button if it doesn't exist
        startDealBtn = document.createElement('button');
        startDealBtn.id = 'start-deal-btn';
        startDealBtn.textContent = 'بدء الصفقة';
        startDealBtn.className = 'p-2 rounded-full neon-btn text-black text-sm hover:opacity-80 transition';

        // Insert the button next to "Send Money" button
        if (sendMoneyBtn && sendMoneyBtn.parentNode) {
            sendMoneyBtn.parentNode.insertBefore(startDealBtn, sendMoneyBtn.nextSibling);
        }
    }

    

    // Ensure the button is always visible
    startDealBtn.style.display = 'inline-block';

    // Fetch conversations and messages periodically
    setInterval(() => {
        fetchConversations();
        if (window.ACTIVE_CHAT_USER) {
            fetchMessages();
        }
    }, 5000);

    // Update user status on page load and unload
    fetch('api/update_status.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=online'
    });

    window.addEventListener('beforeunload', function() {
        navigator.sendBeacon && navigator.sendBeacon('api/update_status.php', new URLSearchParams({ action: 'offline' }));
    });

    // Initialize ACTIVE_CHAT_USER from URL or sessionStorage
    try {
        if (!window.ACTIVE_CHAT_USER) {
            const urlParams = new URLSearchParams(window.location.search);
            const conversationId = urlParams.get('conversation_id');
            const sellerId = urlParams.get('seller_id');
            const userId = urlParams.get('user_id');
            
            if (conversationId) {
                // إذا كان هناك conversation_id، جلب معلومات المحادثة
                fetch(`api/get_conversation_info.php?conversation_id=${conversationId}`, { credentials: 'include' })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.other_user) {
                            window.ACTIVE_CHAT_USER = data.other_user.id;
                            window.CONVERSATION_ID = conversationId;
                            window.LINKED_ACCOUNT = data.account;
                            
                            // تحميل حالة الصفقة النشطة للمحادثة باستخدام conversation_id
                            loadActiveDeal(window.CONVERSATION_ID);
                            
                            fetchUserProfile(window.ACTIVE_CHAT_USER, function(profile) {
                                if (profile) {
                                    console.log('User profile loaded:', profile);
                                }
                            });
                            
                            // عرض معلومات الحساب المرتبط
                            displayLinkedAccountInfo();
                            
                            // تحميل الصفقة النشطة إن وجدت
                            loadActiveDeal(window.CONVERSATION_ID);
                            
                            fetchMessages();
                        }
                    })
                    .catch(e => console.warn('Error loading conversation:', e));
            } else {
                // استخدام الطريقة القديمة
                window.ACTIVE_CHAT_USER = sellerId || userId || sessionStorage.getItem('ACTIVE_CHAT_USER');
                
                if (window.ACTIVE_CHAT_USER) {
                    // البحث عن conversation_id الفعلي باستخدام API الجديد
                    if (window.buyerId) {
                        fetch(`api/get_conversation_by_users.php?buyer_id=${window.buyerId}&seller_id=${window.ACTIVE_CHAT_USER}`, { credentials: 'include' })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.conversation_id) {
                                    window.CONVERSATION_ID = data.conversation_id;
                                    // تحميل حالة الصفقة النشطة باستخدام conversation_id الصحيح
                                    loadActiveDeal(data.conversation_id);
                                } else {
                                    // في حالة عدم وجود محادثة، استخدم user_id كبديل
                                    loadActiveDeal(window.ACTIVE_CHAT_USER);
                                }
                            })
                            .catch(() => {
                                // في حالة فشل الطلب، استخدم user_id كبديل
                                loadActiveDeal(window.ACTIVE_CHAT_USER);
                            });
                    } else {
                        // إذا لم يكن buyerId متوفراً، استخدم user_id كبديل
                        loadActiveDeal(window.ACTIVE_CHAT_USER);
                    }
                    
                    fetchUserProfile(window.ACTIVE_CHAT_USER, function(profile) {
                        if (profile) {
                            console.log('User profile loaded:', profile);
                        }
                    });
                    fetchMessages();
                }
            }
        }
    } catch (e) {
        console.warn('Error initializing ACTIVE_CHAT_USER:', e);
    }
});

// تعريف buyerId من السيشن
console.log('PHP_BUYER_ID value:', typeof PHP_BUYER_ID !== 'undefined' ? PHP_BUYER_ID : 'undefined');
    window.buyerId = typeof PHP_BUYER_ID !== 'undefined' && PHP_BUYER_ID !== '' ? PHP_BUYER_ID : null;

// تأكد من أن buyerId معرف قبل استخدامه
if (!window.buyerId) {
    console.warn('المستخدم غير مسجل الدخول. بعض الوظائف قد لا تعمل.');
}

// دالة لعرض معلومات الحساب المرتبط بالمحادثة
function displayLinkedAccountInfo() {
    const linkedAccountInfo = document.getElementById('linked-account-info');
    const linkedAccountTitle = document.getElementById('linked-account-title');
    const linkedAccountPrice = document.getElementById('linked-account-price');
    
    if (!linkedAccountInfo || !linkedAccountTitle || !linkedAccountPrice) {
        return;
    }
    
    if (window.LINKED_ACCOUNT && window.LINKED_ACCOUNT.id) {
        const account = window.LINKED_ACCOUNT;
        linkedAccountTitle.textContent = account.title || 'حساب غير محدد';
        linkedAccountPrice.textContent = account.price || '0';
        linkedAccountInfo.classList.remove('hidden');
        
        console.log('Displaying linked account info:', account);
    } else {
        linkedAccountInfo.classList.add('hidden');
    }
}

// دالة حفظ حالة الصفقة في localStorage
function saveDealToLocalStorage(deal) {
    if (!deal || !window.ACTIVE_CHAT_USER) return;
    
    const dealData = {
        deal: deal,
        timestamp: Date.now(),
        conversationId: window.ACTIVE_CHAT_USER,
        lastUpdate: new Date().toISOString()
    };
    
    // حفظ الصفقة للمحادثة الحالية
    localStorage.setItem(`active_deal_${window.ACTIVE_CHAT_USER}`, JSON.stringify(dealData));
    
    // حفظ قائمة بجميع الصفقات النشطة
    const activeDeals = JSON.parse(localStorage.getItem('all_active_deals') || '{}');
    activeDeals[window.ACTIVE_CHAT_USER] = {
        dealId: deal.id,
        status: deal.status,
        timestamp: Date.now(),
        lastUpdate: new Date().toISOString()
    };
    localStorage.setItem('all_active_deals', JSON.stringify(activeDeals));
}

// دالة استعادة حالة الصفقة من localStorage
function restoreDealFromLocalStorage(conversationId) {
    if (!conversationId) return null;
    
    try {
        const savedDeal = localStorage.getItem(`active_deal_${conversationId}`);
        if (savedDeal) {
            const dealData = JSON.parse(savedDeal);
            // التحقق من أن البيانات ليست قديمة جداً (أكثر من 24 ساعة)
            const maxAge = 24 * 60 * 60 * 1000; // 24 ساعة
            if (Date.now() - dealData.timestamp < maxAge) {
                return dealData.deal;
            } else {
                // حذف البيانات القديمة
                localStorage.removeItem(`active_deal_${conversationId}`);
            }
        }
    } catch (error) {
        console.error('Error restoring deal from localStorage:', error);
        localStorage.removeItem(`active_deal_${conversationId}`);
    }
    
    return null;
}

// إضافة دالة لتحميل الصفقة النشطة
function loadActiveDeal(conversationId) {
    if (!conversationId) return;
    
    // محاولة استعادة البيانات من localStorage أولاً
    const restoredDeal = restoreDealFromLocalStorage(conversationId);
    if (restoredDeal) {
        console.log('استعادة بيانات الصفقة من localStorage');
        updateDealUI(restoredDeal);
        // لا نعيد return هنا لنسمح بتحديث البيانات من الخادم أيضاً
    }
    
    fetch(`api/get_active_deal.php?conversation_id=${conversationId}`, { credentials: 'include' })
        .then(response => response.json())
        .then(data => {
            const dealSection = document.getElementById('deal-status-section');
            if (data.success && data.deal) {
                updateDealUI(data.deal);
            } else {
                // إزالة البيانات المحفوظة إذا لم تعد هناك صفقة نشطة
                localStorage.removeItem(`active_deal_${conversationId}`);
                if (dealSection) {
                    dealSection.classList.add('hidden');
                    dealSection.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error loading active deal:', error);
            // في حالة فشل الطلب، نحتفظ بالبيانات المحفوظة إذا كانت موجودة
            if (!restoredDeal) {
                const dealSection = document.getElementById('deal-status-section');
                if (dealSection) {
                    dealSection.classList.add('hidden');
                    dealSection.style.display = 'none';
                }
            }
        });
}

// دالة استعادة بيانات الصفقة المحفوظة عند تحميل الصفحة
function restoreSavedDealData() {
    if (!window.ACTIVE_CHAT_USER) {
        return;
    }
    
    const restoredDeal = restoreDealFromLocalStorage(window.ACTIVE_CHAT_USER);
    if (restoredDeal) {
        console.log('استعادة بيانات الصفقة المحفوظة');
        updateDealUI(restoredDeal);
    }
}

// دالة عرض مودال تأكيد استلام البيانات
function showConfirmReceiptModal(dealId) {
    // منع فتح أكثر من modal في نفس الوقت
    if (window.MODAL_OPEN) {
        console.log('Modal already open, preventing duplicate');
        return;
    }
    
    window.MODAL_OPEN = true;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-xl shadow-2xl max-w-sm w-full mx-auto transform transition-all duration-300 scale-95 hover:scale-100">
            <div class="p-4">
                <h3 class="text-base font-semibold text-white mb-3 text-center">تأكيد الاستلام</h3>
                <p class="text-gray-300 text-xs mb-3 text-center leading-relaxed">هل تأكدت من استلام بيانات الحساب بشكل صحيح؟</p>
                
                <div class="bg-green-900/20 border border-green-500/30 rounded-lg p-2 mb-2">
                    <p class="text-green-300 text-xs text-center">
                        ✅ سيتم تحويل حالة الصفقة إلى FUNDED بعد التأكيد
                    </p>
                </div>
                
                <div class="bg-yellow-900/20 border border-yellow-500/30 rounded-lg p-2 mb-4">
                    <p class="text-yellow-300 text-xs text-center leading-relaxed">
                        ⚠️ تحذير: بعد التأكيد لا يمكن التراجع عن هذه الخطوة وستتم مراجعة الصفقة من قبل الإدارة
                    </p>
                </div>
                
                <div class="flex gap-2 justify-center">
                    <button id="cancel-modal" class="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-xs flex-1">
                        إلغاء
                    </button>
                    <button id="confirm-modal" class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-xs flex-1">
                        <i class="fa-solid fa-check ml-1"></i> تأكيد
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // دالة إغلاق الـ modal
    const closeModal = () => {
        if (modal && modal.parentNode) {
            document.body.removeChild(modal);
        }
        window.MODAL_OPEN = false;
    };
    
    modal.querySelector('#cancel-modal').addEventListener('click', closeModal);
    
    modal.querySelector('#confirm-modal').addEventListener('click', () => {
        confirmDealReceipt(dealId);
        closeModal();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

// دالة عرض مودال إلغاء الصفقة
function showCancelDealModal(dealId) {
    // منع فتح أكثر من modal في نفس الوقت
    if (window.MODAL_OPEN) {
        console.log('Modal already open, preventing duplicate');
        return;
    }
    
    window.MODAL_OPEN = true;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-xl shadow-2xl max-w-sm w-full mx-auto transform transition-all duration-300 scale-95 hover:scale-100">
            <div class="p-4">
                <h3 class="text-base font-semibold text-white mb-3 text-center">إلغاء الصفقة</h3>
                <p class="text-gray-300 text-xs mb-3 text-center leading-relaxed">هل أنت متأكد من رغبتك في إلغاء هذه الصفقة؟</p>
                
                <div class="bg-blue-900/20 border border-blue-500/30 rounded-lg p-2 mb-4">
                    <p class="text-blue-300 text-xs text-center">
                        ℹ️ سيتم إرجاع الأموال المعلقة إلى محفظتك
                    </p>
                </div>
                
                <div class="flex gap-2 justify-center">
                    <button id="cancel-modal" class="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-xs flex-1">
                        تراجع
                    </button>
                    <button id="confirm-cancel" class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-xs flex-1">
                        <i class="fa-solid fa-times ml-1"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // دالة إغلاق الـ modal
    const closeModal = () => {
        if (modal && modal.parentNode) {
            document.body.removeChild(modal);
        }
        window.MODAL_OPEN = false;
    };
    
    modal.querySelector('#cancel-modal').addEventListener('click', closeModal);
    
    modal.querySelector('#confirm-cancel').addEventListener('click', () => {
        cancelDeal(dealId);
        closeModal();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

// دالة عرض مودال تحرير الأموال
function showReleaseFundsModal(dealId) {
    // منع فتح أكثر من modal في نفس الوقت
    if (window.MODAL_OPEN) {
        console.log('Modal already open, preventing duplicate');
        return;
    }
    
    window.MODAL_OPEN = true;
    
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-gray-800 rounded-xl shadow-2xl max-w-sm w-full mx-auto transform transition-all duration-300 scale-95 hover:scale-100">
            <div class="p-4">
                <h3 class="text-base font-semibold text-white mb-3 text-center">تحرير الأموال</h3>
                <p class="text-gray-300 text-xs mb-3 text-center leading-relaxed">هل أنت متأكد من رغبتك في تحرير الأموال للبائع؟</p>
                
                <div class="bg-red-900/20 border border-red-500/30 rounded-lg p-2 mb-4">
                    <p class="text-red-300 text-xs text-center">
                        ⚠️ تحذير: بعد تحرير الأموال سيتم إرسالها للبائع ولا يمكن التراجع
                    </p>
                </div>
                
                <div class="flex gap-2 justify-center">
                    <button id="cancel-release-modal" class="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-xs flex-1">
                        تراجع
                    </button>
                    <button id="confirm-release" class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition text-xs flex-1">
                        <i class="fa-solid fa-check ml-1"></i> تحرير
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // دالة إغلاق الـ modal
    const closeModal = () => {
        if (modal && modal.parentNode) {
            document.body.removeChild(modal);
        }
        window.MODAL_OPEN = false;
    };
    
    modal.querySelector('#cancel-release-modal').addEventListener('click', closeModal);
    
    modal.querySelector('#confirm-release').addEventListener('click', () => {
        releaseFunds(dealId);
        closeModal();
    });
    
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

// دالة تأكيد استلام البيانات
function confirmDealReceipt(dealId) {
    fetch('api/fund_deal_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deal_id: dealId
        }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إعادة تحميل الصفقة النشطة لتحديث الحالة
            if (window.CONVERSATION_ID) {
                loadActiveDeal(window.CONVERSATION_ID);
            }
        } else {
            alert('حدث خطأ: ' + (data.error || 'فشل في تأكيد الاستلام'));
        }
    })
    .catch(error => {
        console.error('Error confirming deal receipt:', error);
        alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
    });
}

// دالة إلغاء الصفقة
function cancelDeal(dealId) {
    fetch('api/cancel_deal_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({deal_id: dealId}),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // إعادة تحميل الصفقة النشطة لتحديث الحالة
            if (window.CONVERSATION_ID) {
                loadActiveDeal(window.CONVERSATION_ID);
            }
        } else {
            alert('حدث خطأ: ' + (data.error || 'فشل في إلغاء الصفقة'));
        }
    })
    .catch(error => {
        console.error('Error canceling deal:', error);
        alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
    });
}

// دالة تحرير الأموال
function releaseFunds(dealId) {
    // لا حاجة لحفظ الحالة في localStorage، سيتم التحكم من قاعدة البيانات
    
    // تحديث الواجهة فوراً لإظهار حالة المعالجة
    if (window.CONVERSATION_ID) {
        loadActiveDeal(window.CONVERSATION_ID);
    }
    
    fetch('api/release_funds.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `deal_id=${dealId}`,
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        // الحالة ستُحدث تلقائياً من قاعدة البيانات
        
        if (data.success) {
            // إعادة تحميل الصفقة النشطة لتحديث الحالة
            if (window.CONVERSATION_ID) {
                loadActiveDeal(window.CONVERSATION_ID);
            }
            alert('تم تحرير الأموال بنجاح');
        } else {
            alert('حدث خطأ: ' + (data.error || 'فشل في تحرير الأموال'));
            // إعادة تحميل الواجهة لإزالة حالة المعالجة
            if (window.CONVERSATION_ID) {
                loadActiveDeal(window.CONVERSATION_ID);
            }
        }
    })
    .catch(error => {
        console.error('Error releasing funds:', error);
        // الحالة ستُحدث تلقائياً من قاعدة البيانات
        alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
        // إعادة تحميل الواجهة لإزالة حالة المعالجة
        if (window.CONVERSATION_ID) {
            loadActiveDeal(window.CONVERSATION_ID);
        }
    });
}

// وظائف الإبلاغ عن المحادثة
document.addEventListener('DOMContentLoaded', function() {
    const reportBtn = document.getElementById('report-conversation-btn');
    const reportModal = document.getElementById('report-modal');
    const reportModalContent = document.getElementById('report-modal-content');
    const closeReportModal = document.getElementById('close-report-modal');
    const cancelReport = document.getElementById('cancel-report');
    const reportForm = document.getElementById('report-form');
    const reportReason = document.getElementById('report-reason');

    // فتح نافذة الإبلاغ
    if (reportBtn) {
        reportBtn.addEventListener('click', function() {
            if (!window.ACTIVE_CHAT_USER) {
                showPlaceholder('يجب اختيار محادثة أولاً');
                return;
            }
            
            reportModal.classList.remove('hidden');
            setTimeout(() => {
                reportModal.classList.add('show');
            }, 10);
            reportReason.value = '';
            reportReason.focus();
        });
    }

    // إغلاق نافذة الإبلاغ
    function closeReportModalFunc() {
        reportModal.classList.remove('show');
        setTimeout(() => {
            reportModal.classList.add('hidden');
        }, 300);
    }

    if (closeReportModal) {
        closeReportModal.addEventListener('click', closeReportModalFunc);
    }

    if (cancelReport) {
        cancelReport.addEventListener('click', closeReportModalFunc);
    }

    // إغلاق النافذة عند النقر خارجها
    if (reportModal) {
        reportModal.addEventListener('click', function(e) {
            if (e.target === reportModal) {
                closeReportModalFunc();
            }
        });
    }

    // إرسال البلاغ
    if (reportForm && !reportForm.hasReportListener) {
        reportForm.hasReportListener = true; // منع إضافة مستمعين متعددين
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const reason = reportReason.value.trim();
            if (!reason) {
                showPlaceholder('يرجى كتابة سبب الإبلاغ');
                return;
            }

            if (reason.length < 10) {
                showPlaceholder('يجب أن يكون سبب الإبلاغ 10 أحرف على الأقل');
                return;
            }

            if (!window.ACTIVE_CHAT_USER) {
                showPlaceholder('لا يمكن تحديد المحادثة المراد الإبلاغ عنها');
                return;
            }

            // تعطيل الزر أثناء الإرسال
            const submitBtn = document.getElementById('submit-report');
            if (submitBtn.disabled) {
                return; // منع الإرسال المتكرر إذا كان الزر معطلاً
            }
            
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin ml-1"></i>جاري الإرسال...';

            // إرسال البلاغ
            fetch('api/create_report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'include',
                body: JSON.stringify({
                    reported_user_id: window.ACTIVE_CHAT_USER,
                    conversation_id: window.CONVERSATION_ID,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showPlaceholder('تم إرسال البلاغ بنجاح. سيتم مراجعته من قبل فريق الإدارة.');
                    closeReportModalFunc();
                } else {
                    showPlaceholder(data.message || data.error || 'حدث خطأ أثناء إرسال البلاغ');
                }
            })
            .catch(error => {
                console.error('Error submitting report:', error);
                showPlaceholder('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقاً.');
            })
            .finally(() => {
                // إعادة تفعيل الزر
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
});