<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json');

// التحقق من تسجيل دخول المدير
ensure_session();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بالوصول']);
    exit;
}

try {
    // إحصائيات المستخدمين
    $userStats = [];
    
    // إجمالي المستخدمين
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'system'");
    $userStats['total_users'] = $stmt->fetch()['total_users'];
    
    // المستخدمين حسب النوع
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE role != 'system' GROUP BY role");
    $userStats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // المستخدمين المتصلين حالياً
    $stmt = $pdo->query("SELECT COUNT(*) as online_users FROM users WHERE is_online = 1 AND role != 'system'");
    $userStats['online_users'] = $stmt->fetch()['online_users'];
    
    // المستخدمين الجدد اليوم (بناءً على أول رسالة أو حساب)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as new_users_today FROM accounts WHERE DATE(created_at) = CURDATE()");
    $userStats['new_users_today'] = $stmt->fetch()['new_users_today'];
    
    // إحصائيات الحسابات
    $accountStats = [];
    
    // إجمالي الحسابات
    $stmt = $pdo->query("SELECT COUNT(*) as total_accounts FROM accounts");
    $accountStats['total_accounts'] = $stmt->fetch()['total_accounts'];
    
    // الحسابات حسب اللعبة
    $stmt = $pdo->query("SELECT game_name, COUNT(*) as count FROM accounts GROUP BY game_name ORDER BY count DESC LIMIT 10");
    $accountStats['accounts_by_game'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // متوسط سعر الحسابات
    $stmt = $pdo->query("SELECT AVG(price) as avg_price, MIN(price) as min_price, MAX(price) as max_price FROM accounts");
    $accountStats['price_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // الحسابات المضافة اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as accounts_today FROM accounts WHERE DATE(created_at) = CURDATE()");
    $accountStats['accounts_today'] = $stmt->fetch()['accounts_today'];
    
    // إحصائيات الصفقات
    $dealStats = [];
    
    // إجمالي الصفقات
    $stmt = $pdo->query("SELECT COUNT(*) as total_deals FROM deals");
    $dealStats['total_deals'] = $stmt->fetch()['total_deals'];
    
    // الصفقات حسب الحالة
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM deals GROUP BY status");
    $dealStats['deals_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إجمالي قيمة الصفقات
    $stmt = $pdo->query("SELECT SUM(amount) as total_amount, AVG(amount) as avg_amount FROM deals");
    $dealStats['deal_amounts'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // الصفقات المكتملة - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->query("SELECT COUNT(*) as completed_deals FROM deals WHERE status IN ('COMPLETED', 'RELEASED') AND amount > 0 AND status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')");
    $dealStats['completed_deals'] = $stmt->fetch()['completed_deals'];
    
    // الصفقات اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as deals_today FROM deals WHERE DATE(created_at) = CURDATE()");
    $dealStats['deals_today'] = $stmt->fetch()['deals_today'];
    
    // الصفقات المعلقة للمراجعة الإدارية
    $stmt = $pdo->query("SELECT COUNT(*) as pending_admin_review FROM deals WHERE status = 'ADMIN_REVIEW' OR admin_review_status = 'pending'");
    $dealStats['pending_admin_review'] = $stmt->fetch()['pending_admin_review'];
    
    // إحصائيات المحافظ والمدفوعات
    $walletStats = [];
    
    // إجمالي الأرصدة
    $stmt = $pdo->query("SELECT SUM(balance) as total_balance, SUM(pending_balance) as total_pending FROM wallets");
    $walletStats['balance_totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // إجمالي المدفوعات
    $stmt = $pdo->query("SELECT COUNT(*) as total_payments, SUM(amount) as total_payment_amount FROM payments");
    $walletStats['payment_totals'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // طلبات الشحن
    $stmt = $pdo->query("SELECT status, COUNT(*) as count, SUM(amount) as total_amount FROM wallet_topups GROUP BY status");
    $walletStats['topup_requests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الرسائل والمحادثات
    $messageStats = [];
    
    // إجمالي الرسائل
    $stmt = $pdo->query("SELECT COUNT(*) as total_messages FROM messages");
    $messageStats['total_messages'] = $stmt->fetch()['total_messages'];
    
    // إجمالي المحادثات
    $stmt = $pdo->query("SELECT COUNT(*) as total_conversations FROM conversations");
    $messageStats['total_conversations'] = $stmt->fetch()['total_conversations'];
    
    // الرسائل اليوم
    $stmt = $pdo->query("SELECT COUNT(*) as messages_today FROM messages WHERE DATE(created_at) = CURDATE()");
    $messageStats['messages_today'] = $stmt->fetch()['messages_today'];
    
    // إحصائيات المفضلة
    $favoriteStats = [];
    
    // إجمالي المفضلة
    $stmt = $pdo->query("SELECT COUNT(*) as total_favorites FROM favorites");
    $favoriteStats['total_favorites'] = $stmt->fetch()['total_favorites'];
    
    // أكثر الحسابات إضافة للمفضلة
    $stmt = $pdo->query("
        SELECT a.game_name, a.description, COUNT(f.id) as favorite_count 
        FROM favorites f 
        JOIN accounts a ON f.account_id = a.id 
        GROUP BY f.account_id 
        ORDER BY favorite_count DESC 
        LIMIT 5
    ");
    $favoriteStats['most_favorited'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات النزاعات
    $disputeStats = [];
    
    // إجمالي النزاعات
    $stmt = $pdo->query("SELECT COUNT(*) as total_disputes FROM disputes");
    $disputeStats['total_disputes'] = $stmt->fetch()['total_disputes'];
    
    // النزاعات حسب الحالة
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM disputes GROUP BY status");
    $disputeStats['disputes_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الاقتراحات
    $suggestionStats = [];
    
    // إجمالي الاقتراحات
    $stmt = $pdo->query("SELECT COUNT(*) as total_suggestions FROM suggestions");
    $suggestionStats['total_suggestions'] = $stmt->fetch()['total_suggestions'];
    
    // الاقتراحات حسب الحالة
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM suggestions GROUP BY status");
    $suggestionStats['suggestions_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات شهرية (آخر 6 أشهر)
    $monthlyStats = [];
    
    // الصفقات الشهرية
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as deal_count,
            SUM(amount) as total_amount
        FROM deals 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $monthlyStats['deals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // المستخدمين الجدد شهرياً (بناءً على أول نشاط)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(DISTINCT user_id) as new_users
        FROM accounts 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $monthlyStats['new_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات الأداء
    $performanceStats = [];
    
    // معدل إتمام الصفقات - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM deals WHERE status IN ('COMPLETED', 'RELEASED') AND amount > 0 AND status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')) as completed,
            (SELECT COUNT(*) FROM deals WHERE amount > 0 AND status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')) as total
    ");
    $result = $stmt->fetch();
    $performanceStats['completion_rate'] = $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100, 2) : 0;
    
    // متوسط وقت إتمام الصفقة (بالساعات) - استثناء الصفقات الفاشلة والسالبة
    $stmt = $pdo->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_completion_hours
        FROM deals 
        WHERE status IN ('COMPLETED', 'RELEASED')
        AND amount > 0
        AND status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')
    ");
    $performanceStats['avg_completion_hours'] = round($stmt->fetch()['avg_completion_hours'] ?? 0, 2);
    
    // أكثر البائعين نشاطاً - استثناء الصفقات الفاشلة والسالبة
    $topSellers = [];
    $stmt = $pdo->query("
        SELECT 
            u.name,
            u.phone,
            COUNT(d.id) as deal_count,
            SUM(d.amount) as total_sales
        FROM users u
        JOIN deals d ON u.id = d.seller_id
        WHERE d.status IN ('COMPLETED', 'RELEASED')
        AND d.amount > 0
        AND d.status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')
        GROUP BY u.id
        ORDER BY total_sales DESC
        LIMIT 10
    ");
    $topSellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // أكثر المشترين نشاطاً - استثناء الصفقات الفاشلة والسالبة
    $topBuyers = [];
    $stmt = $pdo->query("
        SELECT 
            u.name,
            u.phone,
            COUNT(d.id) as deal_count,
            SUM(d.amount) as total_purchases
        FROM users u
        JOIN deals d ON u.id = d.buyer_id
        WHERE d.status IN ('COMPLETED', 'RELEASED')
        AND d.amount > 0
        AND d.status NOT IN ('CANCELLED', 'FAILED', 'DISPUTED', 'TIMEOUT')
        GROUP BY u.id
        ORDER BY total_purchases DESC
        LIMIT 10
    ");
    $topBuyers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تجميع جميع الإحصائيات
    $stats = [
        'success' => true,
        'users' => $userStats,
        'accounts' => $accountStats,
        'deals' => $dealStats,
        'wallets' => $walletStats,
        'messages' => $messageStats,
        'favorites' => $favoriteStats,
        'disputes' => $disputeStats,
        'suggestions' => $suggestionStats,
        'monthly' => $monthlyStats,
        'performance' => $performanceStats,
        'top_sellers' => $topSellers,
        'top_buyers' => $topBuyers,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($stats, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'حدث خطأ في جلب الإحصائيات',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>