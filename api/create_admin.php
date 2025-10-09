<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

// بيانات الأدمن الجديد
$adminData = [
    'name' => 'Admin',
    'email' => 'admin@admin.com',
    'password' => 'admin123',  // كلمة المرور
    'phone' => '01000000000',
    'role' => 'admin'  // استخدام role بدل is_admin
];

try {
    // التحقق من أن قاعدة البيانات متصلة
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // التحقق إذا كان الأدمن موجود بالفعل - وتحديثه لو موجود
    $checkStmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ? OR phone = ?");
    $checkStmt->execute([$adminData['email'], $adminData['phone']]);
    $existingUser = $checkStmt->fetch();

    if ($existingUser) {
        // تحديث المستخدم الموجود ليكون أدمن
        $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);

        $updateStmt = $pdo->prepare("
            UPDATE users
            SET role = 'admin', password = ?, name = ?, balance = 10000
            WHERE id = ?
        ");

        $updateStmt->execute([
            $hashedPassword,
            $adminData['name'],
            $existingUser['id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث المستخدم ليصبح أدمن',
            'admin_id' => $existingUser['id'],
            'login_info' => [
                'email' => $adminData['email'],
                'phone' => $adminData['phone'],
                'password' => $adminData['password']
            ],
            'login_url' => 'http://localhost/api/login.html'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // تشفير كلمة المرور
    $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);

    // إدراج الأدمن في قاعدة البيانات
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, created_at, balance)
        VALUES (?, ?, ?, ?, ?, NOW(), 10000)
    ");

    $stmt->execute([
        $adminData['name'],
        $adminData['email'],
        $adminData['phone'],
        $hashedPassword,
        $adminData['role']
    ]);

    $adminId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء حساب الأدمن بنجاح',
        'admin_id' => $adminId,
        'login_info' => [
            'email' => $adminData['email'],
            'phone' => $adminData['phone'],
            'password' => $adminData['password']
        ],
        'login_url' => 'http://localhost/api/login.html'
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
