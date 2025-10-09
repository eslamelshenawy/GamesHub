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

try {
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Get search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Get status filter
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(u.name LIKE ? OR u.phone LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if (!empty($statusFilter) && in_array($statusFilter, ['user', 'admin'])) {
        $whereConditions[] = "u.role = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) FROM users u {$whereClause}";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalUsers = $stmt->fetchColumn();
    
    // Get users with additional statistics
    $query = "
        SELECT 
            u.id,
            u.name,
            u.phone,
            u.role,
            u.balance,
            u.wallet_balance,
            u.is_online,
            u.created_at,
            COUNT(DISTINCT d.id) as deals_count,
            COUNT(DISTINCT t.id) as completed_topups,
            COALESCE(SUM(CASE WHEN t.status = 'approved' THEN t.amount END), 0) as total_topups
        FROM users u
        LEFT JOIN deals d ON (u.id = d.buyer_id OR u.id = d.seller_id)
        LEFT JOIN wallet_topups t ON u.id = t.user_id
        {$whereClause}
        GROUP BY u.id, u.name, u.phone, u.role, u.balance, u.wallet_balance, u.is_online, u.created_at
        ORDER BY u.id DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the users data
    $formattedUsers = array_map(function($user) {
        return [
            'id' => intval($user['id']),
            'name' => $user['name'],
            'phone' => $user['phone'],
            'role' => $user['role'],
            'balance' => floatval($user['balance']),
            'wallet_balance' => floatval($user['wallet_balance']),
            'is_online' => (bool)$user['is_online'],
            'created_at' => $user['created_at'],
            'deals_count' => intval($user['deals_count']),
            'completed_topups' => intval($user['completed_topups']),
            'total_topups' => floatval($user['total_topups'])
        ];
    }, $users);
    
    // Calculate pagination info
    $totalPages = ceil($totalUsers / $limit);
    
    echo json_encode([
        'success' => true,
        'users' => $formattedUsers,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_users' => intval($totalUsers),
            'per_page' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get_users.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Error in get_users.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ غير متوقع']);
}
?>