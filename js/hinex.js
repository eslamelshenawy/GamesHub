/*
  hinex.js — Wasata helpers
  - Encapsulated in Wasata namespace to avoid polluting globals
  - Exposes Wasata.renderCards, Wasata.toggleFavoriteIndex, Wasata.fetchAndRenderAccounts, Wasata.filterCards
  - Backward compatibility: window.toggleFavoriteIndex points to Wasata.toggleFavoriteIndex
*/

(function(global){
  var Wasata = {};

  // Dark mode functionality removed for cleaner UI

  // toast helper - prefer global showPlaceholder if available
  Wasata.showPlaceholder = function(msg){
    if (typeof global.showPlaceholder === 'function') { global.showPlaceholder(msg); return; }
    var t = document.getElementById('toast'); if (!t) return; t.textContent = msg || 'الميزة غير متاحة الآن — قريبًا ✨'; t.style.display='block'; t.style.opacity='1'; clearTimeout(window._toastTimer); window._toastTimer = setTimeout(function(){ t.style.transition='opacity 300ms'; t.style.opacity='0'; setTimeout(function(){t.style.display='none';},300); }, 2200);
  };

  Wasata.toggleFavoriteIndex = function(event, adId, btn) {
    try {
      if (event && event.preventDefault) event.preventDefault();
      var icon = btn.querySelector('i');
      var currentlyFav = btn.classList.contains('active-fav');
      var setFavUI = function(fav){ if (fav) { btn.classList.add('active-fav'); if (icon) icon.className = 'fas fa-heart text-2xl'; } else { btn.classList.remove('active-fav'); if (icon) icon.className = 'far fa-heart text-2xl'; } };
      setFavUI(!currentlyFav);
      fetch('/api/api/get_csrf.php', { credentials: 'include' })
        .then(function(r){ return r.json(); })
        .then(function(csrfData){ var csrf = csrfData && csrfData.csrf_token ? csrfData.csrf_token : ''; return fetch('/api/api/favorite.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ account_id: adId, csrf_token: csrf }), credentials: 'include' }); })
        .then(function(res){ 
          if (res.status === 401) {
            setFavUI(currentlyFav);
            try { sessionStorage.setItem('post_auth_return', window.location.pathname+window.location.search+window.location.hash); } catch(e) {}
            // استخدم باراميتر return في الرابط
            var currentPath = window.location.pathname+window.location.search+window.location.hash;
            var returnParam = encodeURIComponent(currentPath);
            window.location.href = 'login.html?return=' + returnParam;
            throw new Error('unauthenticated');
          }
          return res.json();
        })
        .then(function(data){ if (!data || !data.success) { setFavUI(currentlyFav); Wasata.showPlaceholder(data && data.error ? data.error : (data && data.message ? data.message : 'فشل تحديث المفضلة')); } else { if (typeof data.favorited !== 'undefined') setFavUI(!!data.favorited); } })
        .catch(function(err){ if (err && err.message === 'unauthenticated') return; setFavUI(currentlyFav); console.error('favorite toggle error', err); Wasata.showPlaceholder('خطأ في الاتصال بالخادم'); });
    } catch(e){ console.error(e); }
  };

  Wasata.renderCards = function(accounts, limit){
    var cardsContainer = document.getElementById('cards'); if (!cardsContainer) return;
    cardsContainer.innerHTML = ''; var visible = 0;
    var accountsToShow = limit ? accounts.slice(0, limit) : accounts;
    accountsToShow.forEach(function(acc){
      var img = acc.images && acc.images.length > 0 ? acc.images[0] : 'uploads/default-game-image.svg';
      var game = acc.game_name || '';
      var price = acc.price || '';
      var desc = acc.description || '';
      var title = game || (desc.length > 40 ? desc.substring(0,40) + '...' : desc);
      var id = acc.id; var isFav = acc.is_favorite ? 'active-fav' : '';
      cardsContainer.innerHTML += '\n    <article class="p-0 rounded-xl overflow-hidden card-bg">\n      <div class="relative">\n        <img src="'+img+'" alt="account" class="w-full h-44 object-cover" />\n        <button class="absolute top-3 left-3 z-10 card-favorite-icon '+isFav+'" title="اضف الى المفضلة" onclick="Wasata.toggleFavoriteIndex(event, '+id+', this)">\n          <i class="fa'+(acc.is_favorite ? 's' : 'r')+' fa-heart text-2xl" style="color:#ff6b81;"></i>\n        </button>\n        <div class="absolute top-3 left-3 md:left-20 px-2 py-1 rounded-md text-xs font-semibold" style="background:rgba(0,0,0,0.5)">'+game+'</div>\n      </div>\n      <div class="p-4">\n        <h3 class="font-bold text-lg">'+title+'</h3>\n        <p class="mt-1 muted text-sm">'+desc+'</p>\n        <div class="mt-3 flex items-center justify-between">\n          <div>\n            <div class="text-xl font-bold" style="color:var(--neon-blue)">$'+price+'</div>\n          </div>\n          <div class="flex flex-col gap-2">\n            <a href="moredetails.html?id='+id+'" class="px-3 py-2 rounded-lg neon-btn text-black font-semibold">اشترِ الآن</a>\n          </div>\n        </div>\n      </div>\n    </article>\n    ';
      visible++;
    });
    var countEl = document.getElementById('count'); if (countEl) countEl.textContent = visible;
  };

  Wasata.fetchAndRenderAccounts = function(limit){ 
    fetch('/api/api/get_all_accounts.php').then(function(res){ return res.json(); }).then(function(data){ 
      // Check if we're on the homepage by looking for specific elements
      var isHomepage = window.location.pathname === '/' || window.location.pathname.endsWith('index.html') || window.location.pathname === '/index.html';
      var actualLimit = isHomepage ? (limit || 6) : undefined;
      Wasata.renderCards(data, actualLimit); 
    }); 
  };

  Wasata.filterCards = function(){
    var q = (document.getElementById('search') && document.getElementById('search').value || document.getElementById('mobile-search') && document.getElementById('mobile-search').value || '').toLowerCase().trim();
    var game = (document.getElementById('filter-game') && document.getElementById('filter-game').value || '').toLowerCase();
    var cards = Array.from(document.querySelectorAll('#cards article'));
    var visible = 0; cards.forEach(function(card){ var title = (card.querySelector('h3') && card.querySelector('h3').textContent.toLowerCase()||''); var meta = (card.querySelector('.muted') && card.querySelector('.muted').textContent.toLowerCase()||''); var tag = (card.querySelector('.absolute div, .absolute') && (card.querySelector('.absolute div, .absolute').textContent || '').toLowerCase()||''); var match=true; if (q && !(title.includes(q) || meta.includes(q) || tag.includes(q))) match=false; if (game && !card.innerText.toLowerCase().includes(game)) match=false; card.style.display = match ? 'block' : 'none'; if(match) visible++; }); document.getElementById('count').textContent = visible;
  };

  // init hooks
  document.addEventListener('DOMContentLoaded', function(){
    if (document.getElementById('cards')){
      Wasata.fetchAndRenderAccounts();
      var params = new URLSearchParams(window.location.search);
      if (params.get('success')==='1') Wasata.showPlaceholder('تم نشر اعلانك بنجاح!');
      document.getElementById('search')?.addEventListener('input', Wasata.filterCards);
      document.getElementById('mobile-search')?.addEventListener('input', Wasata.filterCards);
      document.getElementById('filter-game')?.addEventListener('change', Wasata.filterCards);
    }
  });

  // expose Wasata and keep backward-compatible names
  global.Wasata = Wasata;
  global.toggleFavoriteIndex = Wasata.toggleFavoriteIndex;

})(window);
