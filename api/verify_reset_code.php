<?php
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
$code = isset($input['code']) ? trim($input['code']) : '';
$token = isset($input['token']) ? trim($input['token']) : '';

// التحقق من وجود البيانات المطلوبة
if (empty($code) || empty($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'رمز التحقق والرمز المميز مطلوبان']);
    exit;
}

// التحقق من صحة رمز التحقق (6 أرقام)
if (!preg_match('/^\d{6}$/', $code)) {
    http_response_code(400);
    echo json_encode(['error' => 'رمز التحقق يجب أن يكون 6 أرقام']);
    exit;
}

try {
    session_start();
    
    // التحقق من وجود بيانات الجلسة
    if (!isset($_SESSION['reset_code']) || 
        !isset($_SESSION['reset_email']) || 
        !isset($_SESSION['reset_token']) || 
        !isset($_SESSION['reset_time'])) {
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
    
    // التحقق من انتهاء صلاحية الرمز (30 دقيقة)
    $currentTime = time();
    $resetTime = $_SESSION['reset_time'];
    $timeLimit = 30 * 60; // 30 دقيقة بالثواني
    
    if (($currentTime - $resetTime) > $timeLimit) {
        // حذف بيانات الجلسة المنتهية الصلاحية
        unset($_SESSION['reset_code']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_token']);
        unset($_SESSION['reset_time']);
        
        http_response_code(400);
        echo json_encode(['error' => 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد']);
        exit;
    }
    
    // التحقق من صحة رمز التحقق
    if ($_SESSION['reset_code'] !== $code) {
        http_response_code(400);
        echo json_encode(['error' => 'رمز التحقق غير صحيح']);
        exit;
    }
    
    // تحديث حالة التحقق في الجلسة
    $_SESSION['code_verified'] = true;
    $_SESSION['verification_time'] = $currentTime;
    
    echo json_encode([
        'success' => true,
        'message' => 'تم التحقق من الرمز بنجاح',
        'email' => $_SESSION['reset_email']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?>