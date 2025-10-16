/*
 * js/sidebar.js
 * Responsibility: load and control the site sidebar (mobile drawer, overlay, login visibility)
 * Usage: include <div id="sidebar-container"></div> in pages and add <script src="js/sidebar.js"></script>
 * Notes:
 * - Sidebar markup is loaded from `includes/sidebar.html` and cached in sessionStorage for a short time.
 * - This file exposes global helpers: openSidebar, closeSidebar, showPlaceholder, logoutUser
 *   (preferred usage through these helpers; they are minimal and centralized here).
 */

document.addEventListener('DOMContentLoaded', function() {
	var container = document.getElementById('sidebar-container');
	if (!container) return;

		// Always clear any old cached sidebar HTML to avoid stale onclick placeholders
		// Also clear when auth button is updated (version 2.0)
		try { sessionStorage.removeItem('wasata_sidebar'); sessionStorage.removeItem('wasata_sidebar_v2'); } catch(e) {}

		// Simple loading placeholder while sidebar HTML is retrieved
	container.innerHTML = '<div class="p-4 text-gray-400">جارٍ تحميل الشريط الجانبي...</div>';

	// Try sessionStorage cache first (expire after 1 hour)
	try {
		var cached = sessionStorage.getItem('wasata_sidebar');

		// If we're on the user's "إعلاناتي" page, force refresh the sidebar
		// to avoid showing an older or page-specific sidebar variation.
		try {
			var pathname = (location && location.pathname) ? location.pathname.toLowerCase() : '';
			if (pathname.indexOf('myaccount.html') !== -1) {
				try { sessionStorage.removeItem('wasata_sidebar'); } catch(e){}
				cached = null;
			}
		} catch(e) {}
		if (cached) {
			var obj = JSON.parse(cached);
			if (obj && obj.html && (Date.now() - (obj.ts || 0) < 1000 * 60 * 60)) {
				container.innerHTML = obj.html;
				// ensure overlay starts non-interactive
				setTimeout(function(){
					var overlay = document.getElementById('overlay');
					if (overlay) { overlay.style.pointerEvents = 'none'; overlay.style.display = 'none'; }
				}, 10);
				// update login state
				fetch('/api/api/check_login.php', { credentials: 'include' })
					.then(res => res.json())
					.then(data => {
						if (window.setLoginVisibility) window.setLoginVisibility(!!data.logged_in, data.name || null);
						// Update auth button state
						checkSidebarAuthState();
					})
					.catch(()=>{});
				return; // used cache
			}
		}
	} catch(e) { /* ignore storage errors */ }

	// Not cached or expired -> fetch and store
	fetch('includes/sidebar.html')
		.then(res => res.text())
		.then(html => {
			container.innerHTML = html;
			// ضمان وجود الكلاس md:translate-x-0 في عنصر sidebar
			setTimeout(function(){
				var sidebar = document.getElementById('sidebar');
				// على الكمبيوتر، أزل translate-x-full ليظهر السايد بار
				if (sidebar) {
					if (window.innerWidth >= 768) {
						sidebar.classList.remove('translate-x-full');
					}
					if (!sidebar.classList.contains('md:translate-x-0')) {
						sidebar.classList.add('md:translate-x-0');
					}
				}
				var overlay = document.getElementById('overlay');
				if (overlay) { overlay.style.pointerEvents = 'none'; overlay.style.display = 'none'; }
			}, 10);
			try { sessionStorage.setItem('wasata_sidebar', JSON.stringify({ html: html, ts: Date.now() })); } catch(e){}

						// بعد إدراج الشريط الجانبي في DOM نحدّث حالة تسجيل الدخول واسم المستخدم
						fetch('/api/api/check_login.php', { credentials: 'include' })
								.then(res => res.json())
								.then(data => {
									if (window.setLoginVisibility) window.setLoginVisibility(!!data.logged_in, data.name || null);
									// Update auth button state
									checkSidebarAuthState();
								})
								.catch(() => {});

						// منطق إظهار زر لوحة الإدارة للأدمن فقط
						fetch('/api/api/login.php?check=1', { credentials: 'include' })
							.then(res => res.json())
							.then(data => {
								if (data.logged_in && data.user_id) {
									fetch('/api/api/get_user.php', { credentials: 'include' })
										.then(r=>r.json())
										.then(u=>{
											if (u.profile && u.profile.role === 'admin') {
												var adminLink = document.getElementById('admin-panel-link');
												var adminLinkBottom = document.getElementById('admin-panel-link-bottom');
												if (adminLink) {
													adminLink.style.display = '';
													console.debug('[sidebar] admin-panel-link SHOWN');
												} else {
													console.warn('[sidebar] admin-panel-link NOT FOUND');
												}
												if (adminLinkBottom) {
													adminLinkBottom.style.display = '';
													console.debug('[sidebar] admin-panel-link-bottom SHOWN');
												} else {
													console.warn('[sidebar] admin-panel-link-bottom NOT FOUND');
												}
											} else {
												if (!u.profile) {
													console.error('[sidebar] لم يتم العثور على بيانات البروفايل للمستخدم. تحقق من استجابة get_user.php');
												} else if (typeof u.profile.role === 'undefined') {
													console.error('[sidebar] حقل role غير موجود في بيانات المستخدم. تحقق من قاعدة البيانات أو كود get_user.php');
												} else {
													console.warn('[sidebar] المستخدم ليس أدمن. role الحالي:', u.profile.role);
												}
												// إظهار رسالة للمستخدم إذا لم يظهر زر لوحة الإدارة رغم تسجيل الدخول
												var adminLink = document.getElementById('admin-panel-link');
												var adminLinkBottom = document.getElementById('admin-panel-link-bottom');
												if (adminLink && adminLink.style.display === 'none') {
													adminLink.title = 'زر لوحة الإدارة يظهر فقط للمستخدم الأدمن.';
												}
												if (adminLinkBottom && adminLinkBottom.style.display === 'none') {
													adminLinkBottom.title = 'زر لوحة الإدارة يظهر فقط للمستخدم الأدمن.';
												}
											}
										})
										.catch(e=>console.error('[sidebar] get_user.php error',e));
								} else {
									console.debug('[sidebar] not logged in or no user_id');
								}
							})
							.catch(e=>console.error('[sidebar] login.php?check=1 error',e));
		})
		.catch(()=>{
			// leave placeholder or display minimal error
			container.innerHTML = '<div class="p-4 text-red-400">تعذر تحميل الشريط الجانبي</div>';
		});

// إخفاء/إظهار عناصر تسجيل الدخول وتحديث اسم المستخدم (قابلة للاستدعاء من مكونات أخرى)
function setLoginVisibility(loggedIn, userName){
		const all = Array.from(document.querySelectorAll('a,button'));
		all.forEach(el => {
			const text = (el.textContent || '').trim();
			const href = el.getAttribute && (el.getAttribute('href') || '').toLowerCase();
			const onclick = el.getAttribute && (el.getAttribute('onclick') || '');
			const isLoginByText = /تسجيل\s*دخول/.test(text);
			const isLoginByHref = href.includes('login.html') || href.includes('/login');
			const isLoginByOnclick = onclick.includes('login.html') || onclick.toLowerCase().includes("'login.html'");
			if (loggedIn) {
				if (isLoginByText || isLoginByHref || isLoginByOnclick) {
					el.style.display = 'none';
				}
			} else {
				// show login elements when not logged in
				if (isLoginByText || isLoginByHref || isLoginByOnclick) {
					el.style.display = '';
				}
			}
		});
		// Update any username placeholders if present
		if (loggedIn && userName){
			const nameEls = document.querySelectorAll('#username-sidebar, .username');
			nameEls.forEach(e => e.textContent = userName);
		}
	}

	// إخفاء زر تسجيل الدخول إذا كان المستخدم مسجل دخول عند التحميل
	fetch('/api/api/check_login.php', { credentials: 'include' })
		.then(res => res.json())
		.then(data => {
			setLoginVisibility(!!data.logged_in, data.name || null);
		})
		.catch(() => { /* ignore errors, keep default UI */ });
});

