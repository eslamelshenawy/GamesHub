<?php
/**
 * سكريبت لإرجاع كل المسارات من /api/ إلى /api/api/
 */

$directory = __DIR__;

// أنواع الملفات المراد فحصها
$extensions = ['html', 'js', 'php'];

$files = [];
foreach ($extensions as $ext) {
    $files = array_merge($files, glob($directory . "/*.$ext"));
    $files = array_merge($files, glob($directory . "/js/*.$ext"));
}

echo "=== إرجاع المسارات من /api/ إلى /api/api/ ===\n\n";

$totalFixed = 0;
$filesModified = 0;

foreach ($files as $file) {
    $filename = str_replace($directory . '\\', '', $file);

    // تخطي الملفات المؤقتة وملفات الإصلاح
    if (strpos($filename, 'test') !== false ||
        strpos($filename, 'check') !== false ||
        strpos($filename, 'fix_') !== false ||
        strpos($filename, 'restore_') !== false) {
        continue;
    }

    $content = file_get_contents($file);
    $originalContent = $content;

    // استبدال /api/ بـ /api/api/ (بحذر لتجنب /api/api/api/)
    $content = preg_replace('#(["\'])(/api/)(?!api/)#', '$1$2api/', $content);

    // حساب عدد التعديلات
    preg_match_all('#(["\'])(/api/)(?!api/)#', $originalContent, $matches);
    $changes = count($matches[0]);

    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✅ $filename - تم الإرجاع ($changes مسار)\n";
        $filesModified++;
        $totalFixed += $changes;
    }
}

echo "\n=== النتائج ===\n";
echo "عدد الملفات المعدلة: $filesModified\n";
echo "عدد المسارات المرجعة: $totalFixed\n";
?>
