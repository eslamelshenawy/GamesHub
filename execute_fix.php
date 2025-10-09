<?php
// Execute database fix for deals_with_users view
require_once 'api/db.php';

try {
    // Read the SQL fix file
    $sql = file_get_contents('fix_view.sql');
    
    // Split by semicolon to execute multiple statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n<br>";
        }
    }
    
    echo "<br><strong>SUCCESS: deals_with_users view has been updated successfully!</strong><br>";
    echo "The 'description' column reference has been replaced with an empty string.";
    
} catch (Exception $e) {
    echo "<strong>ERROR:</strong> " . $e->getMessage();
}
?>