<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db_connect.php';

// Get level ID from URL or default to 1
$levelId = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Fetch level data
$sql = "SELECT level_id, level_name, notes, notes_media FROM levels WHERE level_id = ?";
$levelData = executeQuery($sql, [$levelId]);

// Display level data
echo "<h1>Level Debug</h1>";
echo "<pre>";
print_r($levelData);
echo "</pre>";

if (!empty($levelData) && isset($levelData[0])) {
    $level = $levelData[0];
    
    echo "<h2>Notes Content</h2>";
    if (!empty($level['notes'])) {
        echo "<div style='background: #f5f5f5; padding: 15px; border: 1px solid #ddd;'>";
        echo nl2br(htmlspecialchars($level['notes']));
        echo "</div>";
    } else {
        echo "<p>No notes content found.</p>";
    }
    
    echo "<h2>Media Content</h2>";
    if (!empty($level['notes_media'])) {
        echo "<p>Media path: " . htmlspecialchars($level['notes_media']) . "</p>";
        
        // Try different path approaches
        $paths = [
            $level['notes_media'],
            '../' . $level['notes_media'],
            './' . $level['notes_media'],
            '/' . $level['notes_media'],
            str_replace('uploads/', '../uploads/', $level['notes_media'])
        ];
        
        echo "<h3>File Existence Check</h3>";
        foreach ($paths as $index => $path) {
            echo "<p>Path $index: $path - " . (file_exists($path) ? "EXISTS" : "NOT FOUND") . "</p>";
        }
        
        // Try to display the image/media
        echo "<h3>Display Attempts</h3>";
        
        // Direct path
        echo "<p>Direct path:</p>";
        echo "<img src='" . htmlspecialchars($level['notes_media']) . "' style='max-width: 300px;' />";
        
        // With ../ prefix
        echo "<p>With ../ prefix:</p>";
        echo "<img src='../" . htmlspecialchars($level['notes_media']) . "' style='max-width: 300px;' />";
    } else {
        echo "<p>No media content found.</p>";
    }
}
?> 