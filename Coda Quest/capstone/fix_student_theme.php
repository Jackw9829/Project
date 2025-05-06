<?php
/**
 * Fix Student Theme Script
 * 
 * This script ensures the theme column exists in the students table and sets default values.
 */

// Include database connection
require_once 'config/db_connect.php';

// Check if theme column exists in students table
$checkColumnSql = "SHOW COLUMNS FROM students LIKE 'theme'";
$columnExists = executeQuery($checkColumnSql);

// If theme column doesn't exist, add it
if (empty($columnExists)) {
    echo "<p>Theme column does not exist. Adding it now...</p>";
    $addColumnSql = "ALTER TABLE students ADD COLUMN theme VARCHAR(10) DEFAULT 'dark' AFTER email";
    $result = executeQuery($addColumnSql);
    echo "<p>Result: " . ($result ? 'Success' : 'Failed') . "</p>";
} else {
    echo "<p>Theme column already exists in students table.</p>";
}

// Update all students to have a default theme if not set
$updateSql = "UPDATE students SET theme = 'dark' WHERE theme IS NULL OR theme = ''";
$updateResult = executeQuery($updateSql);
$rowsAffected = $updateResult ? "Success" : "Failed";
echo "<p>Updated students with default theme. Result: $rowsAffected</p>";

// Count students with each theme
$countSql = "SELECT theme, COUNT(*) as count FROM students GROUP BY theme";
$countResult = executeQuery($countSql);

echo "<h3>Theme Distribution:</h3>";
echo "<ul>";
foreach ($countResult as $row) {
    echo "<li>" . htmlspecialchars($row['theme']) . ": " . $row['count'] . " students</li>";
}
echo "</ul>";

// Add a link to update your own theme
echo "<h3>Update Your Theme:</h3>";
echo "<p>Click one of the links below to update your theme:</p>";
echo "<ul>";
echo "<li><a href='update_student_theme.php?theme=dark'>Set Dark Theme</a></li>";
echo "<li><a href='update_student_theme.php?theme=light'>Set Light Theme</a></li>";
echo "</ul>";

echo "<p><a href='dashboard.php'>Return to Dashboard</a></p>";
?>
