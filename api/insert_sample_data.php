<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->beginTransaction();

    // 1. Add Users
    echo "Adding users...\n";
    $users = [
        ['احمد محمد', 'ahmed@gmail.com', '01012345678', 'password123', 25, 'male', 'buyer', 5000],
        ['سارة علي', 'sara@gmail.com', '01112345678', 'password123', 22, 'female', 'seller', 3000],
        ['محمود حسن', 'mahmoud@gmail.com', '01212345678', 'password123', 30, 'male', 'buyer', 8000],
        ['فاطمة عبدالله', 'fatma@gmail.com', '01312345678', 'password123', 28, 'female', 'seller', 4500],
        ['عمر خالد', 'omar@gmail.com', '01412345678', 'password123', 26, 'male', 'admin', 10000]
    ];

    $userIds = [];
    foreach ($users as $user) {
        $hashedPassword = password_hash($user[3], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, age, gender, role, balance, wallet_balance)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user[0], $user[1], $user[2], $hashedPassword, $user[4], $user[5], $user[6], $user[7], $user[7]]);
        $userId = $pdo->lastInsertId();
        $userIds[] = $userId;

        // Create wallet for user
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
        $stmt->execute([$userId, $user[7]]);

        echo "Added user: {$user[0]} (ID: $userId)\n";
    }

    // 2. Add Game Accounts
    echo "\nAdding game accounts...\n";
    $accounts = [
        [$userIds[1], 'PUBG Mobile', 'حساب ببجي موبايل مستوى 80 - كونكر - سيزون 20', 2500],
        [$userIds[1], 'Free Fire', 'حساب فري فاير مستوى 65 - دايموند 50000', 1800],
        [$userIds[3], 'Call of Duty', 'حساب كود موبايل مستوى 150 - ليجندري', 3200],
        [$userIds[3], 'Fortnite', 'حساب فورتنايت - سكنات نادرة - 200 V-Bucks', 2800],
        [$userIds[1], 'Clash of Clans', 'حساب كلاش اوف كلانس TH14 - قرية ماكس', 4500]
    ];

    $accountIds = [];
    foreach ($accounts as $account) {
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, game_name, description, price, created_at)
                              VALUES (?, ?, ?, ?, CURDATE())");
        $stmt->execute($account);
        $accountId = $pdo->lastInsertId();
        $accountIds[] = $accountId;
        echo "Added account: {$account[1]} (ID: $accountId)\n";
    }

    // 3. Add Deals
    echo "\nAdding deals...\n";
    $deals = [
        [$userIds[0], $userIds[1], 2500, 'صفقة شراء حساب PUBG Mobile', 'COMPLETED', $accountIds[0], 10],
        [$userIds[2], $userIds[3], 3200, 'صفقة شراء حساب Call of Duty', 'FUNDED', $accountIds[2], 10],
        [$userIds[0], $userIds[1], 1800, 'صفقة شراء حساب Free Fire', 'CREATED', $accountIds[1], 10]
    ];

    $dealIds = [];
    foreach ($deals as $deal) {
        $platformFee = ($deal[2] * $deal[6]) / 100;
        $sellerAmount = $deal[2] - $platformFee;

        $stmt = $pdo->prepare("INSERT INTO deals (buyer_id, seller_id, amount, details, status, account_id,
                              platform_fee, seller_amount, fee_percentage)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$deal[0], $deal[1], $deal[2], $deal[3], $deal[4], $deal[5],
                       $platformFee, $sellerAmount, $deal[6]]);
        $dealId = $pdo->lastInsertId();
        $dealIds[] = $dealId;
        echo "Added deal: {$deal[3]} (ID: $dealId)\n";
    }

    // 4. Add Reports
    echo "\nAdding reports...\n";
    $reports = [
        [$userIds[0], $userIds[1], null, 'المستخدم لم يقم بتسليم الحساب بعد الدفع', 'pending'],
        [$userIds[2], $userIds[3], null, 'الحساب المباع غير صحيح', 'under_review']
    ];

    $reportIds = [];
    foreach ($reports as $report) {
        $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_user_id, conversation_id, reason, status)
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($report);
        $reportId = $pdo->lastInsertId();
        $reportIds[] = $reportId;
        echo "Added report ID: $reportId\n";
    }

    // 5. Add Wallet Topups
    echo "\nAdding wallet topups...\n";
    $topups = [
        [$userIds[0], 'vodafone_cash', 5000, '01012345678', 'approved'],
        [$userIds[2], 'instapay', 8000, '01212345678', 'approved'],
        [$userIds[1], 'bank_transfer', 3000, '01112345678', 'pending']
    ];

    $topupIds = [];
    foreach ($topups as $topup) {
        $stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, method, amount, phone, status, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute($topup);
        $topupId = $pdo->lastInsertId();
        $topupIds[] = $topupId;
        echo "Added topup ID: $topupId\n";

        // Update wallet balance if approved
        if ($topup[4] === 'approved') {
            $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
            $stmt->execute([$topup[2], $topup[0]]);

            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at)
                                  VALUES (?, ?, 'deposit', 'شحن المحفظة', NOW())");
            $stmt->execute([$topup[0], $topup[2]]);
        }
    }

    // 6. Add Withdrawal Requests
    echo "\nAdding withdrawal requests...\n";
    $withdrawals = [
        [$userIds[1], 2000, '01112345678', 'vodafone_cash', 'approved'],
        [$userIds[3], 3000, '01312345678', 'instapay', 'pending']
    ];

    $withdrawalIds = [];
    foreach ($withdrawals as $withdrawal) {
        $stmt = $pdo->prepare("INSERT INTO withdraw_requests (user_id, amount, phone, method, status, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute($withdrawal);
        $withdrawalId = $pdo->lastInsertId();
        $withdrawalIds[] = $withdrawalId;
        echo "Added withdrawal ID: $withdrawalId\n";

        // Update wallet balance if approved
        if ($withdrawal[4] === 'approved') {
            $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
            $stmt->execute([$withdrawal[1], $withdrawal[0]]);

            $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at)
                                  VALUES (?, ?, 'withdraw', 'سحب من المحفظة', NOW())");
            $stmt->execute([$withdrawal[0], $withdrawal[1]]);
        }
    }

    $pdo->commit();

    echo "\n\nSuccess! Sample data inserted successfully!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Summary:\n";
    echo "- Users: " . count($userIds) . "\n";
    echo "- Accounts: " . count($accountIds) . "\n";
    echo "- Deals: " . count($dealIds) . "\n";
    echo "- Reports: " . count($reportIds) . "\n";
    echo "- Topups: " . count($topupIds) . "\n";
    echo "- Withdrawals: " . count($withdrawalIds) . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    echo json_encode([
        'success' => true,
        'message' => 'Sample data inserted successfully',
        'summary' => [
            'users' => count($userIds),
            'accounts' => count($accountIds),
            'deals' => count($dealIds),
            'reports' => count($reportIds),
            'topups' => count($topupIds),
            'withdrawals' => count($withdrawalIds)
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
