<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Debug function
function debug_log($message, $data = null) {
    $log = "[" . date('Y-m-d H:i:s') . "] RESET_PASSWORD: $message";
    if ($data !== null) {
        $log .= " - " . print_r($data, true);
    }
    error_log($log);
}

// Helper function to test token generation and database insertion
function test_token_generation() {
    if (!isset($_GET['test_token']) || $_GET['test_token'] !== '1') {
        return false;
    }
    
    debug_log("Running token generation test");
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }
        
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        $tableExists = $tableCheck->rowCount() > 0;
        echo "<p>Table exists: " . ($tableExists ? 'Yes' : 'No') . "</p>";
        
        if (!$tableExists) {
            // Create the table
            $createSql = "
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                reset_id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                student_id INT DEFAULT NULL,
                admin_id INT DEFAULT NULL,
                INDEX (email),
                INDEX (token),
                INDEX (student_id),
                INDEX (admin_id)
            )";
            $pdo->exec($createSql);
            echo "<p>Created table</p>";
        }
        
        // Generate a test token
        $testEmail = 'test@example.com';
        $testToken = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        // Insert test token
        $sql = "INSERT INTO password_reset_tokens (email, token, expires_at, student_id, admin_id) 
                VALUES (?, ?, ?, NULL, NULL)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$testEmail, $testToken, $expiresAt]);
        
        echo "<p>Token insertion result: " . ($result ? 'Success' : 'Failed') . "</p>";
        echo "<p>Token: " . $testToken . "</p>";
        echo "<p>Email: " . $testEmail . "</p>";
        echo "<p>Expires at: " . $expiresAt . "</p>";
        
        // Verify insertion
        $verifyStmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? ORDER BY reset_id DESC LIMIT 1");
        $verifyStmt->execute([$testEmail]);
        $record = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>Verification result: " . ($record ? 'Found' : 'Not found') . "</p>";
        if ($record) {
            echo "<pre>" . print_r($record, true) . "</pre>";
        }
        
        // Check all tokens
        $allTokens = $pdo->query("SELECT * FROM password_reset_tokens")->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>Total tokens in table: " . count($allTokens) . "</p>";
        if ($allTokens) {
            echo "<table border='1'>";
            echo "<tr><th>Reset ID</th><th>Email</th><th>Token</th><th>Created</th><th>Expires</th><th>Student ID</th><th>Admin ID</th></tr>";
            foreach ($allTokens as $token) {
                echo "<tr>";
                echo "<td>" . $token['reset_id'] . "</td>";
                echo "<td>" . $token['email'] . "</td>";
                echo "<td>" . $token['token'] . "</td>";
                echo "<td>" . $token['created_at'] . "</td>";
                echo "<td>" . $token['expires_at'] . "</td>";
                echo "<td>" . $token['student_id'] . "</td>";
                echo "<td>" . $token['admin_id'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        exit("Token test completed");
    } catch (Exception $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>";
        exit("Token test failed");
    }
}

// Call the test function if needed
if (isset($_GET['test_token'])) {
    test_token_generation();
}

// Initialize variables
$error_message = "";
$success_message = "";
$email = "";
$passcode = "";
$step = "request"; // Default step: request, verify, reset

// Handle verification step (step 2)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_code'])) {
    $email = trim($_POST['email']);
    $passcode = trim($_POST['passcode']);
    debug_log("Verifying passcode for email", ["email" => $email, "passcode_length" => strlen($passcode)]);
    
    // Check which verification method to use
    $sql = "SELECT column_name FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = 'students' 
            AND column_name = 'reset_code'";
    $reset_code_exists = (count(executeQuery($sql, [])) > 0);
    debug_log("Reset code column exists", $reset_code_exists);
    
    if ($reset_code_exists) {
        // Check if passcode matches reset_code in students or admins table
        $sql = "SELECT 'student' as user_type, student_id as id, username FROM students WHERE email = ? AND reset_code = ?
                UNION
                SELECT 'admin' as user_type, admin_id as id, username FROM admins WHERE email = ? AND reset_code = ?";
        $result = executeQuery($sql, [$email, $passcode, $email, $passcode]);
        debug_log("Reset code validation result", $result);
    } else {
        // Check if passcode matches token in password_reset_tokens table and is not expired
        $sql = "SELECT t.*, 
                CASE 
                    WHEN t.student_id IS NOT NULL THEN 'student' 
                    WHEN t.admin_id IS NOT NULL THEN 'admin' 
                    ELSE NULL 
                END as user_type,
                CASE 
                    WHEN t.student_id IS NOT NULL THEN t.student_id 
                    WHEN t.admin_id IS NOT NULL THEN t.admin_id 
                    ELSE NULL 
                END as id
                FROM password_reset_tokens t 
                WHERE t.email = ? AND t.token = ? AND t.expires_at > NOW()";
        
        debug_log("Token validation SQL", ["sql" => $sql, "params" => [$email, $passcode]]);
        
        try {
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception("Database connection failed");
            }
            
            // First check if token exists but might be expired
            $checkStmt = $pdo->prepare("SELECT token, expires_at FROM password_reset_tokens WHERE email = ? AND token = ?");
            $checkStmt->execute([$email, $passcode]);
            $tokenCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tokenCheck) {
                debug_log("Found token record", $tokenCheck);
                // Check if it's expired
                $now = new DateTime();
                $expires = new DateTime($tokenCheck['expires_at']);
                $isExpired = $now > $expires;
                
                if ($isExpired) {
                    debug_log("Token is expired", ["expires_at" => $tokenCheck['expires_at'], "now" => $now->format('Y-m-d H:i:s')]);
                    $error_message = "Your reset code has expired. Please request a new one.";
                    $step = "request";
                    $result = [];
                } else {
                    // Token is valid and not expired, proceed with the original query
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$email, $passcode]);
                    $tokenResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    debug_log("Valid token found", $tokenResult);
                    
                    if (!empty($tokenResult)) {
                        $result = [];
                        // Get the user info to match the format from the union query
                        $user_type = $tokenResult[0]['user_type'];
                        $user_id = $tokenResult[0]['id'];
                        
                        if ($user_type === 'student') {
                            $userSql = "SELECT username FROM students WHERE student_id = ?";
                        } else {
                            $userSql = "SELECT username FROM admins WHERE admin_id = ?";
                        }
                        
                        $stmt = $pdo->prepare($userSql);
                        $stmt->execute([$user_id]);
                        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                        debug_log("User info for token", $userInfo);
                        
                        if ($userInfo) {
                            $result[] = [
                                'user_type' => $user_type,
                                'id' => $user_id,
                                'username' => $userInfo['username']
                            ];
                        }
                    } else {
                        // This shouldn't happen given our previous checks
                        debug_log("Token validation inconsistency - found token but failed validation query");
                        $error_message = "Error validating reset code. Please try again.";
                        $result = [];
                    }
                }
            } else {
                debug_log("No token found for this email and code", ["email" => $email]);
                $error_message = "Invalid reset code. Please check your code and try again.";
                $result = [];
            }
        } catch (Exception $e) {
            $error_message = "Error verifying reset code: " . $e->getMessage();
            debug_log("Token validation error", $e->getMessage());
            $result = [];
        }
    }
    
    if (!empty($result)) {
        $user = $result[0];
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_user_type'] = $user['user_type'];
        $_SESSION['reset_user_id'] = $user['id'];
        
        debug_log("Reset verification successful, session data set", [
            'reset_email' => $_SESSION['reset_email'],
            'reset_user_type' => $_SESSION['reset_user_type'],
            'reset_user_id' => $_SESSION['reset_user_id']
        ]);
        
        $success_message = "Code verified! Please enter your new password.";
        $step = "reset";
    } else {
        if (!isset($error_message)) {
            $error_message = "Invalid or expired reset code. Please try again.";
        }
        $step = "verify";
    }
}

