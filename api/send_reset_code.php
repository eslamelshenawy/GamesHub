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
$email = isset($input['email']) ? trim($input['email']) : '';

// التحقق من صحة البريد الإلكتروني
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'بريد إلكتروني غير صحيح']);
    exit;
}

try {
    // التحقق من وجود البريد الإلكتروني في قاعدة البيانات
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'البريد الإلكتروني غير مسجل']);
        exit;
    }
    
    // توليد رمز التحقق (6 أرقام)
    $verificationCode = sprintf('%06d', mt_rand(100000, 999999));
    
    // توليد رمز مميز للجلسة
    $token = bin2hex(random_bytes(32));
    
    // حفظ رمز التحقق في الجلسة
    session_start();
    $_SESSION['reset_code'] = $verificationCode;
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_token'] = $token;
    $_SESSION['reset_time'] = time();
    
    // إعداد محتوى البريد الإلكتروني
    $subject = 'رمز استعادة كلمة المرور - GamesHub';
    $message = "مرحباً {$user['name']},\n\nرمز التحقق الخاص بك هو: {$verificationCode}\n\nهذا الرمز صالح لمدة 30 دقيقة فقط.\n\nإذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذه الرسالة.\n\nفريق GamesHub";
    
    $emailHtml = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px;'>
        <div style='background: white; padding: 30px; border-radius: 10px; text-align: center;'>
            <h1 style='color: #333; margin-bottom: 20px;'>GamesHub</h1>
            <h2 style='color: #555; margin-bottom: 30px;'>استعادة كلمة المرور</h2>
            <p style='color: #666; font-size: 16px; margin-bottom: 20px;'>مرحباً {$user['name']},</p>
            <p style='color: #666; font-size: 16px; margin-bottom: 30px;'>رمز التحقق الخاص بك هو:</p>
            <div style='background: #f8f9fa; border: 2px dashed #007bff; padding: 20px; margin: 20px 0; border-radius: 10px;'>
                <h1 style='color: #007bff; font-size: 36px; margin: 0; letter-spacing: 5px;'>{$verificationCode}</h1>
            </div>
            <p style='color: #666; font-size: 14px; margin-bottom: 20px;'>هذا الرمز صالح لمدة 30 دقيقة فقط</p>
            <p style='color: #999; font-size: 12px;'>إذا لم تطلب استعادة كلمة المرور، يرجى تجاهل هذه الرسالة</p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='color: #999; font-size: 12px;'>فريق GamesHub</p>
        </div>
    </div>
    ";
    
    // إرسال البريد الإلكتروني عبر Google Apps Script
    $EMAIL_API_URL = 'https://script.google.com/macros/s/AKfycbye7T3cF5Bu4qSbFDCuUNhkMvCcIcIcP9BkK4zlibepI1DkDt9StYIA3rPpU_ii4RoNdQ/exec';
    
    $emailData = [
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
        'message_html' => $emailHtml,
        'api_key' => 'asd774hiudf98'
    ];
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($emailData)
        ]
    ]);
    
    // إرسال البريد الإلكتروني
    $result = file_get_contents($EMAIL_API_URL, false, $context);
    
    if ($result === false) {
        throw new Exception('فشل في إرسال البريد الإلكتروني');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال رمز التحقق بنجاح',
        'token' => $token
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'حدث خطأ في الخادم: ' . $e->getMessage()]);
}
?>