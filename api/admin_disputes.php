<?php
// صفحة إدارة النزاعات للصفقات
require_once 'db.php';

// جلب جميع الصفقات المتنازع عليها
$sql = "SELECT d.id, d.buyer_id, d.seller_id, d.amount, d.details, d.created_at, d.updated_at, d.status, d.escrow_amount, d.escrow_status, u1.name AS buyer_name, u2.name AS seller_name
        FROM deals d
        JOIN users u1 ON d.buyer_id = u1.id
        JOIN users u2 ON d.seller_id = u2.id
        WHERE d.status = 'DISPUTED'";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة النزاعات</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #eee; }
        .actions button { margin: 0 5px; }
    </style>
</head>
<body>
    <h2>الصفقات المتنازع عليها</h2>
    <table>
        <tr>
            <th>رقم الصفقة</th>
            <th>المشتري</th>
            <th>البائع</th>
            <th>المبلغ</th>
            <th>تفاصيل الصفقة</th>
            <th>تاريخ النزاع</th>
            <th>إجراءات</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['buyer_name']) ?></td>
            <td><?= htmlspecialchars($row['seller_name']) ?></td>
            <td><?= $row['escrow_amount'] ?></td>
            <td><?= htmlspecialchars($row['details']) ?></td>
            <td><?= $row['updated_at'] ?></td>
            <td class="actions">
                <form method="post" style="display:inline">
                    <input type="hidden" name="deal_id" value="<?= $row['id'] ?>">
                    <button name="action" value="release" onclick="return confirm('تأكيد إطلاق المال للبائع؟')">إطلاق للبائع</button>
                    <button name="action" value="refund" onclick="return confirm('تأكيد إرجاع المال للمشتري؟')">إرجاع للمشتري</button>
                    <button name="action" value="cancel_dispute" onclick="return confirm('تأكيد إلغاء النزاع؟')">إلغاء النزاع</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php
// معالجة قرار الإدارة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deal_id'], $_POST['action'])) {
    $deal_id = intval($_POST['deal_id']);
    $action = $_POST['action'];
    // جلب الصفقة
    $stmt = $conn->prepare("SELECT buyer_id, seller_id, escrow_amount FROM deals WHERE id = ? AND status = 'DISPUTED'");
    $stmt->bind_param('i', $deal_id);
    $stmt->execute();
    $stmt->bind_result($buyer_id, $seller_id, $escrow_amount);
    if ($stmt->fetch()) {
        $stmt->close();
        if ($action === 'release') {
            // إطلاق المال للبائع
            $conn->begin_transaction();
            try {
                // إضافة المبلغ لرصيد البائع وخصمه من الرصيد المعلق للمشتري
                $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
                $stmt->bind_param('di', $escrow_amount, $seller_id);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $conn->prepare("UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?");
                $stmt->bind_param('di', $escrow_amount, $buyer_id);
                $stmt->execute();
                $stmt->close();
                    // تسجيل الحركة المالية (RELEASE من الضمان للبائع)
                    $stmt = $conn->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, 'RELEASE', ?, NULL, ?)");
                    $stmt->bind_param('idi', $deal_id, $escrow_amount, $seller_id);
                    $stmt->execute();
                    $stmt->close();
                $stmt = $conn->prepare("UPDATE deals SET status = 'RELEASED', escrow_status = 'RELEASED', released_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $deal_id);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                echo '<p style="color:green">تم إطلاق المال للبائع بنجاح.</p>';
            } catch (Exception $e) {
                $conn->rollback();
                echo '<p style="color:red">فشل في إطلاق المال للبائع.</p>';
            }
        } elseif ($action === 'refund') {
            // إرجاع المال للإدارة (ليس للمشتري مباشرة)
            $conn->begin_transaction();
            try {
                // الحصول على حساب الإدارة
                $admin_result = $conn->query("SELECT id FROM users WHERE role = 'system' OR role = 'admin' OR is_admin = 1 LIMIT 1");
                $admin_user = $admin_result->fetch_assoc();

                if (!$admin_user) {
                    throw new Exception('لم يتم العثور على حساب الإدارة');
                }

                // إرجاع المبلغ من الرصيد المعلق للمشتري إلى الرصيد المعلق للإدارة
                $stmt = $conn->prepare("UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?");
                $stmt->bind_param('di', $escrow_amount, $buyer_id);
                $stmt->execute();
                $stmt->close();

                // إضافة المبلغ للرصيد المعلق للإدارة
                $stmt = $conn->prepare("UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?");
                $stmt->bind_param('di', $escrow_amount, $admin_user['id']);
                $stmt->execute();
                $stmt->close();

                // تسجيل الحركة المالية (REFUND_TO_ADMIN من المشتري للإدارة)
                $stmt = $conn->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, 'REFUND_TO_ADMIN', ?, ?, ?)");
                $stmt->bind_param('idii', $deal_id, $escrow_amount, $buyer_id, $admin_user['id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE deals SET status = 'REFUNDED', escrow_status = 'REFUNDED', refunded_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $deal_id);
                $stmt->execute();
                $stmt->close();
                $conn->commit();
                echo '<p style="color:green">تم إرجاع المال إلى الإدارة بنجاح. المشتري يجب أن يتواصل مع الدعم للحصول على استرداد كامل.</p>';
            } catch (Exception $e) {
                $conn->rollback();
                echo '<p style="color:red">فشل في إرجاع المال للإدارة: ' . $e->getMessage() . '</p>';
            }
        } elseif ($action === 'cancel_dispute') {
            // إلغاء النزاع فقط
            $stmt = $conn->prepare("UPDATE deals SET status = 'ON_HOLD', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $deal_id);
            if ($stmt->execute()) {
                echo '<p style="color:blue">تم إلغاء النزاع وإعادة الصفقة لوضع الانتظار.</p>';
            } else {
                echo '<p style="color:red">فشل في إلغاء النزاع.</p>';
            }
            $stmt->close();
        }
    } else {
        $stmt->close();
        echo '<p style="color:red">الصفقة غير موجودة أو ليست في حالة نزاع.</p>';
    }
    // إعادة تحميل الصفحة بعد المعالجة
    echo '<meta http-equiv="refresh" content="2">';
}
$conn->close();
?>
</body>
</html>
