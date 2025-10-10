<?php
// إرجاع جميع طلبات الشحن للإدارة مع بيانات المستخدم
// Session is now managed in db.php
require_once 'security.php';
ensure_session();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'غير مصرح']);
    exit;
}
require_once 'db.php';
$sql = "SELECT t.*, u.name FROM wallet_topups t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC";
$stmt = $pdo->query($sql);
$requests = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['receipt']) {
        // إذا كان المسار لا يبدأ بـ 'uploads/' أضف uploads/ فقط
        if (strpos($row['receipt'], 'uploads/') !== 0) {
            $row['receipt'] = 'uploads/' . ltrim($row['receipt'], '/');
        }
    }
    // إعادة تسمية name إلى username ليتوافق مع الواجهة الأمامية
    if (isset($row['name'])) {
        $row['username'] = $row['name'];
        unset($row['name']);
    }
    $requests[] = $row;
}
echo json_encode(['success'=>true, 'requests'=>$requests]);
