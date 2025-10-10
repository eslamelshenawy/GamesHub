<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // إضافة عمود role إذا لم يكن موجوداً
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(32) DEFAULT 'buyer'");
    } catch (Exception $e) {
        // للإصدارات القديمة من MySQL
        $colCheck = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
        $colCheck->execute();
        if (!$colCheck->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) DEFAULT 'buyer'");
        }
    }

    // بيانات المدير
    $adminPhone = '1111111111';
    $adminName = 'Admin';
    $adminPassword = 'admin123';
    $adminRole = 'admin';

    // التحقق من وجود المدير
    $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
    $stmt->execute([$adminPhone]);
    $existingAdmin = $stmt->fetch();

    if (!$existingAdmin) {
        // إنشاء حساب المدير
        $passHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (phone, name, password, role, age, gender) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$adminPhone, $adminName, $passHash, $adminRole, 25, 'male']);
        
        echo "تم إنشاء حساب المدير بنجاح!\n";
        echo "رقم الهاتف: $adminPhone\n";
        echo "كلمة المرور: $adminPassword\n";
        echo "الدور: $adminRole\n";
    } else {
        // تحديث الدور إلى admin إذا كان المستخدم موجوداً
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$adminRole, $existingAdmin['id']]);
        
        echo "المستخدم موجود مسبقاً. تم تحديث دوره إلى مدير.\n";
        echo "رقم الهاتف: $adminPhone\n";
        echo "كلمة المرور: $adminPassword\n";
        echo "الدور: $adminRole\n";
    }

} catch (Exception $e) {
    echo 'خطأ: ' . $e->getMessage();
}
?>