<?php
/**
 * Student Theme Helper for CodaQuest
 * 
 * This file handles theme detection and application for student users
 */

// Add theme detection code to be available to all student pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as student
if (isset($_SESSION['student_id'])) {
    // Include database connection if not already included
    if (!function_exists('executeQuery')) {
        require_once dirname(__FILE__) . '/../config/db_connect.php';
    }
    
    $studentId = $_SESSION['student_id'];
    
    // First, check if theme column exists in students table
    $checkColumnSql = "SHOW COLUMNS FROM students LIKE 'theme'";
    $columnExists = executeQuery($checkColumnSql);
    
    // If theme column doesn't exist, add it
    if (empty($columnExists)) {
        error_log("Theme column does not exist in students table. Adding it now...");
        $addColumnSql = "ALTER TABLE students ADD COLUMN theme VARCHAR(10) DEFAULT 'dark' AFTER email";
        $result = executeQuery($addColumnSql);
        error_log("Added theme column to students table. Result: " . ($result ? 'Success' : 'Failed'));
    }
    
    // Now that we've ensured the column exists, get the theme from database
    $themeSql = "SELECT theme FROM students WHERE student_id = ?";
    $themeResult = executeQuery($themeSql, [$studentId]);
    
    if ($themeResult && isset($themeResult[0]['theme']) && !empty($themeResult[0]['theme'])) {
        // Valid theme found in database
        $dbTheme = $themeResult[0]['theme'];
        error_log("Retrieved theme from database for student ID: {$studentId}, Theme: {$dbTheme}");
        
        // Ensure it's a valid theme value
        if ($dbTheme === 'light' || $dbTheme === 'dark') {
            $currentTheme = $dbTheme;
            error_log("Using valid theme from database: {$currentTheme}");
        } else {
            $currentTheme = 'dark'; // Default if invalid value
            error_log("Invalid theme value found in database: {$dbTheme}, defaulting to dark");
            
            // Fix invalid theme in database
            $updateThemeSql = "UPDATE students SET theme = 'dark' WHERE student_id = ?";
            $result = executeQuery($updateThemeSql, [$studentId]);
            error_log("Fixed invalid theme value for student ID: {$studentId}, Result: " . ($result ? 'Success' : 'Failed'));
        }
    } else {
        // No theme in database, set default
        $currentTheme = 'dark';
        error_log("No theme found in database for student ID: {$studentId}, setting default: dark");
        
        // Update the database with the default theme
        $updateThemeSql = "UPDATE students SET theme = 'dark' WHERE student_id = ?";
        $result = executeQuery($updateThemeSql, [$studentId]);
        error_log("Set default theme for student ID: {$studentId}, Result: " . ($result ? 'Success' : 'Failed'));
    }
    
    // Update session with current theme
    $_SESSION['student_theme'] = $currentTheme;
    error_log("Updated session with theme: {$currentTheme} for student ID: {$studentId}");
    
} else {
    // Not logged in as student, use default theme
    $currentTheme = 'dark';
}
?>
<!-- Set theme immediately -->
<script>
    // Apply the theme from the data-theme attribute on page load
    document.documentElement.setAttribute('data-theme', '<?php echo $currentTheme; ?>');
</script>
