<?php
session_start();
require_once 'api/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسموح بالوصول']);
    exit;
}

// التحقق من وجود معرف البائع
if (!isset($_GET['seller_id']) || empty($_GET['seller_id'])) {
    echo json_encode(['success' => false, 'error' => 'معرف البائع مطلوب']);
    exit;
}

$seller_id = intval($_GET['seller_id']);

try {
    // جلب حسابات البائع المتاحة للبيع
    $stmt = $pdo->prepare("
        SELECT a.id, a.game_name, a.description, a.price, a.created_at
        FROM accounts a
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    
    $stmt->execute([$seller_id]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'accounts' => $accounts,
        'count' => count($accounts)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_seller_accounts.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في الخادم'
    ]);
}
?>