<?php
/**
 * سكريبت لإضافة session-manager.js لكل ملفات HTML
 */

$directory = __DIR__;
$sessionManagerScript = '<script src="js/session-manager.js"></script>';

// البحث عن كل ملفات HTML
$htmlFiles = glob($directory . '/*.html');

echo "=== إضافة Session Manager لملفات HTML ===\n\n";

$updatedCount = 0;
$skippedCount = 0;

foreach ($htmlFiles as $file) {
    $filename = basename($file);

    // تخطي صفحات لا تحتاج session check
    $skipPages = ['login.html', 'signup.html', 'forget-password.html', 'forget-pass.html'];
    if (in_array($filename, $skipPages)) {
        echo "⏭️  تخطي: $filename (صفحة عامة)\n";
        $skippedCount++;
        continue;
    }

    $content = file_get_contents($file);

    // التحقق إذا كان السكريبت موجود بالفعل
    if (strpos($content, 'session-manager.js') !== false) {
        echo "✓  موجود بالفعل: $filename\n";
        $skippedCount++;
        continue;
    }

    // البحث عن ban-check.js أو أي سكريبت آخر قبل </body>
    if (strpos($content, 'ban-check.js') !== false) {
        // إضافة قبل ban-check.js
        $content = str_replace(
            '<script src="js/ban-check.js"></script>',
            $sessionManagerScript . "\n" . '<script src="js/ban-check.js"></script>',
            $content
        );
    } elseif (strpos($content, '</body>') !== false) {
        // إضافة قبل </body> مباشرة
        $content = str_replace(
            '</body>',
            $sessionManagerScript . "\n" . '</body>',
            $content
        );
    } else {
        echo "⚠️  لم يتم العثور على </body> في: $filename\n";
        $skippedCount++;
        continue;
    }

    // حفظ الملف
    file_put_contents($file, $content);
    echo "✅ تم التحديث: $filename\n";
    $updatedCount++;
}

echo "\n=== النتائج ===\n";
echo "تم التحديث: $updatedCount\n";
echo "تم التخطي: $skippedCount\n";
echo "المجموع: " . ($updatedCount + $skippedCount) . "\n";
?>
