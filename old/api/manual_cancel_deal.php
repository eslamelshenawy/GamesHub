<?php
// إلغاء الصفقة يدويًا من المشتري أو البائع
require_once 'db.php';

$deal_id = intval($_POST['deal_id'] ?? 0);
$user_id = intval($_POST['user_id'] ?? 0);

if ($deal_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صحيحة']);
    exit;
}

// جلب الصفقة
$stmt = $conn->prepare("SELECT status, buyer_id, seller_id, escrow_amount, escrow_status FROM deals WHERE id = ?");
$stmt->bind_param('i', $deal_id);
$stmt->execute();
$stmt->bind_result($status, $buyer_id, $seller_id, $escrow_amount, $escrow_status);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'الصفقة غير موجودة']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// تحقق من صلاحية الإلغاء
if ($status !== 'ON_HOLD' || $escrow_status !== 'FUNDED' || !in_array($user_id, [$buyer_id, $seller_id])) {
    http_response_code(403);
    echo json_encode(['error' => 'لا يمكن إلغاء هذه الصفقة في هذه الحالة']);
    $conn->close();
    exit;
}

// تنفيذ الإلغاء وإرجاع المال
$conn->begin_transaction();
try {
    // إعادة المال للمشتري
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->bind_param('di', $escrow_amount, $buyer_id);
    $stmt->execute();
    $stmt->close();

    // تحديث حالة الصفقة
    $stmt = $conn->prepare("UPDATE deals SET status = 'CANCELLED', escrow_status = 'REFUNDED', refunded_at = NOW(), updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $deal_id);
    $stmt->execute();
    $stmt->close();

    // تسجيل الحركة المالية
    $stmt = $conn->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, 'REFUND', ?, NULL, ?)");
    $stmt->bind_param('idi', $deal_id, $escrow_amount, $buyer_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    echo json_encode(['success' => true, 'deal_id' => $deal_id, 'refunded' => true]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'فشل في إلغاء الصفقة وإرجاع المال']);
}
$conn->close();
?>
