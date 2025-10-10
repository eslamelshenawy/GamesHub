<?php
require_once 'db.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

header('Content-Type: application/json; charset=utf-8');

// التحقق من صلاحيات الأدمن
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية للوصول']);
    exit;
}

try {
    $activities = [];
    
    // المستخدمين الجدد (آخر 10)
    $stmt = $pdo->query('
        SELECT "user_register" as type, name as user_name, created_at, id
        FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ');
    
    while ($row = $stmt->fetch()) {
        $time_diff = time() - strtotime($row['created_at']);
        $time_text = formatTimeDiff($time_diff);
        
        $activities[] = [
            'type' => 'user_register',
            'message' => 'مستخدم جديد انضم للمنصة',
            'user' => $row['user_name'],
            'time' => $time_text,
            'timestamp' => strtotime($row['created_at']),
            'icon' => 'fa-user-plus',
            'color' => 'neon-green'
        ];
    }
    
    // الصفقات المكتملة (آخر 5)
    $stmt = $pdo->query('
        SELECT d.*, u.name as buyer_name, s.name as seller_name
        FROM deals d
        LEFT JOIN users u ON d.buyer_id = u.id
        LEFT JOIN users s ON d.seller_id = s.id
        WHERE d.status = "completed"
        ORDER BY d.updated_at DESC 
        LIMIT 5
    ');
    
    while ($row = $stmt->fetch()) {
        $time_diff = time() - strtotime($row['updated_at']);
        $time_text = formatTimeDiff($time_diff);
        
        $activities[] = [
            'type' => 'deal_completed',
            'message' => 'تم إكمال صفقة بنجاح',
            'user' => $row['buyer_name'] ?? 'غير محدد',
            'time' => $time_text,
            'timestamp' => strtotime($row['updated_at']),
            'icon' => 'fa-handshake',
            'color' => 'neon-blue',
            'details' => 'قيمة الصفقة: $' . number_format($row['amount'] ?? 0, 2)
        ];
    }
    
    // طلبات الشحن الجديدة (آخر 5)
    $stmt = $pdo->query('
        SELECT wt.*, u.name as user_name
        FROM wallet_topups wt
        LEFT JOIN users u ON wt.user_id = u.id
        WHERE wt.status = "pending"
        ORDER BY wt.created_at DESC 
        LIMIT 5
    ');
    
    while ($row = $stmt->fetch()) {
        $time_diff = time() - strtotime($row['created_at']);
        $time_text = formatTimeDiff($time_diff);
        
        $activities[] = [
            'type' => 'topup_request',
            'message' => 'طلب شحن جديد',
            'user' => $row['user_name'] ?? 'غير محدد',
            'time' => $time_text,
            'timestamp' => strtotime($row['created_at']),
            'icon' => 'fa-wallet',
            'color' => 'neon-orange',
            'details' => 'المبلغ: $' . number_format($row['amount'] ?? 0, 2)
        ];
    }
    
    // الحسابات المباعة (آخر 5)
    $stmt = $pdo->query('
        SELECT ga.*, u.name as seller_name, g.name as game_name
        FROM game_accounts ga
        LEFT JOIN users u ON ga.user_id = u.id
        LEFT JOIN games g ON ga.game_id = g.id
        WHERE ga.status = "sold"
        ORDER BY ga.updated_at DESC 
        LIMIT 5
    ');
    
    while ($row = $stmt->fetch()) {
        $time_diff = time() - strtotime($row['updated_at']);
        $time_text = formatTimeDiff($time_diff);
        
        $activities[] = [
            'type' => 'account_sold',
            'message' => 'تم بيع حساب ' . ($row['game_name'] ?? 'لعبة'),
            'user' => $row['seller_name'] ?? 'غير محدد',
            'time' => $time_text,
            'timestamp' => strtotime($row['updated_at']),
            'icon' => 'fa-gamepad',
            'color' => 'neon-purple',
            'details' => 'السعر: $' . number_format($row['price'] ?? 0, 2)
        ];
    }
    
    // ترتيب الأنشطة حسب الوقت
    usort($activities, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // أخذ أحدث 15 نشاط
    $activities = array_slice($activities, 0, 15);
    
    echo json_encode($activities);
    
} catch (Exception $e) {
    error_log('Error fetching recent activities: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'خطأ في جلب الأنشطة الأخيرة',
        'details' => $e->getMessage()
    ]);
}

// دالة لتنسيق الوقت
function formatTimeDiff($seconds) {
    if ($seconds < 60) {
        return 'الآن';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        return $minutes . ' دقيقة';
    } elseif ($seconds < 86400) {
        $hours = floor($seconds / 3600);
        return $hours . ' ساعة';
    } elseif ($seconds < 2592000) {
        $days = floor($seconds / 86400);
        return $days . ' يوم';
    } else {
        $months = floor($seconds / 2592000);
        return $months . ' شهر';
    }
}
?>