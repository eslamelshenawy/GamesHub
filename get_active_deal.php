<?php
session_start();
require_once 'api/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسموح بالوصول']);
    exit;
}

// التحقق من وجود معرف المحادثة
if (!isset($_GET['conversation_id']) || empty($_GET['conversation_id'])) {
    echo json_encode(['success' => false, 'error' => 'معرف المحادثة مطلوب']);
    exit;
}

$conversation_id = intval($_GET['conversation_id']);
$user_id = $_SESSION['user_id'];

try {
    // التحقق من أن المستخدم جزء من المحادثة (اختياري - لجعل النظام أكثر مرونة)
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // إذا لم توجد المحادثة، نتحقق من وجود صفقات مرتبطة بالمستخدم
    if (!$conversation) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT conversation_id FROM deals 
            WHERE conversation_id = ? AND (buyer_id = ? OR seller_id = ?)
        ");
        
        $stmt->execute([$conversation_id, $user_id, $user_id]);
        $deal_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deal_conversation) {
            echo json_encode([
                'success' => true,
                'deal' => null,
                'message' => 'لا توجد صفقات في هذه المحادثة'
            ]);
            exit;
        }
    }
    

    
    // أولاً: البحث عن الصفقة النشطة (غير المكتملة) في المحادثة
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            buyer.name as buyer_name,
            seller.name as seller_name,
            a.game_name as account_title,
            a.price as account_price,
            a.user_id as account_owner_id,
            account_owner.name as account_owner_name
        FROM deals d
        LEFT JOIN accounts a ON d.account_id = a.id
        LEFT JOIN users buyer ON d.buyer_id = buyer.id
        LEFT JOIN users seller ON d.seller_id = seller.id
        LEFT JOIN users account_owner ON a.user_id = account_owner.id
        WHERE d.conversation_id = ?
        AND d.status NOT IN ('COMPLETED', 'CANCELLED', 'REFUNDED')
        AND (d.buyer_id = ? OR d.seller_id = ?)
        ORDER BY COALESCE(d.updated_at, d.created_at) DESC, d.created_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // إذا لم توجد صفقة نشطة، ابحث عن آخر صفقة في المحادثة (حتى لو كانت مكتملة)
    if (!$deal) {
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                buyer.name as buyer_name,
                seller.name as seller_name,
                a.game_name as account_title,
                a.price as account_price,
                a.user_id as account_owner_id,
                account_owner.name as account_owner_name
            FROM deals d
            LEFT JOIN accounts a ON d.account_id = a.id
            LEFT JOIN users buyer ON d.buyer_id = buyer.id
            LEFT JOIN users seller ON d.seller_id = seller.id
            LEFT JOIN users account_owner ON a.user_id = account_owner.id
            WHERE d.conversation_id = ?
            AND (d.buyer_id = ? OR d.seller_id = ?)
            ORDER BY COALESCE(d.updated_at, d.created_at) DESC, d.created_at DESC
             LIMIT 1
         ");
        
        $stmt->execute([$conversation_id, $user_id, $user_id]);
        $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($deal) {
        // تحديد ما إذا كانت الصفقة نشطة أم مكتملة
        $is_active = !in_array($deal['status'], ['COMPLETED', 'CANCELLED', 'REFUNDED']);
        
        echo json_encode([
            'success' => true,
            'deal' => [
                'id' => $deal['id'],
                'buyer_id' => $deal['buyer_id'],
                'seller_id' => $deal['seller_id'],
                'account_id' => $deal['account_id'],
                'amount' => $deal['amount'],
                'status' => $deal['status'],
                'is_active' => $is_active,
                'account_title' => $deal['account_title'],
                'account_price' => $deal['account_price'],
                'account_owner_id' => $deal['account_owner_id'],
                'buyer_name' => $deal['buyer_name'],
                'seller_name' => $deal['seller_name'],
                'account_owner_name' => $deal['account_owner_name'],
                'delivery_confirmed' => $deal['delivery_confirmed'] ?? false,
                'release_funds_processing' => $deal['release_funds_processing'] ?? false,
                'release_funds_requested_at' => $deal['release_funds_requested_at'] ?? null,
                'release_funds_requested_by' => $deal['release_funds_requested_by'] ?? null,
                'created_at' => $deal['created_at']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'deal' => null
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_active_deal.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>