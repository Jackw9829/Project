<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    // User is not logged in, redirect to homepage
    header("Location: /capstone/homepage.php");
    exit;
}
?>
