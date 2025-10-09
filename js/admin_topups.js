// جلب بيانات المستخدم الحالي لمعرفة إذا كان أدمن
let isAdmin = false;
fetch('api/get_user.php', { credentials: 'include' })
  .then(async res => {
    try {
      return await res.json();
    } catch (e) {
      throw new Error('فشل في قراءة بيانات المستخدم (الاستجابة ليست JSON)');
    }
  })
  .then(userData => {
    if (userData && userData.profile && userData.profile.role === 'admin') {
      isAdmin = true;

    }
  })
  .catch(err => {
    showNotification(err.message || 'حدث خطأ أثناء جلب بيانات المستخدم', false);
  })
  .finally(() => {

    // جلب الطلبات وعرضها في الجدول
    fetch('api/get_topup_requests.php', { credentials: 'include' })
      .then(async res => {
        try {
          return await res.json();
        } catch (e) {
          throw new Error('فشل في قراءة بيانات الطلبات (الاستجابة ليست JSON)');
        }
      })
      .then(data => {
        const tbody = document.getElementById('topups-table-body');
        tbody.innerHTML = '';
        if (!data.success || !Array.isArray(data.requests) || data.requests.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center py-6">لا توجد طلبات شحن حالياً</td></tr>';
          return;
        }
        data.requests.forEach((req, i) => {
          tbody.innerHTML += `
            <tr class="border-b">
              <td class="py-2 px-3">${i+1}</td>
              <td class="py-2 px-3">${req.username || req.user_id}</td>
              <td class="py-2 px-3">${req.phone || ''}</td>
              <td class="py-2 px-3">${req.amount}</td>
              <td class="py-2 px-3">${req.method}</td>
              <td class="py-2 px-3">${req.receipt ? `<a href="#" class="show-receipt" data-img="${req.receipt}">عرض</a>` : '-'} </td>
              <td class="py-2 px-3">${req.status}</td>
              <td class="py-2 px-3">
                ${(isAdmin && req.status === 'pending') ? `
                  <button onclick="approveTopup(${req.id})" class="bg-green-500 text-white px-2 py-1 rounded">موافقة</button>
                  <button onclick="rejectTopup(${req.id})" class="bg-red-500 text-white px-2 py-1 rounded ml-1">رفض</button>
                ` : '-'}
              </td>
            </tr>
          `;
        });

// تفعيل منطق المودال لعرض الإيصال
document.addEventListener('click', function(e) {
  if (e.target && e.target.classList.contains('show-receipt')) {
    e.preventDefault();
    var img = e.target.getAttribute('data-img');
    var modal = document.getElementById('receipt-modal');
    var modalImg = document.getElementById('receipt-modal-img');
    if (modal && modalImg) {
      modalImg.src = img;
      modal.style.display = 'flex';
    }
  }
});

document.getElementById('close-receipt-modal').onclick = function() {
  document.getElementById('receipt-modal').style.display = 'none';
  document.getElementById('receipt-modal-img').src = '';
};
      })
      .catch(err => {
        const tbody = document.getElementById('topups-table-body');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-6 text-red-600">' + (err.message || 'حدث خطأ أثناء جلب الطلبات') + '</td></tr>';
        showNotification(err.message || 'حدث خطأ أثناء جلب الطلبات', false);
      });
  });

function showNotification(msg, success = true) {
  let n = document.getElementById('admin-notify');
  if (!n) {
    n = document.createElement('div');
    n.id = 'admin-notify';
    n.style.position = 'fixed';
    n.style.top = '30px';
    n.style.left = '50%';
    n.style.transform = 'translateX(-50%)';
    n.style.zIndex = '9999';
    n.style.padding = '16px 32px';
    n.style.borderRadius = '8px';
    n.style.fontSize = '18px';
    n.style.fontWeight = 'bold';
    n.style.boxShadow = '0 2px 12px rgba(0,0,0,0.12)';
    document.body.appendChild(n);
  }
  n.textContent = msg;
  n.style.background = success ? '#38b000' : '#e63946';
  n.style.color = '#fff';
  n.style.display = 'block';
  setTimeout(() => { n.style.display = 'none'; }, 2500);
}

