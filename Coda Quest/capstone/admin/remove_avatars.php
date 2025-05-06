<?php
// Script to remove user-avatar divs from all admin pages
$adminDir = __DIR__;
$files = glob($adminDir . '/*.php');
$pattern = '/<div class="user-avatar">.*?<\/div>/s';
$replacement = '<!-- User avatar removed -->';
$count = 0;

foreach ($files as $file) {
    if ($file === __FILE__) continue; // Skip this script
    
    $content = file_get_contents($file);
    $newContent = preg_replace($pattern, $replacement, $content, -1, $replacements);
    
    if ($replacements > 0) {
        file_put_contents($file, $newContent);
        $count += $replacements;
        echo "Updated " . basename($file) . " - Removed $replacements avatar(s)\n";
    }
}

echo "Completed! Removed $count user-avatar divs from admin pages.";
?>

