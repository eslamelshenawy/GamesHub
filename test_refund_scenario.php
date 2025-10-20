<?php
/**
 * اختبار سيناريو إرجاع الأموال الكامل
 *
 * السيناريو:
 * 1. إنشاء صفقة جديدة (المشتري يدفع → pending_balance للمشتري)
 * 2. رفض الصفقة من الأدمن (الفلوس تروح من pending_balance المشتري → pending_balance الأدمن)
 * 3. الأدمن يرجع الفلوس للمستخدم (من pending_balance الأدمن → balance المستخدم)
 */

// الاتصال بقاعدة البيانات مباشرة
$host = 'localhost';
$dbname = 'bvize_games_accounts';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage() . "\n");
}

echo "=== اختبار سيناريو إرجاع الأموال الكامل ===\n\n";

try {
    // 1. الحصول على بيانات المستخدمين
    echo "1️⃣ جلب بيانات المستخدمين...\n";

    // الحصول على الأدمن/النظام
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE role = 'admin' OR role = 'system' ORDER BY role DESC LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo "❌ لم يتم العثور على حساب إدارة/نظام!\n";
        echo "   سأقوم بإنشاء حساب نظام...\n";
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES ('System', 'system@system.com', 'xxxxx', 'system')");
        $stmt->execute();
        $admin_id = $pdo->lastInsertId();
        $admin = ['id' => $admin_id, 'name' => 'System', 'email' => 'system@system.com', 'role' => 'system'];
    }

    echo "   ✅ حساب الإدارة: {$admin['name']} (ID: {$admin['id']})\n\n";

    // الحصول على مستخدمين عاديين (مشتري وبائع)
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE (role IS NULL OR role NOT IN ('admin', 'system')) AND id != ? LIMIT 2");
    $stmt->execute([$admin['id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) < 2) {
        echo "❌ لا يوجد مستخدمين كافيين للاختبار!\n";
        exit;
    }

    $buyer = $users[0];
    $seller = $users[1];

    echo "   ✅ المشتري: {$buyer['name']} (ID: {$buyer['id']})\n";
    echo "   ✅ البائع: {$seller['name']} (ID: {$seller['id']})\n\n";

    // 2. التأكد من وجود محافظ للجميع
    echo "2️⃣ التحقق من المحافظ وإنشائها إذا لزم الأمر...\n";

    foreach ([$admin['id'], $buyer['id'], $seller['id']] as $user_id) {
        $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)");
            $stmt->execute([$user_id]);
            echo "   ✅ تم إنشاء محفظة للمستخدم ID: {$user_id}\n";
        }
    }
    echo "\n";

    // 3. عرض الأرصدة قبل البداية
    echo "3️⃣ الأرصدة قبل البداية:\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, COALESCE(w.balance, 0) as balance, COALESCE(w.pending_balance, 0) as pending_balance
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.id IN (?, ?, ?)
    ");
    $stmt->execute([$admin['id'], $buyer['id'], $seller['id']]);
    $wallets_before = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wallets_before as $w) {
        echo sprintf("   %-20s | رصيد: %8.2f | معلق: %8.2f\n", $w['name'], $w['balance'], $w['pending_balance']);
    }
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 4. إضافة رصيد للمشتري للاختبار
    $test_amount = 1000.00;
    echo "4️⃣ إضافة {$test_amount} جنيه للمشتري للاختبار...\n";
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$test_amount, $buyer['id']]);
    echo "   ✅ تمت الإضافة\n\n";

    // 5. إنشاء صفقة جديدة
    $deal_amount = 500.00;
    echo "5️⃣ إنشاء صفقة جديدة بمبلغ {$deal_amount} جنيه...\n";

    $pdo->beginTransaction();

    // خصم من رصيد المشتري وإضافة للرصيد المعلق
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?");
    $stmt->execute([$deal_amount, $deal_amount, $buyer['id']]);

    // إنشاء الصفقة
    $stmt = $pdo->prepare("
        INSERT INTO deals (buyer_id, seller_id, amount, status, escrow_status, created_at, updated_at)
        VALUES (?, ?, ?, 'FUNDED', 'HELD', NOW(), NOW())
    ");
    $stmt->execute([$buyer['id'], $seller['id'], $deal_amount]);
    $deal_id = $pdo->lastInsertId();

    $pdo->commit();

    echo "   ✅ تم إنشاء الصفقة ID: {$deal_id}\n";
    echo "   ✅ تم خصم {$deal_amount} من رصيد المشتري وإضافتها للرصيد المعلق\n\n";

    // 6. عرض الأرصدة بعد إنشاء الصفقة
    echo "6️⃣ الأرصدة بعد إنشاء الصفقة:\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $stmt->execute([$admin['id'], $buyer['id'], $seller['id']]);
    $wallets_after_deal = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wallets_after_deal as $w) {
        echo sprintf("   %-20s | رصيد: %8.2f | معلق: %8.2f\n", $w['name'], $w['balance'], $w['pending_balance']);
    }
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 7. رفض الصفقة من الأدمن (الجزء المهم!)
    echo "7️⃣ رفض الصفقة من الأدمن...\n";
    echo "   📌 يجب أن تنتقل الأموال من pending_balance المشتري → pending_balance الأدمن\n";

    $pdo->beginTransaction();

    // الحصول على تفاصيل الصفقة
    $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?');
    $stmt->execute([$deal_id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);

    $amount_to_refund = $deal['amount'];

    // خصم من الرصيد المعلق للمشتري
    $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?');
    $stmt->execute([$amount_to_refund, $buyer['id']]);

    // إضافة للرصيد المعلق للإدارة
    $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance + ? WHERE user_id = ?');
    $stmt->execute([$amount_to_refund, $admin['id']]);

    // تحديث حالة الصفقة
    $stmt = $pdo->prepare('UPDATE deals SET status = "CANCELLED", escrow_status = "REFUNDED", updated_at = NOW() WHERE id = ?');
    $stmt->execute([$deal_id]);

    // تسجيل في financial_logs
    $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, "REFUND_TO_ADMIN", ?, ?, ?, "رفض الصفقة - إرجاع للإدارة")');
    $stmt->execute([$deal_id, $amount_to_refund, $buyer['id'], $admin['id']]);

    $pdo->commit();

    echo "   ✅ تم رفض الصفقة وإرجاع الأموال للإدارة\n\n";

    // 8. عرض الأرصدة بعد رفض الصفقة
    echo "8️⃣ الأرصدة بعد رفض الصفقة:\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, COALESCE(w.balance, 0) as balance, COALESCE(w.pending_balance, 0) as pending_balance
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.id IN (?, ?, ?)
    ");
    $stmt->execute([$admin['id'], $buyer['id'], $seller['id']]);
    $wallets_after_reject = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wallets_after_reject as $w) {
        echo sprintf("   %-20s | رصيد: %8.2f | معلق: %8.2f\n", $w['name'], $w['balance'], $w['pending_balance']);
    }
    echo "═══════════════════════════════════════════════════════════\n";

    // التحقق من أن الأموال في pending_balance الأدمن
    $admin_wallet = array_filter($wallets_after_reject, function($w) use ($admin) {
        return $w['id'] == $admin['id'];
    });
    $admin_wallet = array_values($admin_wallet)[0];

    if ($admin_wallet['pending_balance'] >= $amount_to_refund) {
        echo "   ✅ الأموال موجودة في الرصيد المعلق للإدارة: {$admin_wallet['pending_balance']} جنيه\n\n";
    } else {
        echo "   ❌ خطأ! الأموال غير موجودة في الرصيد المعلق للإدارة!\n\n";
    }

    // 9. الأدمن يرجع الأموال للمستخدم
    echo "9️⃣ الأدمن يرجع الأموال للمستخدم...\n";
    echo "   📌 يجب أن تنتقل الأموال من pending_balance الأدمن → balance المستخدم\n";

    $pdo->beginTransaction();

    // خصم من الرصيد المعلق للإدارة
    $stmt = $pdo->prepare("UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?");
    $stmt->execute([$amount_to_refund, $admin['id']]);

    // إضافة للرصيد الأساسي للمستخدم
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$amount_to_refund, $buyer['id']]);

    // تسجيل في financial_logs
    $stmt = $pdo->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, 'ADMIN_REFUND', ?, ?, ?, 'استرداد من الإدارة للمستخدم')");
    $stmt->execute([$deal_id, $amount_to_refund, $admin['id'], $buyer['id']]);

    $pdo->commit();

    echo "   ✅ تم إرجاع الأموال للمستخدم بنجاح\n\n";

    // 10. عرض الأرصدة النهائية
    echo "🔟 الأرصدة النهائية:\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, COALESCE(w.balance, 0) as balance, COALESCE(w.pending_balance, 0) as pending_balance
        FROM users u
        LEFT JOIN wallets w ON u.id = w.user_id
        WHERE u.id IN (?, ?, ?)
    ");
    $stmt->execute([$admin['id'], $buyer['id'], $seller['id']]);
    $wallets_final = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($wallets_final as $w) {
        echo sprintf("   %-20s | رصيد: %8.2f | معلق: %8.2f\n", $w['name'], $w['balance'], $w['pending_balance']);
    }
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 11. عرض سجل المعاملات المالية
    echo "1️⃣1️⃣ سجل المعاملات المالية للصفقة:\n";
    echo "═══════════════════════════════════════════════════════════\n";

    $stmt = $pdo->prepare("
        SELECT
            fl.type,
            fl.amount,
            u1.name as from_user_name,
            u2.name as to_user_name,
            fl.description,
            fl.created_at
        FROM financial_logs fl
        LEFT JOIN users u1 ON fl.from_user = u1.id
        LEFT JOIN users u2 ON fl.to_user = u2.id
        WHERE fl.deal_id = ?
        ORDER BY fl.created_at ASC
    ");
    $stmt->execute([$deal_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($logs as $log) {
        echo sprintf("   %s: %s → %s | %8.2f جنيه | %s\n",
            $log['type'],
            $log['from_user_name'] ?: 'النظام',
            $log['to_user_name'] ?: 'النظام',
            $log['amount'],
            $log['description'] ?: ''
        );
    }
    echo "═══════════════════════════════════════════════════════════\n\n";

    // 12. التحقق من النتائج
    echo "1️⃣2️⃣ التحقق من النتائج:\n";

    $buyer_final = array_filter($wallets_final, function($w) use ($buyer) {
        return $w['id'] == $buyer['id'];
    });
    $buyer_final = array_values($buyer_final)[0];

    $admin_final = array_filter($wallets_final, function($w) use ($admin) {
        return $w['id'] == $admin['id'];
    });
    $admin_final = array_values($admin_final)[0];

    $buyer_expected_balance = $test_amount; // نفس المبلغ الأصلي (1000) لأن الفلوس رجعت
    $buyer_expected_pending = 0; // يجب أن يكون صفر

    echo "   المشتري - الرصيد المتوقع: {$buyer_expected_balance} | الفعلي: {$buyer_final['balance']}\n";
    echo "   المشتري - الرصيد المعلق المتوقع: {$buyer_expected_pending} | الفعلي: {$buyer_final['pending_balance']}\n";
    echo "   الإدارة - الرصيد المعلق المتوقع: 0 | الفعلي: {$admin_final['pending_balance']}\n\n";

    if (abs($buyer_final['balance'] - $buyer_expected_balance) < 0.01 &&
        abs($buyer_final['pending_balance'] - $buyer_expected_pending) < 0.01 &&
        abs($admin_final['pending_balance']) < 0.01) {
        echo "   ✅✅✅ النتائج صحيحة! السيناريو عمل بنجاح! ✅✅✅\n";
    } else {
        echo "   ❌ هناك خطأ في الأرصدة!\n";
    }

    echo "\n=== انتهى الاختبار ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
