<?php
// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    // Redirect to dashboard if already logged in
    header("Location: dashboard.php");
    exit();
}

// Include database connection and Google auth configuration
require_once 'config/db_connect.php';
require_once 'config/google_auth.php';

// Initialize variables for form data and errors
$username = $email = "";
$errors = [];

// Check for error parameters from Google auth
if (isset($_GET['error']) && $_GET['error'] == 'account_creation_failed') {
    $errors["general"] = "Failed to create account with Google. Please try again or use manual registration.";
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty($_POST["username"])) {
        $errors["username"] = "Username is required";
    } else {
        $username = trim($_POST["username"]);
        
        // Check if username already exists in students table
        $sql = "SELECT student_id FROM students WHERE username = ?";
        $result = executeQuery($sql, [$username]);
        
        if ($result && count($result) > 0) {
            $errors["username"] = "Username already exists";
        } else {
            // Also check admins table
            $sql = "SELECT admin_id FROM admins WHERE username = ?";
            $result = executeQuery($sql, [$username]);
            
            if ($result && count($result) > 0) {
                $errors["username"] = "Username already exists";
            }
        }
    }
    
    // Validate email
    if (empty($_POST["email"])) {
        $errors["email"] = "Email is required";
    } else {
        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format";
        } else {
            // Check if email already exists in students table
            $sql = "SELECT student_id FROM students WHERE email = ?";
            $result = executeQuery($sql, [$email]);
            
            if ($result && count($result) > 0) {
                $errors["email"] = "Email already exists";
            } else {
                // Also check admins table
                $sql = "SELECT admin_id FROM admins WHERE email = ?";
                $result = executeQuery($sql, [$email]);
                
                if ($result && count($result) > 0) {
                    $errors["email"] = "Email already exists";
                }
            }
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors["password"] = "Password is required";
    } else if (strlen($_POST["password"]) < 6) {
        $errors["password"] = "Password must be at least 6 characters";
    }
    
    // Validate password confirmation
    if (empty($_POST["confirmPassword"])) {
        $errors["confirmPassword"] = "Please confirm your password";
    } else if ($_POST["password"] !== $_POST["confirmPassword"]) {
        $errors["confirmPassword"] = "Passwords do not match";
    }
    
    // If no errors, create user account
    if (empty($errors)) {
        $password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
        
        // Insert new student
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password_hash,
            'full_name' => $username, // Using username as default full name
            'total_points' => 0,
            'current_level' => 1
        ];
        
        $userId = insertData('students', $userData);
        
        if ($userId) {
            // Set session variables
            $_SESSION["student_id"] = $userId;
            $_SESSION["username"] = $username;
            $_SESSION["role"] = "student"; // Always student for signup
            
            // Create initial achievements for the student
            $achievementData = [
                'student_id' => $userId,
                'achievement_id' => 1,
                'earned_at' => date('Y-m-d H:i:s')
            ];
            
            insertData('user_achievements', $achievementData);
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            $errors["general"] = "Error creating account. Please try again.";
        }
    }
}

// Set page title
$pageTitle = "CodaQuest - Sign Up";
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
        
        .signup-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 40px 20px;
        }
        
        .signup-card {
            width: 100%;
            max-width: 600px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 40px;
        }
        
        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .signup-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .signup-header p {
            color: #777;
            font-size: 16px;
        }
        
        .signup-form {
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
            color: #c62828;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .signup-btn {
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
        
        .signup-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .form-links {
            display: flex;
            justify-content: center;
            font-size: 14px;
            margin-top: 20px;
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
        
        .general-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
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
        
        .google-signup-btn {
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
        
        .google-signup-btn:hover {
            background-color: #f1f1f1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .signup-card {
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
        <div class="signup-container">
            <div class="signup-card">
                <div class="signup-header">
                    <h2 class="form-title"><i class="material-icons">person_add</i> Create Your Account</h2>
                    <p>Join CodaQuest and start your Python learning journey</p>
                </div>
                
                <?php if (isset($errors["general"])): ?>
                    <div class="general-error">
                        <?php echo htmlspecialchars($errors["general"]); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="signup.php" class="signup-form">
                    <div class="form-group">
                        <label for="username"><i class="material-icons">person</i> Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <?php if (isset($errors["username"])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors["username"]); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="material-icons">email</i> Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors["email"])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors["email"]); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="material-icons">lock</i> Password</label>
                        <input type="password" id="password" name="password" required>
                        <?php if (isset($errors["password"])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors["password"]); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword"><i class="material-icons">lock_outline</i> Confirm Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                        <?php if (isset($errors["confirmPassword"])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors["confirmPassword"]); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="signup-btn">Create Account</button>
                    </div>
                    
                    <div class="form-links">
                        <span>Already have an account? <a href="login.php">Log In</a></span>
                    </div>
                    
                    <?php 
                    $googleLoginUrl = getGoogleLoginUrl();
                    if (!empty($googleLoginUrl)): 
                    ?>
                    <div class="social-login">
                        <div class="divider">
                            <span>OR</span>
                        </div>
                        <a href="<?php echo $googleLoginUrl; ?>" class="google-signup-btn">
                            <img src="assets/google-icon.svg" alt="Google" width="20" height="20">
                            Sign up with Google
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script src="matrix-bg.js"></script>
</body>
</html>