function approveTopup(id) {
  if (!confirm('تأكيد الموافقة على الشحن؟')) return;
  fetch('api/approve_topup.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ id })
  })
  .then(async res => {
    try {
      return await res.json();
    } catch (e) {
      throw new Error('فشل في قراءة استجابة الموافقة (الاستجابة ليست JSON)');
    }
  })
  .then(data => {
    if (data.success) {
      showNotification(data.message || 'تمت الموافقة', true);
      // تحديث صفحة wallet.html إذا كانت مفتوحة
      if (window.BroadcastChannel) {
        try {
          const bc = new BroadcastChannel('wallet_update');
          bc.postMessage({ action: 'refresh_balance' });
          bc.close();
        } catch(e) {}
      }
      setTimeout(() => location.reload(), 1800);
    } else {
      showNotification(data.error || data.message || 'فشل التنفيذ', false);
    }
  })
  .catch(err => {
    showNotification(err.message || 'حدث خطأ أثناء الموافقة على الشحن', false);
  });
}
function rejectTopup(id) {
  if (!confirm('تأكيد رفض الطلب؟')) return;
  fetch('api/reject_topup.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ id })
  })
  .then(async res => {
    try {
      return await res.json();
    } catch (e) {
      throw new Error('فشل في قراءة استجابة الرفض (الاستجابة ليست JSON)');
    }
  })
  .then(data => {
    if (data.success) {
      showNotification(data.message || 'تم الرفض', true);
      setTimeout(() => location.reload(), 1800);
    } else {
      showNotification(data.error || data.message || 'فشل التنفيذ', false);
    }
  })
  .catch(err => {
    showNotification(err.message || 'حدث خطأ أثناء رفض الشحن', false);
  });
}

// الموافقة ورفض طلبات السحب
function approveWithdraw(id) {
    if (!confirm('هل أنت متأكد من الموافقة على طلب السحب؟')) return;
    fetch('api/approve_withdraw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id),
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        showNotification(data.message || data.error, data.success);
        loadWithdrawals();
    });
}
function rejectWithdraw(id) {
    if (!confirm('هل أنت متأكد من رفض طلب السحب؟ سيتم إعادة الرصيد للمستخدم.')) return;
    fetch('api/reject_withdraw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id),
        credentials: 'include'
    })
    .then(res => res.json())
    .then(data => {
        showNotification(data.message || data.error, data.success);
        loadWithdrawals();
    });
}

// جلب طلبات السحب وعرضها في جدول السحب
function loadWithdrawals() {
    fetch('api/get_withdraw_requests.php', { credentials: 'include' })
        .then(async res => {
            try { return await res.json(); } catch { return {}; }
        })
        .then(data => {
            const tbody = document.getElementById('withdrawals-table-body');
            tbody.innerHTML = '';
            if (!data.success || !Array.isArray(data.requests) || data.requests.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center py-6">لا توجد طلبات سحب حالياً</td></tr>';
                return;
            }
            data.requests.forEach((req, i) => {
                tbody.innerHTML += `
                    <tr class="border-b">
                        <td class="py-2 px-3">${i+1}</td>
                        <td class="py-2 px-3">${req.name || req.user_id}</td>
                        <td class="py-2 px-3">${req.phone || ''}</td>
                        <td class="py-2 px-3">${req.amount}</td>
                        <td class="py-2 px-3">${req.method}</td>
                        <td class="py-2 px-3">${req.status}</td>
                        <td class="py-2 px-3">${req.created_at}</td>
                        <td class="py-2 px-3">
                            ${(isAdmin && req.status === 'pending') ? `
                                <button onclick="approveWithdraw(${req.id})" class="bg-green-500 text-white px-2 py-1 rounded">موافقة</button>
                                <button onclick="rejectWithdraw(${req.id})" class="bg-red-500 text-white px-2 py-1 rounded ml-1">رفض</button>
                            ` : '-'}
                        </td>
                    </tr>
                `;
            });
        });
}
// تحميل طلبات السحب عند إظهار القسم
const showWithdrawalsBtn = document.getElementById('show-withdrawals');
if (showWithdrawalsBtn) {
    showWithdrawalsBtn.addEventListener('click', loadWithdrawals);
}
// تحميل تلقائي إذا كان القسم ظاهر عند التحميل
if (document.getElementById('withdrawals-section').style.display !== 'none') {
    loadWithdrawals();
}
