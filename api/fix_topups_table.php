<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();

if (!isset($_SESSION['user_id'])) {
    die('غير مصرح - يجب تسجيل الدخول');
}

echo "<h2>إصلاح جدول wallet_topups</h2>";

try {
    // حذف جميع الطلبات ذات ID = 0
    echo "<h3>الخطوة 1: حذف الطلبات التالفة (ID = 0)...</h3>";
    $stmt = $pdo->prepare("DELETE FROM wallet_topups WHERE id = 0");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    echo "✅ تم حذف {$deleted} طلب تالف<br><br>";

    // إضافة AUTO_INCREMENT لعمود id
    echo "<h3>الخطوة 2: إضافة AUTO_INCREMENT لعمود id...</h3>";

    // أولاً: جعل id PRIMARY KEY و AUTO_INCREMENT
    try {
        $pdo->exec("ALTER TABLE wallet_topups MODIFY id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
        echo "✅ تم تعديل عمود id بنجاح<br><br>";
    } catch (Exception $e) {
        echo "ℹ️ محاولة طريقة بديلة...<br>";

        // إذا كان هناك primary key موجود، نحذفه أولاً
        try {
            $pdo->exec("ALTER TABLE wallet_topups DROP PRIMARY KEY");
            echo "ℹ️ تم حذف PRIMARY KEY القديم<br>";
        } catch (Exception $e2) {
            echo "ℹ️ لا يوجد PRIMARY KEY قديم<br>";
        }

        // ثم نضيف الجديد
        $pdo->exec("ALTER TABLE wallet_topups MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
        $pdo->exec("ALTER TABLE wallet_topups ADD PRIMARY KEY (id)");
        echo "✅ تم تعديل عمود id بنجاح<br><br>";
    }

    // التحقق من التعديل
    echo "<h3>الخطوة 3: التحقق من التعديل...</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wallet_topups WHERE Field = 'id'");
    $id_column = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr>
            <th style='padding: 8px;'>Column</th>
            <th style='padding: 8px;'>Type</th>
            <th style='padding: 8px;'>Key</th>
            <th style='padding: 8px;'>Extra</th>
          </tr>";
    echo "<tr>";
    echo "<td style='padding: 8px;'>{$id_column['Field']}</td>";
    echo "<td style='padding: 8px;'>{$id_column['Type']}</td>";
    echo "<td style='padding: 8px;'>{$id_column['Key']}</td>";
    echo "<td style='padding: 8px; " . (strpos($id_column['Extra'], 'auto_increment') !== false ? 'color: green; font-weight: bold;' : 'color: red;') . "'>{$id_column['Extra']}</td>";
    echo "</tr>";
    echo "</table><br>";

    if (strpos($id_column['Extra'], 'auto_increment') !== false) {
        echo "<div style='color: green; font-weight: bold; font-size: 18px;'>✅ تم الإصلاح بنجاح!</div><br>";
        echo "<p>الآن يمكنك إنشاء طلبات جديدة باستخدام <a href='create_test_requests.php'>create_test_requests.php</a></p>";
    } else {
        echo "<div style='color: red; font-weight: bold;'>❌ فشل الإصلاح - يرجى المحاولة يدوياً</div>";
    }

} catch (Exception $e) {
    echo "<div style='color: red;'>خطأ: " . $e->getMessage() . "</div>";
}
?>
