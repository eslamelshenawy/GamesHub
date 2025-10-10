<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
    exit;
}

$userId = intval($_GET['user_id']);

try {
    
    // Get user details with additional information
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.phone,
            u.role,
            u.balance,
            u.wallet_balance,
            u.is_online,
            u.age,
            u.gender,
            COUNT(DISTINCT d.id) as deals_count,
            COUNT(DISTINCT t.id) as topups_count,
            COALESCE(SUM(CASE WHEN t.status = 'approved' THEN t.amount END), 0) as total_topups
        FROM users u
        LEFT JOIN deals d ON (u.id = d.buyer_id OR u.id = d.seller_id)
        LEFT JOIN wallet_topups t ON u.id = t.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }
    
    // Get recent deals
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.amount,
            d.status,
            d.created_at
        FROM deals d
        WHERE d.buyer_id = ? OR d.seller_id = ?
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $recentDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent topups
    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.amount,
            t.status,
            t.created_at
        FROM wallet_topups t
        WHERE t.user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentTopups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $userDetails = [
        'id' => $user['id'],
        'name' => $user['name'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'balance' => floatval($user['balance']),
        'wallet_balance' => floatval($user['wallet_balance']),
        'is_online' => (bool)$user['is_online'],
        'age' => $user['age'],
        'gender' => $user['gender'],
        'deals_count' => intval($user['deals_count']),
        'topups_count' => intval($user['topups_count']),
        'total_topups' => floatval($user['total_topups'] ?? 0),
        'recent_deals' => $recentDeals,
        'recent_topups' => $recentTopups
    ];
    
    echo json_encode([
        'success' => true,
        'user' => $userDetails
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get_user_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ في قاعدة البيانات']);
} catch (Exception $e) {
    error_log('Error in get_user_details.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ غير متوقع']);
}
?>