<?php
// Add created_at column to users table
require_once 'db.php';

try {
    // Add created_at column
    $sql = "ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `pending_balance`";
    
    $pdo->exec($sql);
    echo "Successfully added created_at column to users table\n";
    
    // Verify the update
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nUsers table columns after update:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>