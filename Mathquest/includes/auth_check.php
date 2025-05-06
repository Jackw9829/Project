<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Function to check if user has required role
function checkUserRole($required_role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: login.php');
        exit();
    }
}
?>
