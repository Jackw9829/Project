<?php
// Include database connection
require_once 'config/db_connect.php';

// Admin user details
$username = 'admin';
$email = 'admin@codaquest.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);

// Update admin password
$sql = "UPDATE admins SET password = ? WHERE username = ? OR email = ?";
$result = executeQuery($sql, [$password, $username, $email]);

if ($result !== false) {
    echo "Admin password has been reset to 'admin123'";
} else {
    echo "Failed to reset admin password";
}

// Check if admin exists
$checkSql = "SELECT * FROM admins WHERE username = ? OR email = ?";
$adminResult = executeQuery($checkSql, [$username, $email]);

if ($adminResult && count($adminResult) > 0) {
    echo "<br>Admin user exists with ID: " . $adminResult[0]['admin_id'];
    echo "<br>Username: " . $adminResult[0]['username'];
    echo "<br>Email: " . $adminResult[0]['email'];
} else {
    echo "<br>Admin user does not exist!";
    
    // Create admin user if it doesn't exist
    $createSql = "INSERT INTO admins (
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
    
    $resetCode = '12345678';
    $fullName = 'CodaQuest Admin';
    
    $createResult = executeQuery($createSql, [
        $username, 
        $email, 
        $password, 
        $resetCode, 
        $fullName
    ]);
    
    if ($createResult !== false) {
        echo "<br>Created new admin user!";
    } else {
        echo "<br>Failed to create admin user";
    }
}
?>
