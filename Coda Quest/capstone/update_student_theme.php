<?php
/**
 * Update Student Theme Script
 * 
 * This script updates the theme for the logged-in student user.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    // Not logged in as student, redirect to login
    header("Location: login.php?msg=Please log in as a student to change your theme");
    exit;
}

// Get student ID from session
$studentId = $_SESSION['student_id'];

// Check if theme is specified
if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'])) {
    $newTheme = $_GET['theme'];
    
    // Update theme in database
    $updateThemeSql = "UPDATE students SET theme = ? WHERE student_id = ?";
    $result = executeQuery($updateThemeSql, [$newTheme, $studentId]);
    
    // Update session
    $_SESSION['student_theme'] = $newTheme;
    
    // Add debug log
    error_log("Student theme updated: Student ID: $studentId, New Theme: $newTheme, Result: " . ($result ? 'Success' : 'Failed'));
    
    // Redirect to dashboard with success message
    header("Location: dashboard.php?theme_updated=1&t=" . time());
    exit;
} else {
    // Invalid theme parameter
    header("Location: dashboard.php?error=Invalid theme selection");
    exit;
}
?>
