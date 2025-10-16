
document.addEventListener('DOMContentLoaded', function() {
    const sendMoneyButton = document.getElementById('send-money-btn');
    const sendMoneyModal = document.getElementById('send-money-modal');
    const cancelSendMoney = document.getElementById('cancel-send-money');
    const sendMoneyForm = document.getElementById('send-money-form');

    // دالة تحويل المال من المستخدم الحالي إلى مستخدم آخر
    async function sendPayment(amount, receiverId) {
        if (!receiverId || isNaN(receiverId) || parseInt(receiverId) <= 0) {
            alert('معرف المستلم غير صالح أو غير محدد.');
            return;
        }
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            alert('يرجى إدخال مبلغ صالح.');
            return;
        }
        const confirm = window.confirm('هل تريد تحويل الأموال؟');
        if (!confirm) return;
        // سجل القيم المرسلة قبل الفتش
        console.log('سيتم إرسال البيانات:', {
            to_id: parseInt(receiverId),
            amount: parseFloat(amount)
        });
        try {
            const response = await fetch('/api/api/transfer_wallet.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    to_id: parseInt(receiverId),
                    amount: parseFloat(amount)
                })
            });
            const result = await response.json();
            if (result.success) {
                alert('تم تحويل الأموال بنجاح.');
                sendMoneyModal.classList.add('hidden');
                location.reload();
            } else {
                alert(result.error || 'حدث خطأ أثناء تحويل الأموال.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('تعذر الاتصال بالخادم. حاول مرة أخرى لاحقًا.');
        }
    }

    if (sendMoneyButton && sendMoneyModal && cancelSendMoney && sendMoneyForm) {
        // فتح النافذة عند الضغط على زر إرسال الأموال
        sendMoneyButton.addEventListener('click', function() {
            // تحقق من أن ACTIVE_CHAT_USER معرف رقمي صحيح
            console.log('فتح نافذة التحويل، ACTIVE_CHAT_USER =', window.ACTIVE_CHAT_USER);
            if (!window.ACTIVE_CHAT_USER || isNaN(window.ACTIVE_CHAT_USER) || parseInt(window.ACTIVE_CHAT_USER) <= 0) {
                alert('يرجى اختيار مستخدم صحيح من الدردشة قبل التحويل.');
                return;
            }
            sendMoneyModal.classList.remove('hidden');
            var sellerIdInput = document.getElementById('seller-id');
            if (sellerIdInput) {
                sellerIdInput.value = window.ACTIVE_CHAT_USER;
            }
        });

        // إغلاق النافذة عند الضغط على زر إلغاء
        cancelSendMoney.addEventListener('click', function() {
            sendMoneyModal.classList.add('hidden');
        });

        // معالجة إرسال النموذج
        sendMoneyForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const amount = document.getElementById('amount').value;
            const receiverId = window.ACTIVE_CHAT_USER;
            console.log('محاولة إرسال الأموال إلى:', receiverId);
            if (!receiverId || isNaN(receiverId) || parseInt(receiverId) <= 0) {
                alert('يرجى اختيار مستخدم صحيح من الدردشة قبل إرسال الأموال.');
                return;
            }
            sendPayment(amount, receiverId);
        });
    }
});
