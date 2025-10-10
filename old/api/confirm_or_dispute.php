<?php
// ملف تأكيد أو فتح نزاع من المشتري
require_once 'db.php';

$deal_id = intval($_POST['deal_id'] ?? 0);
$buyer_id = intval($_POST['buyer_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($deal_id <= 0 || $buyer_id <= 0 || !in_array($action, ['confirm', 'dispute'])) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صحيحة']);
    exit;
}

// جلب الصفقة
$stmt = $conn->prepare("SELECT status, buyer_id, seller_id, escrow_amount, escrow_status FROM deals WHERE id = ?");
$stmt->bind_param('i', $deal_id);
$stmt->execute();
$stmt->bind_result($status, $db_buyer_id, $seller_id, $escrow_amount, $escrow_status);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'الصفقة غير موجودة']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

if ($buyer_id !== $db_buyer_id || $status !== 'DELIVERED' || $escrow_status !== 'FUNDED') {
    http_response_code(403);
    echo json_encode(['error' => 'لا يمكن تنفيذ هذا الإجراء على الصفقة']);
    $conn->close();
    exit;
}

if ($action === 'confirm') {
    // تأكيد الاستلام: تحديث الصفقة وتحويل المال للبائع
    $conn->begin_transaction();
    try {
        // تحويل المال للبائع
        $stmt = $conn->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->bind_param('di', $escrow_amount, $seller_id);
        $stmt->execute();
        $stmt->close();

        // تسجيل الحركة المالية (RELEASE من الضمان للبائع)
        $stmt = $conn->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, 'RELEASE', ?, NULL, ?)");
        $stmt->bind_param('idi', $deal_id, $escrow_amount, $seller_id);
        $stmt->execute();
        $stmt->close();

        // تحديث حالة الصفقة
        $stmt = $conn->prepare("UPDATE deals SET status = 'RELEASED', escrow_status = 'RELEASED', released_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $deal_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'deal_id' => $deal_id, 'released' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'فشل في تحويل المال للبائع']);
    }
} elseif ($action === 'dispute') {
    // فتح نزاع: تحديث حالة الصفقة فقط
    $stmt = $conn->prepare("UPDATE deals SET status = 'DISPUTED', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $deal_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'deal_id' => $deal_id, 'disputed' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'فشل في فتح النزاع']);
    }
    $stmt->close();
}
$conn->close();
?>