// دوال التحكم في الشريط الجانبي (تعمل في جميع الصفحات)
function openSidebar() {
	const sidebar = document.getElementById('sidebar');
	const overlay = document.getElementById('overlay');
	if (sidebar && overlay) {
		sidebar.classList.remove('translate-x-full');
		overlay.classList.remove('hidden');
		overlay.classList.add('block');
		try {
			document.body.style.overflow = 'hidden';
			overlay.style.pointerEvents = 'auto';
			sidebar.style.pointerEvents = 'auto';
			// ensure overlay is visible for older browsers
			overlay.style.display = 'block';
		} catch(e) {}
	}
}
function closeSidebar() {
	const sidebar = document.getElementById('sidebar');
	const overlay = document.getElementById('overlay');
	if (sidebar && overlay) {
		sidebar.classList.add('translate-x-full');
		overlay.classList.add('hidden');
		overlay.classList.remove('block');
		try {
			document.body.style.overflow = '';
			overlay.style.pointerEvents = 'none';
			// hide overlay display as well
			overlay.style.display = 'none';
		} catch(e) {}
	}
}
// Click delegation so the open/close works on mobile buttons across pages
document.addEventListener('click', function(e){
	try {
		if (e.target && e.target.closest && e.target.closest('.open-sidebar-btn')) {
			e.preventDefault();
			openSidebar();
			return;
		}
		// clicking the overlay should close the sidebar
		if (e.target && e.target.id === 'overlay') {
			closeSidebar();
			return;
		}
		// منع أي رابط في السايد بار من استدعاء showPlaceholder عند الضغط
		var sidebarLink = e.target.closest && e.target.closest('#sidebar a');
		if (sidebarLink && sidebarLink.getAttribute('href') && sidebarLink.getAttribute('href') !== '#') {
			// السماح بالانتقال الفعلي فقط، لا تمنع الافتراضي ولا تعرض أي رسالة
			return;
		}
	} catch (err) {
		// ignore
	}
});
// also support touchstart for mobile devices (some browsers dispatch touch instead of click quickly)
let touchStartTime = 0;
let touchStartTarget = null;

