// Sidebar helpers are centralized in js/sidebar.js (openSidebar/closeSidebar/showPlaceholder)
    // Use the global helpers provided by that file instead of redefining here.
    

    // Image carousel logic
    function changeMainImage(thumbnail) {
        try {
            if (!thumbnail) return;
            const mainImage = document.getElementById('main-image');
            const allThumbnails = document.querySelectorAll('.image-carousel img');
            if (allThumbnails && allThumbnails.length) {
                allThumbnails.forEach(img => img.classList.remove('active-thumbnail', 'border-white/20'));
                allThumbnails.forEach(img => img.classList.add('border-transparent'));
            }
            if (thumbnail.classList) {
                thumbnail.classList.add('active-thumbnail', 'border-white/20');
                thumbnail.classList.remove('border-transparent');
            }
            if (mainImage && thumbnail && thumbnail.src) mainImage.src = thumbnail.src;
        } catch (e) { /* ignore DOM update errors */ }
    }

    // تحميل بيانات الحساب المختار من localStorage وعرضها
    window.addEventListener('DOMContentLoaded', function() {
        try {
            const data = JSON.parse(localStorage.getItem('selectedAccount'));
            if (!data) return;
            // الصورة الرئيسية
            const mainImg = document.getElementById('main-image');
            if (mainImg && data.image) mainImg.src = data.image;
            // العنوان
            const title = document.querySelector('h1.text-3xl');
            if (title && data.title) title.innerText = data.title;
            // الوصف
            const desc = document.querySelector('.mb-6 .muted.leading-relaxed');
            if (desc && data.desc) desc.innerText = data.desc;
            // السعر
            const price = document.querySelector('p.text-xl.font-bold');
            if (price && data.price) price.innerText = data.price;
            // الدولة
            // country field removed
            // نوع اللعبة
            const game = document.querySelector('.flex.items-center.justify-between.mb-2 span');
            if (game && data.game) game.innerText = data.game.toUpperCase();
            // set seller id on contact button if available
            try {
                const contactBtn = document.querySelector('button[onclick="contactSeller()"]');
                if (contactBtn && data.user_id) {
                    contactBtn.dataset.sellerId = data.user_id;
                    contactBtn.onclick = function(){ contactSeller(data.user_id); };
                }
            } catch(e){}
        } catch(e) {}
    });

    // Favorite button logic
    function toggleFavorite() {
        try {
            const favoriteBtn = document.getElementById('favorite-btn');
            if (!favoriteBtn) { showPlaceholder('زر المفضلة غير متاح'); return; }
            const icon = favoriteBtn.querySelector && favoriteBtn.querySelector('i');
            if (!icon) { showPlaceholder('أيقونة المفضلة غير موجودة'); return; }
            const isFavorited = icon.classList.contains('fa-solid') || icon.classList.contains('fas');

            if (isFavorited) {
                icon.classList.remove('fa-solid', 'fas');
                icon.classList.add('fa-regular');
                showPlaceholder('تمت إزالة الحساب من المفضلة');
            } else {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid', 'fas');
                showPlaceholder('تمت إضافة الحساب إلى المفضلة!');
            }
        } catch(e){ /* ignore */ }
    }

    // Placeholder for "Contact Seller" modal
    function contactSeller(sellerId) {
        if (!sellerId) {
            showPlaceholder('معلومات البائع غير متوفرة');
            return;
        }
        
        // الحصول على معرف الحساب الحالي
        const accountId = getIdFromUrl() || 0;
        
        // إنشاء محادثة مع ربطها بالحساب
        fetch('/api/api/get_csrf.php', { credentials: 'include' })
            .then(r => r.json())
            .then(csrfData => {
                const csrf = csrfData && csrfData.csrf_token ? csrfData.csrf_token : '';
                
                const formData = new FormData();
                formData.append('user_id', sellerId);
                formData.append('account_id', accountId);
                formData.append('csrf_token', csrf);
                
                return fetch('/api/api/create_conversation.php', {
                    method: 'POST',
                    credentials: 'include',
                    body: formData
                });
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // التوجه إلى صفحة المحادثات مع معرف المحادثة
                    window.location.href = `messages.php?conversation_id=${data.conversation_id}`;
                } else {
                    // في حالة الفشل، استخدم الطريقة القديمة
                    window.location.href = `messages.php?seller_id=${encodeURIComponent(sellerId)}`;
                }
            })
            .catch(error => {
                console.error('Error creating conversation:', error);
                // في حالة الخطأ، استخدم الطريقة القديمة
                window.location.href = `messages.php?seller_id=${encodeURIComponent(sellerId)}`;
            });
    }

    // Chat modal helpers
    var chatModalPollId = null;
    // count consecutive errors while polling; if too many, stop polling to avoid spamming console
    var chatModalErrorCount = 0;

    function loadChatModalMessages(userId){
        try {
            const messagesContainer = document.getElementById('chat-modal-messages');
            if (!messagesContainer) { chatModalErrorCount++; return; }

            // proceed with fetch
        } catch (syncErr) {
            // synchronous error - increment error counter and bail
            chatModalErrorCount++;
            if (chatModalErrorCount >= 3) {
                try { if (chatModalPollId) { clearInterval(chatModalPollId); chatModalPollId = null; } } catch(e){}
                showPlaceholder('تعذر تحميل المحادثة — يرجى المحاولة لاحقاً');
            }
            return;
        }
        fetch(`api/get_messages.php?user_id=${encodeURIComponent(userId)}`, { credentials: 'include' })
          .then(r => {
              if (!r.ok) return null; // treat non-2xx as failure
              return r.json().catch(() => null);
          })
          .then(data => {
              // data must be an object with success=true and messages array
              if (!data || !data.success || !Array.isArray(data.messages)) {
                  chatModalErrorCount++;
                  if (chatModalErrorCount >= 3) {
                      try { if (chatModalPollId) { clearInterval(chatModalPollId); chatModalPollId = null; } } catch(e){}
                      showPlaceholder('تعذر تحميل المحادثة — يرجى المحاولة لاحقاً');
                  }
                  return;
              }

              // success: reset error counter and safely render messages
              chatModalErrorCount = 0;
              // if the modal was closed meanwhile, bail out
              const messagesContainerNow = document.getElementById('chat-modal-messages');
              if (!messagesContainerNow) return;
              try { messagesContainerNow.innerHTML = ''; } catch(e){}
              const me = data.me;
              for (let i = 0; i < data.messages.length; i++){
                  const m = data.messages[i];
                  try {
                      if (!m) continue;
                      const senderId = (typeof m.sender_id !== 'undefined') ? m.sender_id : (m.from || m.sender || 0);
                      const isSent = senderId == me;
                      const wrapper = document.createElement('div');
                      wrapper.className = 'p-2 ' + (isSent ? 'text-right' : 'text-left');
                      const bubble = document.createElement('div');
                      bubble.className = 'inline-block p-2 rounded ' + (isSent ? 'bg-green-600 text-black' : 'bg-white/5');
                      const textNode = document.createElement('div');
                      textNode.textContent = m.message_text || m.text || '';
                      bubble.appendChild(textNode);
                      wrapper.appendChild(bubble);
                      messagesContainerNow.appendChild(wrapper);
                  } catch(perMsgErr){ /* skip malformed message but continue */ }
              }
              try { messagesContainerNow.scrollTop = messagesContainerNow.scrollHeight; } catch(e){}
          })
          .catch(() => {
              // network or parsing error: increment error counter and possibly stop polling
              chatModalErrorCount++;
              if (chatModalErrorCount >= 3) {
                  try { if (chatModalPollId) { clearInterval(chatModalPollId); chatModalPollId = null; } } catch(e){}
                  showPlaceholder('تعذر تحميل المحادثة — يرجى المحاولة لاحقاً');
              }
          });
    }
    function openChatModal(userId, displayName){
        const modal = document.getElementById('chat-modal');
        if (!modal) { showPlaceholder('نموذج المحادثة غير متوفر'); return; }
        const messagesContainer = document.getElementById('chat-modal-messages');
        const title = modal.querySelector('h4');
        if (title) title.textContent = 'محادثة مع ' + (displayName || 'البائع');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        // store active chat user for modal
        modal.dataset.userId = userId;
    // set hidden seller id input value for form submission
    try { const hidden = document.getElementById('chat-modal-seller-id'); if (hidden) hidden.value = userId; } catch(e){}
    if (messagesContainer) {
        messagesContainer.innerHTML = '';
        const p = document.createElement('div'); p.className = 'muted text-sm p-2'; p.textContent = 'جاري تحميل المحادثة...';
        messagesContainer.appendChild(p);
    }
    // initial load
    // reset error counter when user explicitly opens the modal
    chatModalErrorCount = 0;
    loadChatModalMessages(userId);
    // start polling for new messages every 3s (only if not already polling and error count is low)
    try {
        if (!chatModalPollId && chatModalErrorCount < 3) {
            chatModalPollId = setInterval(function(){
                try { loadChatModalMessages(userId); }
                catch(err){
                    // catch any synchronous errors inside loadChatModalMessages
                    chatModalErrorCount++;
                    if (chatModalErrorCount >= 3) {
                        try { if (chatModalPollId) { clearInterval(chatModalPollId); chatModalPollId = null; } } catch(e){}
                        showPlaceholder('تعذر تحميل المحادثة — يرجى المحاولة لاحقاً');
                    }
                }
            }, 3000);
        }
    } catch(e){}
        // close handler
        const closeBtn = document.getElementById('chat-modal-close');
        if (closeBtn) closeBtn.onclick = closeChatModal;
    }

    function closeChatModal(){
        const modal = document.getElementById('chat-modal');
        if (!modal) return;
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    // stop polling when modal is closed
    try { if (chatModalPollId) { clearInterval(chatModalPollId); chatModalPollId = null; } } catch(e){}
    // reset error counter on close so future opens can attempt polling again
    try { chatModalErrorCount = 0; } catch(e){}
    }

    function sendToSeller(e){
        try {
            e.preventDefault();
            const modal = document.getElementById('chat-modal');
            if (!modal) { showPlaceholder('نموذج المحادثة غير متوفر'); return; }
            const userId = modal.dataset && modal.dataset.userId ? parseInt(modal.dataset.userId) : 0;
            if (!userId) { showPlaceholder('محدد المستلم غير صالح'); return; }
            const input = document.getElementById('chat-modal-input');
            if (!input) { showPlaceholder('حقل الرسالة غير موجود'); return; }
            const text = (input.value || '').trim();
            if (!text) return;
            const btn = modal.querySelector('button[type="submit"]');
            if (!btn) { showPlaceholder('زر الإرسال غير موجود'); return; }
            btn.disabled = true;
        // get csrf and send message
        fetch('/api/api/get_csrf.php', { credentials: 'include' })
          .then(r => {
              if (!r.ok) throw new Error('Failed to fetch CSRF token: ' + r.status);
              return r.json();
          })
          .then(cs => {
              const token = cs && cs.csrf_token ? cs.csrf_token : '';
              // include seller_id explicitly for compatibility
              const hidden = document.getElementById('chat-modal-seller-id');
              const sellerId = hidden && hidden.value ? hidden.value : (modal.dataset.userId || userId);
              const body = new URLSearchParams({ to: userId, seller_id: sellerId, message: text, csrf_token: token });
              return fetch('/api/api/send_message.php', { method: 'POST', credentials: 'include', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() });
          })
          .then(r => r.text())
          .then(textBody => {
              let parsed = null;
              try { parsed = JSON.parse(textBody); } catch(e) { /* not json */ }
              return { statusOk: true, parsed: parsed, raw: textBody };
          })
          .then(result => {
              // result.parsed may be null if non-json
              const j = result.parsed || {};
              try { if (btn) btn.disabled = false; } catch(e){}
              if (j && j.success) {
                  try { input.value = ''; } catch(e){}
                  const messagesContainer = document.getElementById('chat-modal-messages');
                  if (messagesContainer) {
                      const wrapper = document.createElement('div');
                      wrapper.className = 'p-2 text-right';
                      const bubble = document.createElement('div');
                      bubble.className = 'inline-block p-2 rounded bg-green-600 text-black';
                      const txt = document.createElement('div'); txt.textContent = j.message || text;
                      bubble.appendChild(txt);
                      wrapper.appendChild(bubble);
                      messagesContainer.appendChild(wrapper);
                      messagesContainer.scrollTop = messagesContainer.scrollHeight;
                  }
                  showPlaceholder('تم إرسال الرسالة');
              } else {
                  console.warn('send_message response', j, result.raw);
                  showPlaceholder((j && j.error) ? j.error : 'فشل الإرسال');
              }
          })
          .catch(err => {
              try { if (btn) btn.disabled = false; } catch(e){}
              // suppress noisy error logging in production; show user-friendly toast
              const msg = (err && err.error) ? err.error : (err && err.message ? err.message : 'خطأ في الاتصال بالخادم');
              showPlaceholder(msg);
          });
        } catch(err) {
            // suppress unexpected error details, keep UX message
            showPlaceholder('حدث خطأ غير متوقع');
        }
    }




