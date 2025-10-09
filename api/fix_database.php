<?php
require_once 'security.php';

try {
    // الاتصال بقاعدة البيانات
    $pdo = new PDO("mysql:host=localhost;dbname=GamesHub_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<h2>إصلاح قاعدة البيانات</h2>";
    
    // التحقق من وجود جدول accounts
    $stmt = $pdo->query("SHOW TABLES LIKE 'accounts'");
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: red;'>جدول accounts غير موجود!</p>";
        // إنشاء الجدول
        $createTable = "
        CREATE TABLE accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            game_name VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        ";
        $pdo->exec($createTable);
        echo "<p style='color: green;'>تم إنشاء جدول accounts بنجاح!</p>";
    } else {
        echo "<p style='color: blue;'>جدول accounts موجود</p>";
    }
    
    // التحقق من الأعمدة الموجودة
    $stmt = $pdo->query("DESCRIBE accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>الأعمدة الموجودة:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";
    
    // إضافة الأعمدة المفقودة
    $requiredColumns = [
        'description' => 'TEXT NOT NULL DEFAULT \'\'',
        'game_name' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
        'price' => 'DECIMAL(10,2) NOT NULL DEFAULT 0'
    ];
    
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $columns)) {
            try {
                $pdo->exec("ALTER TABLE accounts ADD COLUMN $columnName $columnDef");
                echo "<p style='color: green;'>تم إضافة العمود $columnName</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>خطأ في إضافة العمود $columnName: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>العمود $columnName موجود بالفعل</p>";
        }
    }
    
    // عرض البنية النهائية
    echo "<h3>البنية النهائية للجدول:</h3>";
    $stmt = $pdo->query("DESCRIBE accounts");
    $structure = $stmt->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($structure as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green; font-weight: bold;'>تم إصلاح قاعدة البيانات بنجاح!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>خطأ: " . $e->getMessage() . "</p>";
}
?>