document.addEventListener('touchstart', function(e){
	try {
		touchStartTime = Date.now();
		touchStartTarget = e.target;
		
		if (e.target && e.target.closest && e.target.closest('.open-sidebar-btn')) {
			// don't prevent default - just open the sidebar
			openSidebar();
			return;
		}
	} catch (err) {}
}, { passive: true });

document.addEventListener('touchend', function(e){
	try {
		const touchDuration = Date.now() - touchStartTime;
		// Only close sidebar if touch was on overlay, lasted more than 150ms, and didn't move much
		// This prevents accidental closing while allowing intentional closing
		if (e.target && e.target.id === 'overlay' && 
		    touchStartTarget && touchStartTarget.id === 'overlay' &&
		    touchDuration > 150) {
			closeSidebar();
			return;
		}
	} catch (err) {}
}, { passive: true });

// Prevent sidebar from closing on touch events inside the sidebar itself
document.addEventListener('touchstart', function(e){
	try {
		if (e.target && e.target.closest && e.target.closest('#sidebar')) {
			// If touching inside sidebar, don't close it
			e.stopPropagation();
		}
	} catch (err) {}
}, { passive: false });

document.addEventListener('touchend', function(e){
	try {
		if (e.target && e.target.closest && e.target.closest('#sidebar')) {
			// If touching inside sidebar, don't close it
			e.stopPropagation();
		}
	} catch (err) {}
}, { passive: false });
// Placeholder toast for features not implemented yet
function showPlaceholder(msg){
	const t = document.getElementById('toast');
	if(!t) return;
	t.textContent = msg || 'الميزة غير متاحة الآن — قريبًا ✨';
	t.style.display = 'block';
	t.style.opacity = '1';
	clearTimeout(window._toastTimer);
	window._toastTimer = setTimeout(()=>{
		t.style.transition='opacity 300ms';
		t.style.opacity='0';
		setTimeout(()=>t.style.display='none',300);
	}, 2200);
}

