<?php
require_once 'api/db.php';

// التحقق من جميع الصفقات
$stmt = $pdo->query('SELECT * FROM deals');
$all_deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "جميع الصفقات في قاعدة البيانات:\n";
echo json_encode($all_deals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// التحقق من الصفقات للمحادثة رقم 1
$stmt = $pdo->prepare('SELECT * FROM deals WHERE conversation_id = ?');
$stmt->execute([1]);
$conversation_deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "الصفقات للمحادثة رقم 1:\n";
echo json_encode($conversation_deals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

// التحقق من المحادثات الموجودة
$stmt = $pdo->query('SELECT * FROM conversations');
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "المحادثات الموجودة:\n";
echo json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>