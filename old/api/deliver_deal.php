<?php
// ملف تسليم الحساب من البائع
require_once 'db.php';

$deal_id = intval($_POST['deal_id'] ?? 0);
$seller_id = intval($_POST['seller_id'] ?? 0);

if ($deal_id <= 0 || $seller_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صحيحة']);
    exit;
}

// جلب الصفقة
$stmt = $conn->prepare("SELECT status, seller_id FROM deals WHERE id = ?");
$stmt->bind_param('i', $deal_id);
$stmt->execute();
$stmt->bind_result($status, $db_seller_id);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'الصفقة غير موجودة']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

if ($status !== 'ON_HOLD' || $seller_id !== $db_seller_id) {
    http_response_code(403);
    echo json_encode(['error' => 'لا يمكن تسليم هذه الصفقة']);
    $conn->close();
    exit;
}

// تحديث حالة الصفقة إلى DELIVERED
$stmt = $conn->prepare("UPDATE deals SET status = 'DELIVERED', updated_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $deal_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'deal_id' => $deal_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'فشل في تحديث حالة الصفقة']);
}
$stmt->close();
$conn->close();
?>