// Logout handler moved here so sidebar HTML stays pure
function logoutUser(e){
	if (e && e.preventDefault) e.preventDefault();
	fetch('/api/api/logout.php', { method: 'POST', credentials: 'include' })
		.then((res) => res.json())
		.then((data) => {
			// Update UI and reload
			if (window.setLoginVisibility) window.setLoginVisibility(false, null);
			window.location.reload();
		})
		.catch(() => {
			window.location.reload();
		});
}

// Toggle authentication state for sidebar
function toggleSidebarAuth() {
	fetch('/api/api/check_login.php', { credentials: 'include' })
		.then(res => res.json())
		.then(data => {
			if (data.logged_in) {
				// User is logged in, perform logout
				if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
					fetch('/api/api/logout.php', {
						method: 'POST',
						credentials: 'include'
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Update button to show login state
							updateSidebarAuthButton(false);
							// Redirect to login page
							setTimeout(() => {
								window.location.href = 'login.html';
							}, 500);
						}
					})
					.catch(error => {
						console.error('Logout error:', error);
						window.location.href = 'login.html';
					});
				}
			} else {
				// User is not logged in, redirect to login page
				window.location.href = 'login.html';
			}
		})
		.catch(error => {
			console.error('Auth check error:', error);
			window.location.href = 'login.html';
		});
}

// Update sidebar auth button based on login state
function updateSidebarAuthButton(isLoggedIn) {
	const authIcon = document.getElementById('sidebarAuthIcon');
	const authText = document.getElementById('sidebarAuthText');
	const authBtn = document.getElementById('sidebarAuthBtn');

	if (isLoggedIn) {
		if (authIcon) authIcon.className = 'fa fa-sign-out text-xl text-red-500 sidebar-icon';
		if (authText) authText.textContent = 'تسجيل الخروج';
	} else {
		if (authIcon) authIcon.className = 'fa fa-sign-in text-xl text-green-500 sidebar-icon';
		if (authText) authText.textContent = 'تسجيل دخول';
	}
}

// Check auth state on sidebar load
function checkSidebarAuthState() {
	fetch('/api/api/check_login.php', { credentials: 'include' })
		.then(res => res.json())
		.then(data => {
			updateSidebarAuthButton(data.logged_in);
		})
		.catch(error => {
			console.error('Auth check error:', error);
			updateSidebarAuthButton(false);
		});
}

// delegate logout clicks from any inserted sidebar markup
document.addEventListener('click', function(e){
	try {
		var a = e.target.closest && e.target.closest('[data-action="logout"]');
		if (a) {
			e.preventDefault();
			toggleSidebarAuth();
			return;
		}
	} catch(err){}
});

// Ensure sidebar visibility on desktop viewports
function ensureSidebarVisibleOnDesktop() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');
  if (window.innerWidth >= 768) {
    if (sidebar) {
      sidebar.classList.remove('translate-x-full');
    }
    if (overlay) {
      overlay.classList.add('hidden');
    }
  }
}

// Add event listeners for load and resize
window.addEventListener('resize', ensureSidebarVisibleOnDesktop);
window.addEventListener('load', ensureSidebarVisibleOnDesktop);

// تحميل وتشغيل نظام تحديد الصفحة النشطة
(function() {
	// إنشاء وتحميل ملف sidebar-active.js
	var script = document.createElement('script');
	script.src = 'js/sidebar-active.js';
	script.async = true;
	document.head.appendChild(script);
})();