// Handle password reset request (step 1)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    debug_log("Password reset requested for email", $email);
    
    // Check if reset_code column exists
    $sql = "SELECT column_name FROM information_schema.columns 
            WHERE table_schema = DATABASE() 
            AND table_name = 'students' 
            AND column_name = 'reset_code'";
    $reset_code_exists = (count(executeQuery($sql, [])) > 0);
    debug_log("Reset code column exists", $reset_code_exists);
    
    // Check if email exists in students or admins table
    $sql = "SELECT 'student' as user_type, student_id as id, username" . ($reset_code_exists ? ", reset_code" : "") . " FROM students WHERE email = ?
            UNION
            SELECT 'admin' as user_type, admin_id as id, username" . ($reset_code_exists ? ", reset_code" : "") . " FROM admins WHERE email = ?";
    $result = executeQuery($sql, [$email, $email]);
    debug_log("User search result", $result);
    
    if ($result && count($result) > 0) {
        $user = $result[0];
        
        // Check if user registered with Google
        if (!empty($user['google_id'])) {
            $error_message = "This account is linked to Google. Please reset your password through Google's account services.";
            $step = "request";
        } else {
            if ($reset_code_exists) {
                // Use permanent reset code approach
                $success_message = "Please enter your 8-digit reset code. You can find this code in your user settings.";
                $step = "verify";
            } else {
                // Check if the password_reset_tokens table exists
                $table_check_sql = "SHOW TABLES LIKE 'password_reset_tokens'";
                $table_exists = (count(executeQuery($table_check_sql, [])) > 0);
                debug_log("Password reset tokens table exists", $table_exists);
                
                if (!$table_exists) {
                    // Create the table if it doesn't exist
                    $create_table_sql = "
                    CREATE TABLE IF NOT EXISTS password_reset_tokens (
                        reset_id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) NOT NULL,
                        token VARCHAR(255) NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        expires_at DATETIME NOT NULL,
                        student_id INT DEFAULT NULL,
                        admin_id INT DEFAULT NULL,
                        INDEX (email),
                        INDEX (token),
                        INDEX (student_id),
                        INDEX (admin_id)
                    )";
                    executeQuery($create_table_sql, []);
                    debug_log("Created password_reset_tokens table");
                }
                
                // Generate a temporary token and store it
                $passcode = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                debug_log("Generated passcode and expiration", ["passcode" => $passcode, "expires_at" => $expires_at]);
                
                // First, delete any existing tokens for this email
                $sql = "DELETE FROM password_reset_tokens WHERE email = ?";
                executeQuery($sql, [$email]);
                debug_log("Deleted existing tokens for email", $email);
                
                // Store passcode in database with student_id or admin_id
                $student_id = ($user['user_type'] === 'student') ? $user['id'] : null;
                $admin_id = ($user['user_type'] === 'admin') ? $user['id'] : null;
                debug_log("User information for token", ["user_type" => $user['user_type'], "student_id" => $student_id, "admin_id" => $admin_id]);
                
                // Store passcode in database
                $sql = "INSERT INTO password_reset_tokens (email, token, expires_at, student_id, admin_id) VALUES (?, ?, ?, ?, ?)";
                debug_log("Inserting token with SQL", ["sql" => $sql, "params" => [$email, $passcode, $expires_at, $student_id, $admin_id]]);
                
                try {
                    $pdo = getDbConnection();
                    if (!$pdo) {
                        throw new Exception("Database connection failed");
                    }
                    
                    debug_log("PDO connection established", ["pdo_class" => get_class($pdo)]);
                    
                    // Check database status
                    $version = $pdo->query('SELECT VERSION() as version')->fetch(PDO::FETCH_ASSOC);
                    debug_log("Database version", $version);
                    
                    // Check if we can see tables at all
                    $tables = [];
                    $result = $pdo->query('SHOW TABLES');
                    if ($result) {
                        while ($row = $result->fetch(PDO::FETCH_NUM)) {
                            $tables[] = $row[0];
                        }
                    }
                    debug_log("Tables in database", ["count" => count($tables), "tables" => $tables]);
                    
                    // Ensure created_at is properly set in a MySQL-compatible format
                    $current_time = date('Y-m-d H:i:s');
                    
                    // Try to create the table if it doesn't exist
                    $createTableSql = "
                        CREATE TABLE IF NOT EXISTS password_reset_tokens (
                            reset_id INT AUTO_INCREMENT PRIMARY KEY,
                            email VARCHAR(255) NOT NULL,
                            token VARCHAR(255) NOT NULL,
                            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                            expires_at DATETIME NOT NULL,
                            student_id INT DEFAULT NULL,
                            admin_id INT DEFAULT NULL,
                            INDEX (email),
                            INDEX (token),
                            INDEX (student_id),
                            INDEX (admin_id)
                        )
                    ";
                    
                    $createResult = $pdo->exec($createTableSql);
                    debug_log("Table creation result", ["result" => $createResult]);
                    
                    // Verify the table exists and has the correct structure
                    try {
                        $tableInfo = $pdo->query("DESCRIBE password_reset_tokens")->fetchAll(PDO::FETCH_ASSOC);
                        debug_log("Table structure", $tableInfo);
                    } catch (Exception $e) {
                        debug_log("Error checking table structure", $e->getMessage());
                    }
                    
                    // Clear any existing tokens for this email
                    $clearStmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
                    $clearResult = $clearStmt->execute([$email]);
                    debug_log("Cleared existing tokens", ["email" => $email, "success" => $clearResult, "count" => $clearStmt->rowCount()]);
                    
                    // New SQL with explicit column list including created_at
                    $sql = "INSERT INTO password_reset_tokens (email, token, created_at, expires_at, student_id, admin_id) 
                           VALUES (?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $pdo->prepare($sql);
                    if (!$stmt) {
                        $error = $pdo->errorInfo();
                        debug_log("Prepare statement failed", $error);
                        throw new Exception("Failed to prepare statement: " . $error[2]);
                    }
                    
                    $result = $stmt->execute([$email, $passcode, $current_time, $expires_at, $student_id, $admin_id]);
                    
                    debug_log("Token insertion result", [
                        "success" => $result,
                        "error_code" => $stmt->errorCode(),
                        "error_info" => $stmt->errorInfo()
                    ]);
                    
                    $inserted_id = $pdo->lastInsertId();
                    debug_log("Last insert ID", $inserted_id);
                    
                    // Verify the insertion by selecting - use try/catch to prevent fatal errors
                    try {
                        $selectStmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE email = ? ORDER BY reset_id DESC LIMIT 1");
                        $selectResult = $selectStmt->execute([$email]);
                        $token_record = $selectStmt->fetch(PDO::FETCH_ASSOC);
                        
                        debug_log("Verification query result", [
                            "query_success" => $selectResult,
                            "record" => $token_record
                        ]);
                    } catch (Exception $e) {
                        debug_log("Error verifying token insertion", $e->getMessage());
                        $token_record = null;
                    }
                    
                    if ($inserted_id && $token_record) {
                        // For development/testing environment - show the passcode
                        $success_message = "Your temporary password reset code (valid for 30 minutes): {$passcode}";
                        $step = "verify";
                    } else {
                        throw new Exception("Failed to insert or retrieve token record. Last insert ID: " . ($inserted_id ? $inserted_id : 'none'));
                    }
                } catch (Exception $e) {
                    $error_message = "Failed to generate reset code: " . $e->getMessage();
                    debug_log("Token insertion failed", $e->getMessage());
                    $step = "request";
                }
            }
        }
    } else {
        $error_message = "Email not found. Please check your email address and try again.";
        $step = "request";
    }
}

