<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'config/db_connect.php';

// Get level ID from URL 
$levelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($levelId <= 0) {
    die("Please specify a level ID in the URL (e.g., ?id=1)");
}

echo "<h1>Detailed Level Debug for ID: $levelId</h1>";

// STEP 1: Check if level exists
echo "<h2>Step 1: Check if level exists</h2>";
$checkSql = "SELECT COUNT(*) as count FROM levels WHERE level_id = ?";
$checkResult = executeQuery($checkSql, [$levelId]);
$levelExists = ($checkResult[0]['count'] > 0);
echo "Level exists: " . ($levelExists ? "YES" : "NO") . "<br>";

if (!$levelExists) {
    die("Level with ID $levelId does not exist in the database.");
}

// STEP 2: Check for column existence
echo "<h2>Step 2: Check column existence</h2>";
$columnSql = "SHOW COLUMNS FROM levels";
$columns = executeQuery($columnSql);
$hasNotesCol = false;
$hasMediaCol = false;

echo "<ul>";
foreach ($columns as $col) {
    echo "<li>{$col['Field']} ({$col['Type']})</li>";
    if ($col['Field'] === 'notes') $hasNotesCol = true;
    if ($col['Field'] === 'notes_media') $hasMediaCol = true;
}
echo "</ul>";

echo "Has 'notes' column: " . ($hasNotesCol ? "YES" : "NO") . "<br>";
echo "Has 'notes_media' column: " . ($hasMediaCol ? "YES" : "NO") . "<br>";

// STEP 3: Fetch level data
echo "<h2>Step 3: Fetch complete level data</h2>";
$levelSql = "SELECT * FROM levels WHERE level_id = ?";
$levelData = executeQuery($levelSql, [$levelId]);

if (empty($levelData)) {
    die("Failed to retrieve level data.");
}

$level = $levelData[0];
echo "Level Name: " . htmlspecialchars($level['level_name']) . "<br>";
echo "Description: " . htmlspecialchars(substr($level['description'], 0, 50)) . "...<br>";
echo "Is Active: " . ($level['is_active'] ? "YES" : "NO") . "<br>";

// STEP 4: Check notes content
echo "<h2>Step 4: Check notes content</h2>";
if (isset($level['notes'])) {
    echo "Notes field exists in result: YES<br>";
    echo "Notes is empty: " . (empty($level['notes']) ? "YES" : "NO") . "<br>";
    if (!empty($level['notes'])) {
        echo "Notes content length: " . strlen($level['notes']) . " characters<br>";
        echo "First 100 characters: " . htmlspecialchars(substr($level['notes'], 0, 100)) . "...<br>";
    }
} else {
    echo "Notes field does not exist in query result!<br>";
}

// STEP 5: Check media content
echo "<h2>Step 5: Check media content</h2>";
if (isset($level['notes_media'])) {
    echo "Notes Media field exists in result: YES<br>";
    echo "Notes Media is empty: " . (empty($level['notes_media']) ? "YES" : "NO") . "<br>";
    if (!empty($level['notes_media'])) {
        echo "Media path: " . htmlspecialchars($level['notes_media']) . "<br>";
        echo "File exists: " . (file_exists($level['notes_media']) ? "YES" : "NO") . "<br>";
        
        // Check various path combinations
        $paths = [
            $level['notes_media'],
            './' . $level['notes_media'],
            '../' . $level['notes_media'],
            '/' . ltrim($level['notes_media'], '/'),
            'C:/wamp64/www/capstone/' . $level['notes_media']
        ];
        
        echo "<h3>Path checks:</h3>";
        echo "<ul>";
        foreach ($paths as $index => $path) {
            echo "<li>Path $index: $path - " . (file_exists($path) ? "EXISTS" : "NOT FOUND") . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "Notes Media field does not exist in query result!<br>";
}

// STEP 6: Test display
echo "<h2>Step 6: Test display</h2>";
echo "<h3>Notes Display</h3>";
if (!empty($level['notes'])) {
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px;'>";
    echo nl2br(htmlspecialchars($level['notes']));
    echo "</div>";
} else {
    echo "<p>No notes to display.</p>";
}

echo "<h3>Media Display</h3>";
if (!empty($level['notes_media'])) {
    $mediaUrl = $level['notes_media'];
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $mediaUrl)) {
        echo "<p>Direct path image:</p>";
        echo "<img src='" . htmlspecialchars($mediaUrl) . "' style='max-width: 300px;'><br><br>";
    }
}

// STEP 7: Trace level.php execution
echo "<h2>Step 7: Simulate level.php execution</h2>";
echo "Checking condition in level.php: <code>if (!empty(\$level['notes']))</code><br>";
echo "Result: " . (!empty($level['notes']) ? "TRUE - should display notes" : "FALSE - would not display notes") . "<br><br>";

echo "Checking condition in level.php: <code>if (!empty(\$level['notes_media']))</code><br>";
echo "Result: " . (!empty($level['notes_media']) ? "TRUE - should display media" : "FALSE - would not display media") . "<br>";

// In-context image test with browser path
echo "<h2>Final Image Test</h2>";
if (!empty($level['notes_media']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $level['notes_media'])) {
    echo "<img src='" . htmlspecialchars($level['notes_media']) . "' style='max-width: 300px; border: 2px solid green;'>";
    echo "<p>If the image above is visible, the path is correct for the browser.</p>";
}
?> 