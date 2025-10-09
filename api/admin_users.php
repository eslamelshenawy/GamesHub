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
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // جلب قائمة المستخدمين
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $status = isset($_GET['status']) ? $_GET['status'] : 'all';
        
        $offset = ($page - 1) * $limit;
        
        // بناء الاستعلام
        $where_conditions = [];
        $params = [];
        
        if (!empty($search)) {
            $where_conditions[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $search_param = '%' . $search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        if ($status !== 'all') {
            $where_conditions[] = 'status = ?';
            $params[] = $status;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // عدد المستخدمين الإجمالي
        $count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_users = $count_stmt->fetch()['total'];
        
        // جلب المستخدمين
        $sql = "
            SELECT 
                id, name, phone, email, role, status, wallet_balance, 
                created_at, last_login,
                (SELECT COUNT(*) FROM deals WHERE buyer_id = users.id OR seller_id = users.id) as total_deals,
                (SELECT COUNT(*) FROM game_accounts WHERE user_id = users.id) as total_accounts
            FROM users 
            $where_clause
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        // تنسيق البيانات
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = [
                'id' => intval($user['id']),
                'name' => $user['name'],
                'phone' => $user['phone'],
                'email' => $user['email'] ?? '',
                'role' => $user['role'],
                'status' => $user['status'],
                'wallet_balance' => floatval($user['wallet_balance'] ?? 0),
                'total_deals' => intval($user['total_deals']),
                'total_accounts' => intval($user['total_accounts']),
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login'],
                'member_since' => date('Y-m-d', strtotime($user['created_at']))
            ];
        }
        
        echo json_encode([
            'success' => true,
            'users' => $formatted_users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total_users / $limit),
                'total_users' => intval($total_users),
                'per_page' => $limit
            ]
        ]);
        
    } elseif ($method === 'PUT') {
        // تحديث بيانات المستخدم
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = intval($input['user_id'] ?? 0);
        $action = $input['action'] ?? '';
        
        if ($user_id <= 0) {
            throw new Exception('معرف المستخدم غير صحيح');
        }
        
        switch ($action) {
            case 'toggle_status':
                $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $current_status = $stmt->fetchColumn();
                
                if (!$current_status) {
                    throw new Exception('المستخدم غير موجود');
                }
                
                $new_status = ($current_status === 'active') ? 'suspended' : 'active';
                
                $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
                $stmt->execute([$new_status, $user_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث حالة المستخدم بنجاح',
                    'new_status' => $new_status
                ]);
                break;
                
            case 'update_role':
                $new_role = $input['role'] ?? '';
                if (!in_array($new_role, ['user', 'admin'])) {
                    throw new Exception('نوع المستخدم غير صحيح');
                }
                
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$new_role, $user_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث صلاحيات المستخدم بنجاح',
                    'new_role' => $new_role
                ]);
                break;
                
            case 'update_wallet':
                $amount = floatval($input['amount'] ?? 0);
                $operation = $input['operation'] ?? 'set'; // set, add, subtract
                
                if ($amount < 0) {
                    throw new Exception('المبلغ يجب أن يكون موجباً');
                }
                
                switch ($operation) {
                    case 'set':
                        $stmt = $pdo->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?');
                        $stmt->execute([$amount, $user_id]);
                        break;
                    case 'add':
                        $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
                        $stmt->execute([$amount, $user_id]);
                        break;
                    case 'subtract':
                        $stmt = $pdo->prepare('UPDATE users SET wallet_balance = GREATEST(0, wallet_balance - ?) WHERE id = ?');
                        $stmt->execute([$amount, $user_id]);
                        break;
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'تم تحديث رصيد المحفظة بنجاح'
                ]);
                break;
                
            default:
                throw new Exception('عملية غير مدعومة');
        }
        
    } elseif ($method === 'DELETE') {
        // حذف المستخدم
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = intval($input['user_id'] ?? 0);
        
        if ($user_id <= 0) {
            throw new Exception('معرف المستخدم غير صحيح');
        }
        
        // التحقق من وجود صفقات نشطة
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM deals WHERE (buyer_id = ? OR seller_id = ?) AND status IN ("active", "pending")');
        $stmt->execute([$user_id, $user_id]);
        $active_deals = $stmt->fetchColumn();
        
        if ($active_deals > 0) {
            throw new Exception('لا يمكن حذف المستخدم لوجود صفقات نشطة');
        }
        
        // حذف المستخدم
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح'
        ]);
        
    } else {
        throw new Exception('طريقة الطلب غير مدعومة');
    }
    
} catch (Exception $e) {
    error_log('Error in admin users API: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>