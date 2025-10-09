
        function sendCode(event) {
            event.preventDefault();
            const phoneNumber = document.getElementById('phoneNumber').value;
            // هنا يتم إرسال طلب إرسال كود التحقق للخادم
            alert(`تم إرسال كود التحقق إلى رقم ${phoneNumber}.`);
            // إظهار نافذة الكود
            document.getElementById('codeModal').style.display = 'flex';
        }

        function verifyCode(event) {
            event.preventDefault();
            const verificationCode = document.getElementById('verificationCode').value;
            // هنا يتم إرسال الكود للخادم للتحقق
            if (verificationCode === "123456") { // مثال على كود صحيح
                alert('تم تأكيد الكود بنجاح.');
                document.getElementById('codeModal').style.display = 'none';
                document.getElementById('resetModal').style.display = 'flex';
            } else {
                alert('كود التحقق غير صحيح. حاول مرة أخرى.');
            }
        }

        function resetPassword(event) {
            event.preventDefault();
            const newPassword = document.getElementById('newPassword').value;
            const confirmNewPassword = document.getElementById('confirmNewPassword').value;

            if (newPassword !== confirmNewPassword) {
                alert('كلمتا المرور غير متطابقتين.');
                return;
            }
            // هنا يتم إرسال كلمة السر الجديدة للخادم
            alert('تم إعادة تعيين كلمة السر بنجاح. سيتم نقلك لصفحة تسجيل الدخول.');
            // window.location.href = 'login_page.html';
        }
   