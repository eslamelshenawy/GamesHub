<?php
// ملف إنشاء صفقة جديدة
require_once 'db.php';

// استقبال البيانات من POST
$buyer_id = intval($_POST['buyer_id'] ?? 0);
$seller_id = intval($_POST['seller_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$details = trim($_POST['details'] ?? '');

// تحقق من صحة البيانات
if ($buyer_id <= 0 || $seller_id <= 0 || $amount <= 0 || empty($details)) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صحيحة']);
    exit;
}

// إدخال الصفقة
$stmt = $conn->prepare("INSERT INTO deals (buyer_id, seller_id, amount, details, status, created_at) VALUES (?, ?, ?, ?, 'CREATED', NOW())");
$stmt->bind_param('iids', $buyer_id, $seller_id, $amount, $details);
if ($stmt->execute()) {
    $deal_id = $stmt->insert_id;
    echo json_encode(['success' => true, 'deal_id' => $deal_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'فشل في إنشاء الصفقة']);
}
$stmt->close();
$conn->close();
?>
