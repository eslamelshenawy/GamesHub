<?php
// استدعاء الاتصال من db.php
require 'db.php';

// جلب كل أسماء الجداول
$queryTables = $conn->query("SHOW TABLES");

if ($queryTables) {
    while ($row = $queryTables->fetch_array()) {
        $table = $row[0];
        echo "<h2>📋 جدول: $table</h2>";

        // جلب أسماء الأعمدة لكل جدول
        $queryColumns = $conn->query("SHOW COLUMNS FROM `$table`");

        if ($queryColumns) {
            echo "<ul>";
            while ($col = $queryColumns->fetch_assoc()) {
                echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
            }
            echo "</ul>";
        } else {
            echo "❌ فشل جلب الأعمدة.<br>";
        }
    }
} else {
    echo "❌ فشل جلب أسماء الجداول.";
}

$conn->close();
?>