<?php
// api/platform_fees_stats.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// التحقق من صلاحيات الإدارة
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !in_array($user['role'], ['admin', 'system'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'غير مسموح لك بالوصول لهذه البيانات']);
    exit;
}

try {
    // إحصائيات إجمالية لرسوم المنصة
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_deals,
            SUM(platform_fee) as total_fees,
            SUM(seller_amount) as total_seller_amount,
            AVG(platform_fee) as avg_fee,
            AVG(fee_percentage) as avg_fee_percentage
        FROM deals 
        WHERE status = "COMPLETED" 
        AND platform_fee > 0
    ');
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // إحصائيات شهرية
    $stmt = $pdo->prepare('
        SELECT 
            DATE_FORMAT(created_at, "%Y-%m") as month,
            COUNT(*) as deals_count,
            SUM(platform_fee) as monthly_fees,
            SUM(seller_amount) as monthly_seller_amount
        FROM deals 
        WHERE status = "COMPLETED" 
        AND platform_fee > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, "%Y-%m")
        ORDER BY month DESC
    ');
    $stmt->execute();
    $monthly_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // آخر 10 صفقات مع الرسوم
    $stmt = $pdo->prepare('
        SELECT 
            d.id,
            d.escrow_amount,
            d.platform_fee,
            d.seller_amount,
            d.fee_percentage,
            d.created_at,
            u1.name as buyer_name,
            u2.name as seller_name
        FROM deals d
        LEFT JOIN users u1 ON d.buyer_id = u1.id
        LEFT JOIN users u2 ON d.seller_id = u2.id
        WHERE d.status = "COMPLETED" 
        AND d.platform_fee > 0
        ORDER BY d.created_at DESC
        LIMIT 10
    ');
    $stmt->execute();
    $recent_deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_stats' => $stats,
            'monthly_stats' => $monthly_stats,
            'recent_deals' => $recent_deals
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('[platform_fees_stats] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
}
?>
