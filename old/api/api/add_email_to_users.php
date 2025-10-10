<?php
require_once 'db.php';

try {
    // إضافة حقل email إلى جدول users
    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE AFTER phone";
    $pdo->exec($sql);
    echo "تم إضافة حقل email بنجاح إلى جدول users\n";
    
    // التحقق من التحديث
    $stmt = $pdo->query('DESCRIBE users');
    echo "\nهيكل الجدول بعد التحديث:\n";
    echo "Field\t\tType\t\tNull\tKey\n";
    echo "-----\t\t----\t\t----\t---\n";
    
    while($row = $stmt->fetch()) {
        echo $row['Field'] . "\t\t" . $row['Type'] . "\t\t" . $row['Null'] . "\t" . $row['Key'] . "\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
?>