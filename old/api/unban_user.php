<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['user_id']) || empty($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
    exit;
}

$userId = intval($input['user_id']);

try {
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }
    
    if ($user['role'] !== 'banned') {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير محظور']);
        exit;
    }
    
    // Unban the user by changing role back to user
    $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    $result = $stmt->execute([$userId]);
    
    if ($result) {
        // Unban action completed successfully
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إلغاء حظر المستخدم بنجاح',
            'user_id' => $userId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء إلغاء حظر المستخدم']);
    }
    
} catch (PDOException $e) {
    error_log('Database error in unban_user.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات']);
} catch (Exception $e) {
    error_log('Error in unban_user.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ غير متوقع']);
}
?>