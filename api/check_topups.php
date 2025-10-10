<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();

if (!isset($_SESSION['user_id'])) {
    die('غير مصرح - يجب تسجيل الدخول');
}

echo "<h2>فحص طلبات الشحن في قاعدة البيانات</h2>";

try {
    // جلب جميع الطلبات
    $stmt = $pdo->query("SELECT id, user_id, amount, status, created_at FROM wallet_topups ORDER BY id DESC LIMIT 10");
    $topups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($topups) === 0) {
        echo "<p>لا توجد طلبات شحن في قاعدة البيانات</p>";
    } else {
        echo "<h3>آخر 10 طلبات:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>
                <th style='padding: 8px;'>ID</th>
                <th style='padding: 8px;'>User ID</th>
                <th style='padding: 8px;'>Amount</th>
                <th style='padding: 8px;'>Status</th>
                <th style='padding: 8px;'>Created At</th>
              </tr>";

        foreach ($topups as $topup) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>{$topup['id']}</td>";
            echo "<td style='padding: 8px;'>{$topup['user_id']}</td>";
            echo "<td style='padding: 8px;'>{$topup['amount']}</td>";
            echo "<td style='padding: 8px;'>{$topup['status']}</td>";
            echo "<td style='padding: 8px;'>{$topup['created_at']}</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    // معلومات عن الجدول
    echo "<br><h3>معلومات عن جدول wallet_topups:</h3>";
    $stmt = $pdo->query("DESCRIBE wallet_topups");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr>
            <th style='padding: 8px;'>Column</th>
            <th style='padding: 8px;'>Type</th>
            <th style='padding: 8px;'>Null</th>
            <th style='padding: 8px;'>Key</th>
            <th style='padding: 8px;'>Extra</th>
          </tr>";

    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>{$col['Field']}</td>";
        echo "<td style='padding: 8px;'>{$col['Type']}</td>";
        echo "<td style='padding: 8px;'>{$col['Null']}</td>";
        echo "<td style='padding: 8px;'>{$col['Key']}</td>";
        echo "<td style='padding: 8px;'>{$col['Extra']}</td>";
        echo "</tr>";
    }

    echo "</table>";

} catch (Exception $e) {
    echo "<div style='color: red;'>خطأ: " . $e->getMessage() . "</div>";
}
?>
