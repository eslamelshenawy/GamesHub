<?php
/**
 * سكريبت لتعديل كل المسارات من /api/ إلى /api/
 */

$directory = __DIR__;

// أنواع الملفات المراد فحصها
$extensions = ['html', 'js', 'php'];

$files = [];
foreach ($extensions as $ext) {
    $files = array_merge($files, glob($directory . "/*.$ext"));
    $files = array_merge($files, glob($directory . "/js/*.$ext"));
}

echo "=== تعديل المسارات من /api/ إلى /api/ ===\n\n";

$totalFixed = 0;
$filesModified = 0;

foreach ($files as $file) {
    $filename = str_replace($directory . '\\', '', $file);

    // تخطي الملفات المؤقتة
    if (strpos($filename, 'test') !== false || strpos($filename, 'check') !== false) {
        continue;
    }

    $content = file_get_contents($file);
    $originalContent = $content;

    // استبدال /api/ بـ /api/
    $content = str_replace('/api/', '/api/', $content);

    // حساب عدد التعديلات
    $changes = substr_count($originalContent, '/api/');

    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✅ $filename - تم التعديل ($changes مسار)\n";
        $filesModified++;
        $totalFixed += $changes;
    }
}

echo "\n=== النتائج ===\n";
echo "عدد الملفات المعدلة: $filesModified\n";
echo "عدد المسارات المعدلة: $totalFixed\n";
?>
