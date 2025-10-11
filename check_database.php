<?php
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فحص قاعدة البيانات</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #1a1a2e;
            color: #eee;
        }
        h2 {
            color: #00d9ff;
            border-bottom: 2px solid #00d9ff;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #16213e;
        }
        th, td {
            border: 1px solid #0f3460;
            padding: 12px;
            text-align: right;
        }
        th {
            background: #0f3460;
            color: #00d9ff;
            font-weight: bold;
        }
        tr:hover {
            background: #1a2942;
        }
        .info {
            background: #0f3460;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success {
            color: #00ff00;
        }
        .warning {
            color: #ffaa00;
        }
    </style>
</head>
<body>
    <h1>فحص قاعدة البيانات - Accounts & Images</h1>

    <div class="info">
        <strong>تم إنشاء هذا الملف للفحص السريع</strong><br>
        اسم الجدول: <span class="success">accounts</span> و <span class="success">account_images</span>
    </div>

    <h2>1️⃣ حالة AUTO_INCREMENT لجدول accounts</h2>
    <?php
    $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name='accounts'");
    $status = $stmt->fetch();
    if ($status) {
        echo "<div class='info'>";
        echo "<strong>Auto_increment القيمة الحالية:</strong> <span class='success'>" . $status['Auto_increment'] . "</span><br>";
        echo "<strong>Engine:</strong> " . $status['Engine'] . "<br>";
        echo "<strong>عدد الصفوف:</strong> " . $status['Rows'];
        echo "</div>";
    }
    ?>

    <h2>2️⃣ آخر 10 حسابات من جدول accounts</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User ID</th>
                <th>اسم اللعبة</th>
                <th>الوصف</th>
                <th>السعر</th>
                <th>تاريخ الإنشاء</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT id, user_id, game_name, description, price, created_at FROM accounts ORDER BY id DESC LIMIT 10");
            $accounts = $stmt->fetchAll();
            if (count($accounts) > 0) {
                foreach ($accounts as $acc) {
                    $idClass = ($acc['id'] == 0) ? 'warning' : 'success';
                    echo "<tr>";
                    echo "<td class='{$idClass}'><strong>" . htmlspecialchars($acc['id']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($acc['user_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($acc['game_name']) . "</td>";
                    echo "<td>" . (strlen($acc['description']) > 50 ? substr(htmlspecialchars($acc['description']), 0, 50) . '...' : htmlspecialchars($acc['description'])) . "</td>";
                    echo "<td>" . htmlspecialchars($acc['price']) . "</td>";
                    echo "<td>" . htmlspecialchars($acc['created_at']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center; color: #ffaa00;'>لا توجد حسابات</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <h2>3️⃣ آخر 15 صورة من جدول account_images</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Account ID</th>
                <th>مسار الصورة</th>
                <th>تاريخ الرفع</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT id, account_id, image_path, uploaded_at FROM account_images ORDER BY id DESC LIMIT 15");
            $images = $stmt->fetchAll();
            if (count($images) > 0) {
                foreach ($images as $img) {
                    $accountIdClass = ($img['account_id'] == 0) ? 'warning' : 'success';
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($img['id']) . "</td>";
                    echo "<td class='{$accountIdClass}'><strong>" . htmlspecialchars($img['account_id']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($img['image_path']) . "</td>";
                    echo "<td>" . htmlspecialchars($img['uploaded_at']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4' style='text-align:center; color: #ffaa00;'>لا توجد صور</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <h2>4️⃣ عدد الصور لكل حساب</h2>
    <table>
        <thead>
            <tr>
                <th>Account ID</th>
                <th>عدد الصور</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT account_id, COUNT(*) as image_count FROM account_images GROUP BY account_id ORDER BY account_id DESC LIMIT 10");
            $counts = $stmt->fetchAll();
            if (count($counts) > 0) {
                foreach ($counts as $cnt) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($cnt['account_id']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($cnt['image_count']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='2' style='text-align:center; color: #ffaa00;'>لا توجد بيانات</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <div class="info" style="margin-top: 30px;">
        <strong>ملاحظة:</strong><br>
        - إذا كان ID = 0 (باللون <span class="warning">البرتقالي</span>)، فهناك مشكلة في AUTO_INCREMENT<br>
        - إذا كان ID > 0 (باللون <span class="success">الأخضر</span>)، فكل شيء يعمل بشكل صحيح
    </div>
</body>
</html>
