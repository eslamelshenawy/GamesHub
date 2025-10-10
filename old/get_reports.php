<?php
// API لجلب البلاغات للإدارة
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
session_start();

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

// التحقق من صلاحيات الإدارة
$user_id = $_SESSION['user_id'];
$checkAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$checkAdmin->execute([$user_id]);
$user = $checkAdmin->fetch(PDO::FETCH_ASSOC);

// إذا لم يوجد عمود role، نتحقق من المعرف 38 كإداري افتراضي
if (!$user || ($user['role'] !== 'admin' && $user_id != 38)) {
    if ($user_id != 38) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة'
        ]);
        exit;
    }
}

try {
    // الحصول على معاملات الاستعلام
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(10, (int)$_GET['limit'])) : 20;
    $offset = ($page - 1) * $limit;
    
    // بناء شرط الحالة
    $statusCondition = '';
    $params = [];
    
    if ($status !== 'all') {
        $statusCondition = 'WHERE r.status = ?';
        $params[] = $status;
    }
    
    // استعلام جلب البلاغات مع معلومات المستخدمين
    $query = "
        SELECT 
            r.id,
            r.reporter_id,
            r.reported_user_id,
            r.conversation_id,
            r.reason,
            r.status,
            r.admin_conversation_id,
            r.admin_notes,
            r.reviewed_by,
            r.created_at,
            r.reviewed_at,
            reporter.name as reporter_name,
            reporter.image as reporter_image,
            reported.name as reported_user_name,
            reported.image as reported_user_image,
            reviewer.name as reviewer_name
        FROM reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_user_id = reported.id
        LEFT JOIN users reviewer ON r.reviewed_by = reviewer.id
        {$statusCondition}
        ORDER BY 
            CASE r.status 
                WHEN 'pending' THEN 1
                WHEN 'under_review' THEN 2
                WHEN 'resolved' THEN 3
                WHEN 'dismissed' THEN 4
                ELSE 5
            END,
            r.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // حساب العدد الإجمالي للبلاغات
    $countQuery = "SELECT COUNT(*) as total FROM reports r {$statusCondition}";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalReports = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // حساب إحصائيات البلاغات
    $statsQuery = "
        SELECT 
            status,
            COUNT(*) as count
        FROM reports 
        GROUP BY status
    ";
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = [];
    while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = (int)$row['count'];
    }
    
    // تنسيق البيانات
    $formattedReports = [];
    foreach ($reports as $report) {
        $formattedReports[] = [
            'id' => (int)$report['id'],
            'reporter' => [
                'id' => (int)$report['reporter_id'],
                'name' => $report['reporter_name'],
                'image' => $report['reporter_image']
            ],
            'reported_user' => [
                'id' => (int)$report['reported_user_id'],
                'name' => $report['reported_user_name'],
                'image' => $report['reported_user_image']
            ],
            'conversation_id' => (int)$report['conversation_id'],
            'admin_conversation_id' => (int)$report['admin_conversation_id'],
            'reason' => $report['reason'],
            'status' => $report['status'],
            'admin_notes' => $report['admin_notes'],
            'reviewer' => $report['reviewer_name'],
            'created_at' => $report['created_at'],
            'reviewed_at' => $report['reviewed_at']
        ];
    }
    
    // إرسال الاستجابة
    echo json_encode([
        'success' => true,
        'data' => [
            'reports' => $formattedReports,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalReports / $limit),
                'total_reports' => (int)$totalReports,
                'per_page' => $limit
            ],
            'statistics' => [
                'pending' => $stats['pending'] ?? 0,
                'under_review' => $stats['under_review'] ?? 0,
                'resolved' => $stats['resolved'] ?? 0,
                'dismissed' => $stats['dismissed'] ?? 0,
                'total' => (int)$totalReports
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ في جلب البلاغات: ' . $e->getMessage()
    ]);
}
?>