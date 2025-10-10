<?php
// نظام المهلات التلقائية للصفقات
require_once 'db.php';

// إعداد المهلات بالساعات
$delivery_timeout_hours = 5;
$confirm_timeout_hours = 7;

// إلغاء الصفقة إذا لم يسلم البائع خلال المهلة
$sql_cancel = "UPDATE deals SET status = 'CANCELLED', escrow_status = 'REFUNDED', refunded_at = NOW(), updated_at = NOW() 
WHERE status = 'ON_HOLD' AND TIMESTAMPDIFF(HOUR, updated_at, NOW()) >= ?";
$stmt = $conn->prepare($sql_cancel);
$stmt->bind_param('i', $delivery_timeout_hours);
$stmt->execute();
$stmt->close();

// إعادة المال للمشتري للصفقات الملغاة
$sql_refund = "UPDATE users u JOIN deals d ON u.id = d.buyer_id SET u.wallet_balance = u.wallet_balance + d.escrow_amount 
WHERE d.status = 'CANCELLED' AND d.escrow_status = 'REFUNDED' AND d.refunded_at = NOW()";
$conn->query($sql_refund);

// تسجيل الحركات المالية للإلغاء التلقائي
$sql_log_refund = "INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, created_at)
SELECT id, 'REFUND', escrow_amount, NULL, buyer_id, NOW() FROM deals WHERE status = 'CANCELLED' AND escrow_status = 'REFUNDED' AND refunded_at = NOW()";
$conn->query($sql_log_refund);

// إطلاق المال تلقائيًا للبائع إذا لم يرد المشتري خلال المهلة
$sql_release = "UPDATE deals SET status = 'RELEASED', escrow_status = 'RELEASED', released_at = NOW(), updated_at = NOW() 
WHERE status = 'DELIVERED' AND TIMESTAMPDIFF(HOUR, updated_at, NOW()) >= ? AND escrow_status = 'FUNDED'";
$stmt = $conn->prepare($sql_release);
$stmt->bind_param('i', $confirm_timeout_hours);
$stmt->execute();
$stmt->close();

// تحويل المال للبائع للصفقات التي تم إطلاقها تلقائيًا
$sql_pay = "UPDATE users u JOIN deals d ON u.id = d.seller_id SET u.wallet_balance = u.wallet_balance + d.escrow_amount 
WHERE d.status = 'RELEASED' AND d.escrow_status = 'RELEASED' AND d.released_at = NOW()";
$conn->query($sql_pay);

// تسجيل الحركات المالية للإطلاق التلقائي
$sql_log_release = "INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, created_at)
SELECT id, 'RELEASE', escrow_amount, NULL, seller_id, NOW() FROM deals WHERE status = 'RELEASED' AND escrow_status = 'RELEASED' AND released_at = NOW()";
$conn->query($sql_log_release);

$conn->close();
echo json_encode(['success' => true, 'message' => 'تم تطبيق المهلات التلقائية']);
?>
