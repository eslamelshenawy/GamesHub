<?php
require_once 'db.php';

try {
    // Add created_at column to users table
    $stmt = $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    
    echo "<h3>Successfully added created_at column to users table</h3>";
    
    // Update existing users with current timestamp
    $stmt = $pdo->exec("UPDATE users SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
    
    echo "<p>Updated existing users with current timestamp</p>";
    
    // Check the updated structure
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Updated table structure:</h3>";
    echo "<pre>";
    foreach ($columns as $column) {
        if ($column['Field'] === 'created_at') {
            echo "✓ " . $column['Field'] . " - " . $column['Type'] . " (ADDED)\n";
        }
    }
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>