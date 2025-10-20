<?php
$pdo = new PDO('mysql:host=localhost;dbname=bvize_games_accounts', 'root', '');
$stmt = $pdo->query('DESCRIBE users');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>