// Handle reset step (step 3)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    debug_log("Processing password reset", [
        "reset_email" => isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : 'not set',
        "reset_user_type" => isset($_SESSION['reset_user_type']) ? $_SESSION['reset_user_type'] : 'not set',
        "reset_user_id" => isset($_SESSION['reset_user_id']) ? $_SESSION['reset_user_id'] : 'not set'
    ]);
    
    // Validate passwords
    if ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
        $step = "reset";
    } else if (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
        $step = "reset";
    } else if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_type']) || !isset($_SESSION['reset_user_id'])) {
        $error_message = "Invalid session. Please restart the password reset process.";
        $step = "request";
    } else {
        $email = $_SESSION['reset_email'];
        $user_type = $_SESSION['reset_user_type'];
        $user_id = $_SESSION['reset_user_id'];
        
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception("Database connection failed");
            }
            
            $pdo->beginTransaction();
            
            // Update the password based on user type
            if ($user_type === 'student') {
                $sql = "UPDATE students SET password = ?, reset_code = NULL WHERE student_id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([$hashed_password, $user_id]);
            } else if ($user_type === 'admin') {
                $sql = "UPDATE admins SET password = ?, reset_code = NULL WHERE admin_id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([$hashed_password, $user_id]);
            } else {
                throw new Exception("Invalid user type");
            }
            
            if (!$success) {
                throw new Exception("Failed to update password");
            }
            
            // Delete any tokens for this email
            $sql = "DELETE FROM password_reset_tokens WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            
            $pdo->commit();
            
            // Clear session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_type']);
            unset($_SESSION['reset_user_id']);
            
            debug_log("Password reset successful", ["user_type" => $user_type, "user_id" => $user_id]);
            
            $success_message = "Password has been reset successfully! You can now login with your new password.";
            $step = "success";
        } catch (Exception $e) {
            if ($pdo) {
                $pdo->rollBack();
            }
            $error_message = "Error resetting password: " . $e->getMessage();
            debug_log("Password reset error", $error_message);
            $step = "reset";
        }
    }
}

