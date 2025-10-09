<?php
// api/get_withdraw_requests.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

// فقط الأدمن يمكنه الوصول
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'غير مصرح']);
    exit;
}

$sql = "SELECT w.*, u.name FROM withdraw_requests w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.created_at DESC";
$stmt = $pdo->query($sql);
$requests = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $requests[] = $row;
}
echo json_encode(['success'=>true, 'requests'=>$requests]);
