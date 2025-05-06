<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Include database connection and Google auth configuration
require_once 'config/db_connect.php';
require_once 'config/google_auth.php';

// Initialize variables
$error_message = "";
$username = "";

// Check for error parameters from Google auth
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_state':
            $error_message = "Invalid authentication state. Please try again.";
            break;
        case 'token_exchange_failed':
            $error_message = "Failed to authenticate with Google. Please try again.";
            break;
        case 'profile_fetch_failed':
            $error_message = "Failed to get profile from Google. Please try again.";
            break;
        case 'no_code':
            $error_message = "No authorization code received from Google. Please try again.";
            break;
        default:
            $error_message = "An error occurred during Google authentication. Please try again.";
    }
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // First check if it's a student login
    $sql = "SELECT student_id, username, password FROM students WHERE username = ?";
    $result = executeQuery($sql, [$username]);
    
    if ($result && count($result) > 0) {
        $user = $result[0];
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'student';
            
            // Log activity
            $activity_type = "login";
            $activity_details = "Student logged in to dashboard";
            $student_id = $user['student_id'];
            $sql = "INSERT INTO activity_log (student_id, activity_type, details) VALUES (?, ?, ?)";
            executeQuery($sql, [$student_id, $activity_type, $activity_details]);
        } else {
            $error_message = "Invalid username or password";
        }
    } else {
        // If not found in students table, check admins table
        $sql = "SELECT admin_id, username, password FROM admins WHERE username = ?";
        $result = executeQuery($sql, [$username]);
        
        if ($result && count($result) > 0) {
            $user = $result[0];
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['admin_id'] = $user['admin_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'admin';
                
                // No activity log for admins as the activity_log table is for students
            } else {
                $error_message = "Invalid username or password";
            }
        } else {
            $error_message = "Invalid username or password";
        }
    }
    
    // If login was successful (error message is still empty)
    if (empty($error_message) && (isset($_SESSION['student_id']) || isset($_SESSION['admin_id']))) {
            
        // Redirect to appropriate dashboard based on role
        if ($_SESSION['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }
}

// Set page title
$pageTitle = "CodaQuest - Login";
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
    <link rel="stylesheet" href="common.css">
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
            opacity: 0.1;
        }
        
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 40px 20px;
        }
        
        .login-card {
            width: 100%;
            max-width: 600px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .login-header p {
            color: #777;
            font-size: 16px;
        }
        
        .login-form {
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
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: var(--transition);
        }
        
        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(176, 222, 253, 0.3);
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .login-btn {
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
        
        .login-btn:hover {
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
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider span {
            padding: 0 10px;
            color: #777;
            font-size: 14px;
        }
        
        .social-login {
            margin-top: 20px;
        }
        
        .google-login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 20px;
            background-color: #fff;
            color: #444;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .google-login-btn:hover {
            background-color: #f1f1f1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .login-card {
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
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h2 class="form-title"><i class="material-icons">login</i> Login to CodaQuest</h2>
                    <p>Log in to continue your coding journey</p>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="login.php" class="login-form">
                    <div class="form-group">
                        <label for="username"><i class="material-icons">person</i> Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password"><i class="material-icons">lock</i> Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="login-btn">Log In</button>
                    </div>
                    
                    <div class="form-links">
                        <a href="reset_password.php" class="forgot-password">Forgot Password?</a>
                        <a href="signup.php" class="create-account">Create Account</a>
                    </div>
                </form>
                
                <?php 
                $googleLoginUrl = getGoogleLoginUrl();
                if (!empty($googleLoginUrl)): 
                ?>
                <div class="social-login">
                    <div class="divider">
                        <span>OR</span>
                    </div>
                    <a href="<?php echo $googleLoginUrl; ?>" class="google-login-btn">
                        <img src="assets/google-icon.svg" alt="Google" width="20" height="20">
                        Sign in with Google
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="matrix-bg.js"></script>
</body>
</html>
