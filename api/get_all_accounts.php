<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');
// جلب جميع الحسابات مع الصور وإعلام ما إذا كانت في مفضلة المستخدم الحالي
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$sql = "SELECT a.id, a.game_name, a.description, a.price, a.created_at,
               GROUP_CONCAT(ai.image_path ORDER BY ai.id) as images,
               (SELECT 1 FROM favorites f WHERE f.user_id = ? AND f.account_id = a.id LIMIT 1) as is_favorite
        FROM accounts a
        LEFT JOIN account_images ai ON a.id = ai.account_id
        GROUP BY a.id
        ORDER BY a.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();
foreach($accounts as &$acc){
    $acc['images'] = $acc['images'] ? explode(',', $acc['images']) : [];
    $acc['is_favorite'] = $acc['is_favorite'] ? true : false;
}
echo json_encode($accounts);
exit;
 