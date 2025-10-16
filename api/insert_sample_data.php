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
        ['عمر خالد', 'omar@gmail.com', '01412345678', 'password123', 26, 'male', 'admin', 10000],
        ['ياسر إبراهيم', 'yaser@gmail.com', '01512345678', 'password123', 29, 'male', 'buyer', 6000],
        ['منى سعيد', 'mona@gmail.com', '01612345678', 'password123', 24, 'female', 'seller', 4000],
        ['كريم عادل', 'karim@gmail.com', '01712345678', 'password123', 27, 'male', 'buyer', 7000],
        ['نور الدين', 'nour@gmail.com', '01812345678', 'password123', 23, 'female', 'seller', 3500],
        ['طارق محمود', 'tarek@gmail.com', '01912345678', 'password123', 31, 'male', 'buyer', 9000]
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
        [$userIds[1], 'Clash of Clans', 'حساب كلاش اوف كلانس TH14 - قرية ماكس', 4500],
        [$userIds[6], 'Genshin Impact', 'حساب جينشن امباكت AR 55 - شخصيات نادرة', 3500],
        [$userIds[6], 'Valorant', 'حساب فالورانت - رانك دايموند - اسلحة نادرة', 2900],
        [$userIds[8], 'League of Legends', 'حساب ليج اوف ليجندز - رانك بلاتنيوم', 2200],
        [$userIds[8], 'Apex Legends', 'حساب ابكس ليجندز - جميع الشخصيات مفتوحة', 2600],
        [$userIds[6], 'Minecraft', 'حساب ماين كرافت - عالم ضخم - موارد كثيرة', 1500]
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
        [$userIds[0], $userIds[1], 1800, 'صفقة شراء حساب Free Fire', 'CREATED', $accountIds[1], 10],
        [$userIds[5], $userIds[6], 3500, 'صفقة شراء حساب Genshin Impact', 'COMPLETED', $accountIds[5], 10],
        [$userIds[7], $userIds[8], 2600, 'صفقة شراء حساب Apex Legends', 'FUNDED', $accountIds[8], 10],
        [$userIds[2], $userIds[6], 2900, 'صفقة شراء حساب Valorant', 'CREATED', $accountIds[6], 10],
        [$userIds[5], $userIds[8], 2200, 'صفقة شراء حساب League of Legends', 'COMPLETED', $accountIds[7], 10],
        [$userIds[0], $userIds[3], 2800, 'صفقة شراء حساب Fortnite', 'FUNDED', $accountIds[3], 10],
        [$userIds[7], $userIds[6], 1500, 'صفقة شراء حساب Minecraft', 'CREATED', $accountIds[9], 10],
        [$userIds[2], $userIds[1], 4500, 'صفقة شراء حساب Clash of Clans', 'COMPLETED', $accountIds[4], 10]
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
        [$userIds[2], $userIds[3], null, 'الحساب المباع غير صحيح', 'under_review'],
        [$userIds[5], $userIds[6], null, 'البائع يطلب سعر أعلى بعد الاتفاق', 'pending'],
        [$userIds[7], $userIds[8], null, 'الحساب تم حظره من اللعبة', 'resolved'],
        [$userIds[0], $userIds[3], null, 'المستخدم لا يرد على الرسائل', 'under_review'],
        [$userIds[2], $userIds[6], null, 'محاولة احتيال - طلب دفع خارج المنصة', 'dismissed'],
        [$userIds[5], $userIds[1], null, 'بيانات الحساب غير مطابقة للوصف', 'pending'],
        [$userIds[7], $userIds[3], null, 'التأخر في تسليم الحساب', 'under_review'],
        [$userIds[9], $userIds[6], null, 'سلوك غير لائق في المحادثة', 'resolved'],
        [$userIds[8], $userIds[1], null, 'الحساب المباع به مشاكل فنية', 'pending']
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
        [$userIds[1], 'bank_transfer', 3000, '01112345678', 'pending'],
        [$userIds[5], 'vodafone_cash', 6000, '01512345678', 'approved'],
        [$userIds[7], 'instapay', 7000, '01712345678', 'pending'],
        [$userIds[3], 'vodafone_cash', 4500, '01312345678', 'approved'],
        [$userIds[6], 'bank_transfer', 4000, '01612345678', 'rejected'],
        [$userIds[8], 'vodafone_cash', 3500, '01812345678', 'approved'],
        [$userIds[9], 'instapay', 9000, '01912345678', 'pending'],
        [$userIds[4], 'vodafone_cash', 10000, '01412345678', 'approved']
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
        [$userIds[3], 3000, '01312345678', 'instapay', 'pending'],
        [$userIds[6], 2500, '01612345678', 'vodafone_cash', 'approved'],
        [$userIds[8], 3500, '01812345678', 'bank_transfer', 'pending'],
        [$userIds[0], 4000, '01012345678', 'vodafone_cash', 'rejected'],
        [$userIds[2], 5000, '01212345678', 'instapay', 'approved'],
        [$userIds[5], 2800, '01512345678', 'vodafone_cash', 'pending'],
        [$userIds[7], 3200, '01712345678', 'instapay', 'approved'],
        [$userIds[9], 4500, '01912345678', 'vodafone_cash', 'pending'],
        [$userIds[4], 6000, '01412345678', 'bank_transfer', 'approved']
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
