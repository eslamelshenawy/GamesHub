<?php
require_once 'api/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح AUTO_INCREMENT</title>
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
        .info {
            background: #0f3460;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success {
            color: #00ff00;
        }
        .error {
            color: #ff4444;
        }
        .warning {
            color: #ffaa00;
        }
        pre {
            background: #000;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        button {
            background: #00d9ff;
            color: #000;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin: 10px 5px;
        }
        button:hover {
            background: #00b8d4;
        }
        button.danger {
            background: #ff4444;
            color: #fff;
        }
        button.danger:hover {
            background: #cc0000;
        }
    </style>
</head>
<body>
    <h1>🔧 فحص وإصلاح AUTO_INCREMENT</h1>

    <h2>1️⃣ بنية جدول accounts الحالية</h2>
    <?php
    $stmt = $pdo->query("SHOW CREATE TABLE accounts");
    $result = $stmt->fetch();
    if ($result) {
        echo "<pre>" . htmlspecialchars($result['Create Table']) . "</pre>";
    }
    ?>

    <h2>2️⃣ معلومات عمود ID</h2>
    <?php
    $stmt = $pdo->query("SHOW COLUMNS FROM accounts WHERE Field='id'");
    $column = $stmt->fetch();
    if ($column) {
        echo "<div class='info'>";
        echo "<strong>Type:</strong> " . $column['Type'] . "<br>";
        echo "<strong>Null:</strong> " . $column['Null'] . "<br>";
        echo "<strong>Key:</strong> " . $column['Key'] . "<br>";
        echo "<strong>Extra:</strong> <span class='" . (strpos($column['Extra'], 'auto_increment') !== false ? 'success' : 'error') . "'>" . $column['Extra'] . "</span><br>";
        echo "</div>";

        if (strpos($column['Extra'], 'auto_increment') === false) {
            echo "<div class='error'><strong>⚠️ مشكلة: AUTO_INCREMENT غير مفعل!</strong></div>";
        }
    }
    ?>

    <h2>3️⃣ أعلى ID موجود في الجدول</h2>
    <?php
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM accounts");
    $max = $stmt->fetch();
    $maxId = $max['max_id'] ? $max['max_id'] : 0;
    echo "<div class='info'>";
    echo "<strong>أعلى ID:</strong> <span class='warning'>{$maxId}</span><br>";
    echo "<strong>الـ AUTO_INCREMENT التالي يجب أن يكون:</strong> <span class='success'>" . ($maxId + 1) . "</span>";
    echo "</div>";
    ?>

    <h2>4️⃣ عدد الصفوف بـ ID = 0</h2>
    <?php
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM accounts WHERE id = 0");
    $zeroCount = $stmt->fetch();
    echo "<div class='info'>";
    echo "<strong>عدد الصفوف بـ ID = 0:</strong> <span class='error'>{$zeroCount['count']}</span>";
    echo "</div>";
    ?>

    <h2>5️⃣ الإصلاح</h2>
    <div class='info'>
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'fix') {
            try {
                // حذف الصفوف بـ ID = 0 أولاً
                echo "<p>🗑️ حذف الصفوف بـ ID = 0...</p>";
                $stmt = $pdo->query("DELETE FROM accounts WHERE id = 0");
                echo "<p class='success'>✅ تم حذف " . $stmt->rowCount() . " صف</p>";

                // حذف الصور المرتبطة بـ account_id = 0
                echo "<p>🗑️ حذف الصور المرتبطة بـ account_id = 0...</p>";
                $stmt = $pdo->query("DELETE FROM account_images WHERE account_id = 0");
                echo "<p class='success'>✅ تم حذف " . $stmt->rowCount() . " صورة</p>";

                // إعادة تعيين AUTO_INCREMENT
                $newAutoIncrement = $maxId + 1;
                echo "<p>🔧 إعادة تعيين AUTO_INCREMENT إلى {$newAutoIncrement}...</p>";

                // الخطوة 1: إضافة PRIMARY KEY أولاً
                echo "<p>🔑 إضافة PRIMARY KEY على عمود id...</p>";
                try {
                    $pdo->exec("ALTER TABLE accounts ADD PRIMARY KEY (id)");
                    echo "<p class='success'>✅ تم إضافة PRIMARY KEY</p>";
                } catch (Exception $e) {
                    // قد يكون PRIMARY KEY موجود بالفعل
                    echo "<p class='warning'>⚠️ PRIMARY KEY موجود بالفعل أو: " . htmlspecialchars($e->getMessage()) . "</p>";
                }

                // الخطوة 2: تفعيل AUTO_INCREMENT
                echo "<p>🔧 تفعيل AUTO_INCREMENT...</p>";
                $pdo->exec("ALTER TABLE accounts MODIFY id INT(11) NOT NULL AUTO_INCREMENT");
                echo "<p class='success'>✅ تم تفعيل AUTO_INCREMENT</p>";

                // الخطوة 3: تعيين القيمة الابتدائية
                echo "<p>🔢 تعيين AUTO_INCREMENT إلى {$newAutoIncrement}...</p>";
                $pdo->exec("ALTER TABLE accounts AUTO_INCREMENT = {$newAutoIncrement}");
                echo "<p class='success'>✅ تم تعيين AUTO_INCREMENT إلى {$newAutoIncrement}</p>";

                echo "<hr><p class='success'><strong>✅ تم الإصلاح بنجاح!</strong></p>";
                echo "<p><a href='check_database.php'><button>عرض النتائج في check_database.php</button></a></p>";
                echo "<p><a href='fix_auto_increment.php'><button>تحديث هذه الصفحة</button></a></p>";

            } catch (Exception $e) {
                echo "<p class='error'>❌ خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p><strong>هل تريد إصلاح المشكلة؟</strong></p>";
            echo "<p class='warning'>⚠️ سيتم:</p>";
            echo "<ul>";
            echo "<li>حذف جميع الصفوف بـ ID = 0 من جدول accounts</li>";
            echo "<li>حذف جميع الصور المرتبطة بـ account_id = 0</li>";
            echo "<li>تفعيل AUTO_INCREMENT على عمود id</li>";
            echo "<li>تعيين AUTO_INCREMENT إلى " . ($maxId + 1) . "</li>";
            echo "</ul>";
            echo "<p><a href='fix_auto_increment.php?action=fix'><button class='danger'>إصلاح الآن</button></a></p>";
        }
        ?>
    </div>

</body>
</html>
