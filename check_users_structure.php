<?php
require_once 'api/db.php';

try {
    $stmt = $pdo->query('DESCRIBE users');
    echo "Users table structure:\n";
    echo "Field\t\tType\t\tNull\tKey\tDefault\tExtra\n";
    echo "-----\t\t----\t\t----\t---\t-------\t-----\n";
    
    while($row = $stmt->fetch()) {
        echo $row['Field'] . "\t\t" . $row['Type'] . "\t\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>