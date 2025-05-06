<?php
// Start session
session_start();

// Include database connection and Google auth configuration
require_once 'config/db_connect.php';
require_once 'config/google_auth.php';

// Initialize error message
$error_message = '';

// Verify state parameter to prevent CSRF attacks
if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['google_auth_state']) {
    header('Location: login.php?error=invalid_state');
    exit();
}

// Check for authorization code
if (isset($_GET['code'])) {
    // Exchange authorization code for access token
    $token_data = getGoogleAccessToken($_GET['code']);
    
    if (!$token_data || isset($token_data['error'])) {
        header('Location: login.php?error=token_exchange_failed');
        exit();
    }
    
    // Get user profile with access token
    $user_profile = getGoogleUserProfile($token_data['access_token']);
    
    if (!$user_profile) {
        header('Location: login.php?error=profile_fetch_failed');
        exit();
    }
    
    // Extract user data from profile
    $google_id = $user_profile['sub']; // Google's unique user ID
    $email = $user_profile['email'];
    $name = $user_profile['name'];
    $picture = $user_profile['picture'] ?? '';
    
    // Step 1: Check if a student exists with this Google ID
    $sql = "SELECT student_id, username FROM students WHERE google_id = ?";
    $result = executeQuery($sql, [$google_id]);
    
    if ($result && count($result) > 0) {
        // Student exists - log them in
        $user = $result[0];
        
        // Set session variables
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'student';
        
        // Log activity
        $activity_type = "login";
        $activity_details = "Student logged in with Google";
        $student_id = $user['student_id'];
        $sql = "INSERT INTO activity_log (student_id, activity_type, details) VALUES (?, ?, ?)";
        executeQuery($sql, [$student_id, $activity_type, $activity_details]);
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    }
    
    // Step 2: Check if an admin exists with this Google ID
    $sql = "SELECT admin_id, username FROM admins WHERE google_id = ?";
    $result = executeQuery($sql, [$google_id]);
    
    if ($result && count($result) > 0) {
        // Admin exists - log them in
        $user = $result[0];
        
        // Set session variables
        $_SESSION['admin_id'] = $user['admin_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'admin';
        
        // Admins don't have activity logs
        
        // Redirect to admin dashboard
        header("Location: admin/dashboard.php");
        exit();
    }
    
    // Step 3: Check if a student exists with this email but no Google ID
    $sql = "SELECT student_id FROM students WHERE email = ?";
    $result = executeQuery($sql, [$email]);
    
    if ($result && count($result) > 0) {
        // Email exists in students but not linked to Google - update the student record
        $user = $result[0];
        $student_id = $user['student_id'];
        
        // Update student with Google ID
        $sql = "UPDATE students SET google_id = ?, auth_provider = 'google' WHERE student_id = ?";
        executeQuery($sql, [$google_id, $student_id]);
        
        // Get student details
        $sql = "SELECT username FROM students WHERE student_id = ?";
        $result = executeQuery($sql, [$student_id]);
        $user = $result[0];
        
        // Set session variables
        $_SESSION['student_id'] = $student_id;
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'student';
        
        // Log activity
        $activity_type = "login";
        $activity_details = "Student linked Google account and logged in";
        $sql = "INSERT INTO activity_log (student_id, activity_type, details) VALUES (?, ?, ?)";
        executeQuery($sql, [$student_id, $activity_type, $activity_details]);
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    }
    
    // Step 4: Check if an admin exists with this email but no Google ID
    $sql = "SELECT admin_id FROM admins WHERE email = ?";
    $result = executeQuery($sql, [$email]);
    
    if ($result && count($result) > 0) {
        // Email exists in admins but not linked to Google - update the admin record
        $user = $result[0];
        $admin_id = $user['admin_id'];
        
        // Update admin with Google ID
        $sql = "UPDATE admins SET google_id = ?, auth_provider = 'google' WHERE admin_id = ?";
        executeQuery($sql, [$google_id, $admin_id]);
        
        // Get admin details
        $sql = "SELECT username FROM admins WHERE admin_id = ?";
        $result = executeQuery($sql, [$admin_id]);
        $user = $result[0];
        
        // Set session variables
        $_SESSION['admin_id'] = $admin_id;
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = 'admin';
        
        // Redirect to admin dashboard
        header("Location: admin/dashboard.php");
        exit();
    }
    
    // Step 5: Create a new student account
    // Generate username from email
    $username_base = explode('@', $email)[0];
    $username = $username_base;
    $counter = 1;
    
    // Check if username exists in students table and generate a unique one
    while (true) {
        $sql = "SELECT student_id FROM students WHERE username = ?";
        $result = executeQuery($sql, [$username]);
        
        if (!$result || count($result) == 0) {
            // Also check admins table
            $sql = "SELECT admin_id FROM admins WHERE username = ?";
            $result = executeQuery($sql, [$username]);
            
            if (!$result || count($result) == 0) {
                break; // Username is unique in both tables
            }
        }
        
        $username = $username_base . $counter;
        $counter++;
    }
    
    // Insert new student
    $studentData = [
        'username' => $username,
        'email' => $email,
        'password' => '', // No password for Google users
        'full_name' => $name,
        'google_id' => $google_id,
        'auth_provider' => 'google',
        'total_points' => 0,
        'current_level' => 1
    ];
    
    $studentId = insertData('students', $studentData);
    
    if ($studentId) {
        // Set session variables
        $_SESSION["user_id"] = $studentId;
        $_SESSION["username"] = $username;
        $_SESSION["role"] = 'student';
        
        // Create initial achievements for the student
        $achievementData = [
            'student_id' => $studentId,
            'achievement_id' => 1,
            'earned_at' => date('Y-m-d H:i:s')
        ];
        
        insertData('user_achievements', $achievementData);
        
        // Log activity
        $activity_type = "registration";
        $activity_details = "Student registered with Google";
        $sql = "INSERT INTO activity_log (student_id, activity_type, details) VALUES (?, ?, ?)";
        executeQuery($sql, [$studentId, $activity_type, $activity_details]);
        
        // Redirect to dashboard
        header("Location: dashboard.php");
        exit();
    } else {
        header('Location: signup.php?error=account_creation_failed');
        exit();
    }
} else {
    // No authorization code provided
    header('Location: login.php?error=no_code');
    exit();
}
?>
