<?php
require_once 'db.php';
require_once 'security.php';
header('Content-Type: application/json; charset=utf-8');
    ensure_session();

// Determine profile id: ?id= or current session user
$id = isset($_GET['id']) && intval($_GET['id']) > 0 ? intval($_GET['id']) : (isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المستخدم غير صالح']);
    exit;
}

try {
    // Discover which optional columns exist in the users table so SQL doesn't fail
    $colsStmt = $pdo->query("DESCRIBE users");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0); // list of field names

        $required = ['id','name'];
        $optional = ['bio','image','phone','age','gender','role'];
        // إضافة أعمدة الحالة إذا كانت موجودة
        if (in_array('is_online', $cols)) $optional[] = 'is_online';
        if (in_array('typing_to', $cols)) $optional[] = 'typing_to';
        $select = $required;
        foreach ($optional as $c) {
            if (in_array($c, $cols)) $select[] = $c;
        }

        $selectSql = implode(', ', array_map(function($c){ return "`$c`"; }, $select));
        $stmt = $pdo->prepare("SELECT $selectSql FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '\u0627\u0644\u0645\u0633\u062a\u062e\u062f\u0645 \u063a\u064a\u0631 \u0645\u0648\u062c\u0648\u062f']);
            exit;
        }

        $viewer_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $is_owner = $viewer_id > 0 && $viewer_id === intval($user['id']);

        // Return profile data (do not expose sensitive fields if needed)
        // Build profile output using only keys present in the fetched row
        $profile = ['id' => intval($user['id']), 'name' => $user['name']];
        foreach (['bio','image','phone','age','gender','role','is_online','typing_to'] as $k) {
            if (array_key_exists($k, $user)) {
                $profile[$k] = $user[$k];
            } else {
                $profile[$k] = null;
            }
        }

        echo json_encode([
            'profile' => $profile,
            'viewer_id' => $viewer_id,
            'is_owner' => $is_owner
        ]);
} catch (Exception $e) {
    // Log detailed error server-side and return a generic JSON error to client
    error_log('get_user.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم']);
}
