<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear all session data
$_SESSION = array();

// If a session cookie is used, clear it too
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Log the logout action
error_log("User logged out successfully at " . date('Y-m-d H:i:s'));

// Redirect to homepage
header("Location: /capstone/homepage.php");
exit();
?>