// Set page title
$pageTitle = "CodaQuest - Reset Password";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <?php include_once 'common_styles.php'; ?>
    <style>
        body {
            padding: 0;
            margin: 0;
        }
        
        #matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.15; /* Slightly increased opacity for better visibility */
        }
        
        .reset-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 40px 20px;
            position: relative; /* Ensure proper stacking context */
            z-index: 1; /* Place above the matrix background */
        }
        
        .reset-card {
            width: 100%;
            max-width: 600px;
            background-color: rgba(var(--card-bg-rgb), 0.95); /* Add slight transparency */
            border-radius: var(--border-radius);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 8px 0 rgba(var(--primary-color-rgb), 0.3); /* Enhanced glow effect */
            overflow: hidden;
            padding: 40px;
            border: 4px solid var(--border-color);
            backdrop-filter: blur(4px); /* Add blur effect for modern browsers */
            -webkit-backdrop-filter: blur(4px);
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .reset-header h2 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .reset-header p {
            color: var(--text-color);
            font-size: 16px;
        }
        
        .reset-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input {
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 16px;
            background-color: var(--card-bg);
            color: var(--text-color);
            transition: var(--transition);
        }
        
        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.3);
        }
        
        .error-message {
            background-color: rgba(255, 0, 0, 0.1);
            color: #c62828;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
            border: 2px solid #c62828;
        }
        
        .success-message {
            background-color: rgba(0, 255, 0, 0.1);
            color: #2e7d32;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
            border: 2px solid #2e7d32;
        }
        
        .reset-btn {
            background-color: var(--primary-color);
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 16px;
        }
        
        .reset-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .form-links {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .form-links a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .password-requirements {
            font-size: 12px;
            margin-top: 5px;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .passcode-display {
            letter-spacing: 2px;
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            color: var(--primary-color);
            text-align: center;
        }
        
        .info-text {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
            border: 2px solid var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .reset-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="container">
        <div class="reset-container">
            <div class="reset-card">
                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="success-message">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === "request"): ?>
                    <div class="reset-header">
                        <h2><i class="material-icons">lock_reset</i> Reset Password</h2>
                        <p>Enter your email to continue</p>
                    </div>
                    
                    <div class="info-text">
                        <p>You'll need your 8-digit reset code to reset your password. This code can be found in your user settings.</p>
                    </div>
                    
                    <form method="post" action="reset_password.php" class="reset-form">
                        <div class="form-group">
                            <label for="email"><i class="material-icons">email</i> Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="request_reset" class="reset-btn">Continue</button>
                        </div>
                        
                        <div class="form-links">
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                
                <?php elseif ($step === "verify"): ?>
                    <div class="reset-header">
                        <h2><i class="material-icons">dialpad</i> Enter Reset Code</h2>
                        <p>Enter your 8-digit reset code</p>
                    </div>
                    
                    <div class="info-text">
                        <p>Your reset code can be found in your account settings. This is a permanent code that was generated when you registered.</p>
                    </div>
                    
                    <form method="post" action="reset_password.php" class="reset-form">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div class="form-group">
                            <label for="passcode"><i class="material-icons">password</i> Reset Code</label>
                            <input type="text" id="passcode" name="passcode" pattern="[0-9]{8}" maxlength="8" required placeholder="Enter 8-digit code">
                            <div class="password-requirements">Enter your 8-digit reset code from your user settings</div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="verify_code" class="reset-btn">Verify Code</button>
                        </div>
                        
                        <div class="form-links">
                            <a href="reset_password.php">Start Over</a>
                            <a href="login.php">Back to Login</a>
                        </div>
                    </form>
                
                <?php elseif ($step === "reset"): ?>
                    <div class="reset-header">
                        <h2><i class="material-icons">lock_reset</i> Set New Password</h2>
                        <p>Create a new password for your account</p>
                    </div>
                    
                    <form method="post" action="reset_password.php" class="reset-form">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        
                        <div class="form-group">
                            <label for="new_password"><i class="material-icons">lock</i> New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <div class="password-requirements">Password must be at least 8 characters long</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password"><i class="material-icons">lock</i> Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="reset_password" class="reset-btn">Reset Password</button>
                        </div>
                    </form>
                
                <?php elseif ($step === "success"): ?>
                    <div class="reset-header">
                        <h2><i class="material-icons">check_circle</i> Password Reset Successful</h2>
                        <p>Your password has been updated successfully</p>
                    </div>
                    
                    <div class="form-group" style="text-align: center;">
                        <a href="login.php" class="reset-btn">Go to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="matrix-bg.js"></script>
</body>
</html> 