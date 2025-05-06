<?php
// Include database connection
require_once 'config/db_connect.php';

// Admin user details
$username = 'admin';
$email = 'admin@codaquest.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$fullName = 'CodaQuest Admin';
$resetCode = '12345678';

// Check if admin already exists
$checkSql = "SELECT admin_id FROM admins WHERE username = ? OR email = ?";
$result = executeQuery($checkSql, [$username, $email]);

if ($result && count($result) > 0) {
    echo "Admin user already exists!";
} else {
    // Create admin user
    $sql = "INSERT INTO admins (
        username, 
        email, 
        password, 
        reset_code, 
        full_name, 
        date_registered, 
        last_login, 
        is_active, 
        theme, 
        auth_provider
    ) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1, 'default', 'local')";
    
    $insertResult = executeQuery($sql, [
        $username, 
        $email, 
        $password, 
        $resetCode, 
        $fullName
    ]);
    
    if ($insertResult !== false) {
        echo "Admin user created successfully!";
        echo "\nUsername: admin";
        echo "\nPassword: admin123";
    } else {
        echo "Failed to create admin user. Please check database connection.";
    }
}

// Display a link to return to login page
echo "\n\n<a href='login.php'>Go to Login Page</a>";
?>
