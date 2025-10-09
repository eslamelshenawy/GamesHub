<?php
require_once 'db.php';

try {
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Users table structure:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check if created_at exists
    $hasCreatedAt = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'created_at') {
            $hasCreatedAt = true;
            break;
        }
    }
    
    echo "<h3>Created_at field exists: " . ($hasCreatedAt ? 'YES' : 'NO') . "</h3>";
    
    if (!$hasCreatedAt) {
        echo "<p>Need to add created_at field to users table</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>