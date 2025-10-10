<?php
// api/profits_stats.php
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
    // إحصائيات الأرباح الإجمالية - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_deals,
            SUM(amount) as total_amount,
            SUM(amount * 0.10) as total_profits,
            (CASE WHEN COUNT(*) > 0 
                THEN SUM(amount * 0.10) / COUNT(*) 
                ELSE 0 END) as avg_profit_per_deal
        FROM deals 
        WHERE status = "COMPLETED" 
        AND amount > 0
        AND status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
    ');
    $stmt->execute();
    $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    // تصحيح القيم لتكون أرقام عشرية
    $totalStats['total_deals'] = (int)($totalStats['total_deals'] ?? 0);
    $totalStats['total_amount'] = (float)($totalStats['total_amount'] ?? 0);
    $totalStats['total_profits'] = (float)($totalStats['total_profits'] ?? 0);
    $totalStats['avg_profit_per_deal'] = (float)($totalStats['avg_profit_per_deal'] ?? 0);
    
    // الأرباح اليوم - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as deals_today,
            SUM(amount * 0.10) as profits_today
        FROM deals 
        WHERE status = "COMPLETED" 
        AND amount > 0
        AND status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        AND DATE(created_at) = CURDATE()
    ');
    $stmt->execute();
    $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // الأرباح الشهرية - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            DATE_FORMAT(created_at, "%Y-%m") as month,
            COUNT(*) as deals_count,
            SUM(amount) as total_amount,
            SUM(amount * 0.10) as monthly_profits
        FROM deals 
        WHERE status = "COMPLETED"
        AND amount > 0
        AND status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, "%Y-%m")
        ORDER BY month DESC
    ');
    $stmt->execute();
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // هذا الشهر - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as deals_this_month,
            SUM(amount * 0.10) as profits_this_month
        FROM deals 
        WHERE status = "COMPLETED" 
        AND amount > 0
        AND status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        AND YEAR(created_at) = YEAR(NOW())
        AND MONTH(created_at) = MONTH(NOW())
    ');
    $stmt->execute();
    $thisMonthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // الشهر الماضي - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as deals_last_month,
            SUM(amount * 0.10) as profits_last_month
        FROM deals 
        WHERE status = "COMPLETED" 
        AND amount > 0
        AND status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))
        AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
    ');
    $stmt->execute();
    $lastMonthStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // حساب نمو الأرباح
    $profitGrowth = 0;
    if ($lastMonthStats['profits_last_month'] > 0) {
        $profitGrowth = (($thisMonthStats['profits_this_month'] - $lastMonthStats['profits_last_month']) / $lastMonthStats['profits_last_month']) * 100;
    }
    
    // أفضل البائعين (حسب عدد الصفقات) - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            u.name as seller_name,
            COUNT(d.id) as deals_count,
            SUM(d.amount) as total_amount,
            SUM(d.amount * 0.10) as total_profits
        FROM deals d
        JOIN users u ON d.seller_id = u.id
        WHERE d.status = "COMPLETED"
        AND d.amount > 0
        AND d.status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        GROUP BY d.seller_id, u.name
        ORDER BY deals_count DESC
        LIMIT 5
    ');
    $stmt->execute();
    $topSellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // توزيع الأرباح حسب الألعاب - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->prepare('
        SELECT 
            a.game_name,
            COUNT(d.id) as deals_count,
            SUM(d.amount) as total_amount,
            SUM(d.amount * 0.10) as total_profits
        FROM deals d
        LEFT JOIN accounts a ON d.account_id = a.id
        WHERE d.status = "COMPLETED"
        AND d.amount > 0
        AND d.status NOT IN ("CANCELLED", "FAILED", "DISPUTED", "TIMEOUT")
        AND a.game_name IS NOT NULL
        GROUP BY a.game_name
        ORDER BY total_profits DESC
        LIMIT 10
    ');
    $stmt->execute();
    $gameProfits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_stats' => $totalStats,
            'today_stats' => $todayStats,
            'this_month_stats' => $thisMonthStats,
            'last_month_stats' => $lastMonthStats,
            'monthly_stats' => $monthlyStats,
            'profit_growth' => round($profitGrowth, 2),
            'top_sellers' => $topSellers,
            'game_profits' => $gameProfits
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('[profits_stats] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
}
?>
