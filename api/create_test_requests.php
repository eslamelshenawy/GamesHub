<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    die('غير مصرح - يجب تسجيل الدخول');
}

echo "<h2>إنشاء طلبات تجريبية</h2>";

try {
    // حذف جميع الطلبات القديمة أولاً
    echo "<h3>حذف الطلبات القديمة...</h3>";

    // حذف طلبات الشحن
    $stmt = $pdo->prepare("DELETE FROM wallet_topups");
    $stmt->execute();
    $deleted_topups = $stmt->rowCount();
    echo "✅ تم حذف {$deleted_topups} طلب شحن قديم<br>";

    // حذف طلبات السحب (إذا كان الجدول موجوداً)
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wallet_withdrawals'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("DELETE FROM wallet_withdrawals");
            $stmt->execute();
            $deleted_withdrawals = $stmt->rowCount();
            echo "✅ تم حذف {$deleted_withdrawals} طلب سحب قديم<br>";
        }
    } catch (Exception $e) {
        echo "ℹ️ تم تخطي حذف طلبات السحب<br>";
    }

    echo "<br>";

    // الحصول على مستخدمين
    $stmt = $pdo->query("SELECT id, name FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) < 2) {
        die("يجب أن يكون هناك على الأقل مستخدمين في النظام");
    }

    echo "<h3>المستخدمين المتاحين:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']} - {$user['name']}</li>";
    }
    echo "</ul>";

    // إنشاء طلبات شحن معلقة
    echo "<h3>إنشاء طلبات شحن...</h3>";
    $topup_amounts = [100, 250, 500];
    $topup_ids = [];

    foreach ($topup_amounts as $index => $amount) {
        $user = $users[$index % count($users)];

        $stmt = $pdo->prepare("
            INSERT INTO wallet_topups (user_id, amount, status, created_at)
            VALUES (?, ?, 'pending', NOW())
        ");

        $result = $stmt->execute([$user['id'], $amount]);
        $topup_id = $pdo->lastInsertId();

        if ($topup_id > 0) {
            $topup_ids[] = $topup_id;
            echo "✅ تم إنشاء طلب شحن #{$topup_id} - المبلغ: {$amount} ج.م - المستخدم: {$user['name']}<br>";
        } else {
            echo "❌ فشل إنشاء طلب الشحن - Result: " . ($result ? 'true' : 'false') . " - Last ID: {$topup_id}<br>";

            // عرض آخر طلب تم إدخاله للتحقق
            $check = $pdo->query("SELECT id, user_id, amount FROM wallet_topups ORDER BY id DESC LIMIT 1");
            $last = $check->fetch(PDO::FETCH_ASSOC);
            if ($last) {
                echo "ℹ️ آخر طلب في القاعدة: ID={$last['id']}, User={$last['user_id']}, Amount={$last['amount']}<br>";
            }
        }
    }

    // محاولة إنشاء طلبات سحب (إذا كان الجدول موجوداً)
    echo "<h3>إنشاء طلبات سحب...</h3>";
    $withdraw_ids = [];

    // التحقق من وجود جدول wallet_withdrawals
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'wallet_withdrawals'");
        $table_exists = $stmt->rowCount() > 0;

        if ($table_exists) {
            $withdraw_amounts = [150, 300, 450];

            foreach ($withdraw_amounts as $index => $amount) {
                $user = $users[$index % count($users)];

                // التحقق من رصيد المستخدم وتحديثه إذا لزم الأمر
                $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $current_balance = $stmt->fetchColumn();

                // إضافة رصيد كافي للسحب
                if ($current_balance < $amount) {
                    $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $stmt->execute([$amount + 100, $user['id']]);
                    echo "ℹ️ تم تحديث رصيد {$user['name']} إلى " . ($amount + 100) . " ج.م<br>";
                }

                $stmt = $pdo->prepare("
                    INSERT INTO wallet_withdrawals (user_id, amount, status, created_at)
                    VALUES (?, ?, 'pending', NOW())
                ");

                $stmt->execute([$user['id'], $amount]);
                $withdraw_id = $pdo->lastInsertId();
                $withdraw_ids[] = $withdraw_id;

                echo "✅ تم إنشاء طلب سحب #{$withdraw_id} - المبلغ: {$amount} ج.م - المستخدم: {$user['name']}<br>";
            }
        } else {
            echo "⚠️ جدول wallet_withdrawals غير موجود - تم تخطي طلبات السحب<br>";
        }
    } catch (Exception $e) {
        echo "⚠️ لم يتم إنشاء طلبات السحب: " . $e->getMessage() . "<br>";
    }

    echo "<hr>";
    echo "<h3>✅ تم بنجاح!</h3>";
    echo "<p><strong>طلبات الشحن:</strong> " . count($topup_ids) . " طلبات</p>";
    if (count($withdraw_ids) > 0) {
        echo "<p><strong>طلبات السحب:</strong> " . count($withdraw_ids) . " طلبات</p>";
    }
    echo "<br>";
    echo "<p><a href='../admin-dashboard.html'>العودة للوحة الإدارة</a></p>";

} catch (Exception $e) {
    echo "<div style='color: red;'>خطأ: " . $e->getMessage() . "</div>";
}
?>
