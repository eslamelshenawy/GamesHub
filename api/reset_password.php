<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// استقبال البيانات
$input = json_decode(file_get_contents('php://input'), true);
$newPassword = isset($input['password']) ? trim($input['password']) : '';
$confirmPassword = isset($input['confirm_password']) ? trim($input['confirm_password']) : '';
$token = isset($input['token']) ? trim($input['token']) : '';

// التحقق من وجود البيانات المطلوبة
if (empty($newPassword) || empty($confirmPassword) || empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'جميع الحقول مطلوبة']);
    exit;
}

// التحقق من تطابق كلمات المرور
if ($newPassword !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['error' => 'كلمات المرور غير متطابقة']);
    exit;
}

// التحقق من قوة كلمة المرور
if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل']);
    exit;
}

try {
    session_start();
    
    // التحقق من وجود بيانات الجلسة
    if (!isset($_SESSION['reset_code']) || 
        !isset($_SESSION['reset_email']) || 
        !isset($_SESSION['reset_token']) || 
        !isset($_SESSION['code_verified']) ||
        !isset($_SESSION['verification_time'])) {
        http_response_code(400);
        echo json_encode(['error' => 'جلسة غير صحيحة أو منتهية الصلاحية']);
        exit;
    }
    
    // التحقق من الرمز المميز
    if ($_SESSION['reset_token'] !== $token) {
        http_response_code(400);
        echo json_encode(['error' => 'رمز مميز غير صحيح']);
        exit;
    }
    
    // التحقق من أن الرمز تم التحقق منه مسبقاً
    if (!$_SESSION['code_verified']) {
        http_response_code(400);
        echo json_encode(['error' => 'يجب التحقق من رمز التحقق أولاً']);
        exit;
    }
    
    // التحقق من انتهاء صلاحية الجلسة (30 دقيقة من وقت التحقق)
    $currentTime = time();
    $verificationTime = $_SESSION['verification_time'];
    $timeLimit = 30 * 60; // 30 دقيقة بالثواني
    
    if (($currentTime - $verificationTime) > $timeLimit) {
        // حذف بيانات الجلسة المنتهية الصلاحية
        session_destroy();
        
        http_response_code(400);
        echo json_encode(['error' => 'انتهت صلاحية الجلسة. يرجى البدء من جديد']);
        exit;
    }
    
    $email = $_SESSION['reset_email'];
    
    // تشفير كلمة المرور الجديدة
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // تحديث كلمة المرور في قاعدة البيانات
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
    $result = $stmt->execute([$hashedPassword, $email]);
    
    if (!$result) {
        throw new Exception('فشل في تحديث كلمة المرور');
    }
    
    // التحقق من تأثير التحديث
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // حذف جميع بيانات الجلسة المتعلقة بإعادة تعيين كلمة المرور
    unset($_SESSION['reset_code']);
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_token']);
    unset($_SESSION['reset_time']);
    unset($_SESSION['code_verified']);
    unset($_SESSION['verification_time']);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تغيير كلمة المرور بنجاح'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?>