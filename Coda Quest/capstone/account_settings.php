<?php
// Start session
session_start();

// Include student theme helper to ensure theme is applied correctly
require_once 'includes/student_theme_helper.php';

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get user information
$userId = $_SESSION['role'] === 'student' ? $_SESSION['student_id'] : $_SESSION['admin_id'];
$username = $_SESSION['username'];

// Initialize variables for form data and messages
$successMsg = "";
$errorMsg = "";
$profileUpdated = false;
$passwordUpdated = false;
$preferencesUpdated = false;

// Handle theme preference update
if (isset($_GET['set_theme']) && in_array($_GET['set_theme'], ['dark', 'light'])) {
    // Get theme from URL parameter
    $newTheme = $_GET['set_theme'];
    
    // Update in database based on user role
    if ($_SESSION['role'] === 'student') {
        $updateThemeSql = "UPDATE students SET theme = ? WHERE student_id = ?";
        executeQuery($updateThemeSql, [$newTheme, $userId]);
    } else {
        $updateThemeSql = "UPDATE admins SET theme = ? WHERE admin_id = ?";
        executeQuery($updateThemeSql, [$newTheme, $userId]);
    }
    
    // Update in session
    $_SESSION[($_SESSION['role'] === 'student' ? 'student' : 'admin') . '_theme'] = $newTheme;
    
    // Set success message
    $successMsg = "Theme updated successfully!";
    $preferencesUpdated = true;
    
    // Redirect to remove the parameter from URL
    header("Location: account_settings.php?theme_updated=1&t=" . time() . "#preferences");
    exit();
}

// Get user details from database
function getUserDetails($userId) {
    // Check if user is a student or admin based on role
    if ($_SESSION['role'] === 'student') {
        $sql = "SELECT * FROM students WHERE student_id = ?";
    } else {
        $sql = "SELECT * FROM admins WHERE admin_id = ?";
    }
    
    $result = executeQuery($sql, [$userId]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

// Get user details first
$userDetails = getUserDetails($userId);

// Generate reset code if one doesn't exist
if (empty($userDetails['reset_code'])) {
    $resetCode = generateResetCode();
    
    // Update the appropriate table based on user role
    if ($_SESSION['role'] === 'student') {
        $updateSql = "UPDATE students SET reset_code = ? WHERE student_id = ?";
    } else {
        $updateSql = "UPDATE admins SET reset_code = ? WHERE admin_id = ?";
    }
    
    executeQuery($updateSql, [$resetCode, $userId]);
    
    // Refresh user details
    $userDetails = getUserDetails($userId);
}

// Function to generate a random reset code
function generateResetCode() {
    // Generate an 8-digit numeric code
    return sprintf("%08d", mt_rand(10000000, 99999999));
}

// Regenerate reset code if requested
if (isset($_POST['regenerate_reset_code'])) {
    $newResetCode = generateResetCode();
    
    // Update the appropriate table based on user role
    if ($_SESSION['role'] === 'student') {
        $updateSql = "UPDATE students SET reset_code = ? WHERE student_id = ?";
    } else {
        $updateSql = "UPDATE admins SET reset_code = ? WHERE admin_id = ?";
    }
    
    $updateResult = executeQuery($updateSql, [$newResetCode, $userId]);
    
    if ($updateResult !== false) {
        $successMsg = "Reset code regenerated successfully!";
        // Refresh user details
        $userDetails = getUserDetails($userId);
    } else {
        $errorMsg = "Failed to regenerate reset code. Please try again.";
    }
}

// Update user profile information
if (isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $bio = trim($_POST['bio']);
    
    // Validate input
    if (empty($fullName) || empty($email)) {
        $errorMsg = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Please enter a valid email address.";
    } else {
        // Update user profile in database based on role
        if ($_SESSION['role'] === 'student') {
            $sql = "UPDATE students SET full_name = ?, email = ?, bio = ? WHERE student_id = ?";
        } else {
            $sql = "UPDATE admins SET full_name = ?, email = ?, bio = ? WHERE admin_id = ?";
        }
        $result = executeQuery($sql, [$fullName, $email, $bio, $userId]);
        
        if ($result !== false) {
            $successMsg = "Profile updated successfully!";
            $profileUpdated = true;
        } else {
            $errorMsg = "Failed to update profile. Please try again.";
        }
    }
}

// Update user password
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMsg = "All password fields are required.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMsg = "New passwords do not match.";
    } elseif (strlen($newPassword) < 8) {
        $errorMsg = "Password must be at least 8 characters long.";
    } else {
        // Verify current password based on user role
        if ($_SESSION['role'] === 'student') {
            $sql = "SELECT password FROM students WHERE student_id = ?";
        } else {
            $sql = "SELECT password FROM admins WHERE admin_id = ?";
        }
        $result = executeQuery($sql, [$userId]);
        
        if ($result && count($result) > 0) {
            $hashedPassword = $result[0]['password'];
            
            if (password_verify($currentPassword, $hashedPassword)) {
                // Update password based on user role
                $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                if ($_SESSION['role'] === 'student') {
                    $updateSql = "UPDATE students SET password = ? WHERE student_id = ?";
                } else {
                    $updateSql = "UPDATE admins SET password = ? WHERE admin_id = ?";
                }
                $updateResult = executeQuery($updateSql, [$newHashedPassword, $userId]);
                
                if ($updateResult !== false) {
                    $successMsg = "Password updated successfully!";
                    $passwordUpdated = true;
                } else {
                    $errorMsg = "Failed to update password. Please try again.";
                }
            } else {
                $errorMsg = "Current password is incorrect.";
            }
        } else {
            $errorMsg = "User verification failed. Please try again.";
        }
    }
}

// Get user details
$userDetails = getUserDetails($userId);


// Set page title
$pageTitle = "Account Settings - CodaQuest";

// Additional styles for account settings page
$additionalStyles = '
<style>
    /* Theme-specific styles for account settings */
    
    /* Matrix Background */
    #matrix-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.1;
    }
    
    /* Account Settings Styles */
    body {
        padding: 0;
        margin: 0;
    }
    
    .settings-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .page-header {
        background-color: var(--header-bg);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        text-align: center;
        border: 4px solid var(--border-color);
    }
    
    .page-title {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        text-transform: uppercase;
        text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
    }
    
    .page-description {
        color: var(--text-color);
        max-width: 800px;
        margin: 0 auto;
    }
    
    .settings-grid {
        display: grid;
        grid-template-columns: 1fr 3fr;
        gap: 30px;
    }
    
    .settings-nav {
        background-color: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 20px;
        border: 4px solid var(--border-color);
        box-shadow: var(--shadow);
    }
    
    .settings-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .settings-nav-item {
        margin-bottom: 10px;
    }
    
    .settings-nav-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: var(--text-color);
        text-decoration: none;
        border-radius: var(--border-radius);
        transition: var(--transition);
        font-size: 0.8rem;
        border-left: 4px solid transparent;
    }
    
    .settings-nav-link:hover,
    .settings-nav-link.active {
        background-color: rgba(178, 222, 253, 0.1);
        border-left: 4px solid var(--primary-color);
    }
    
    .settings-nav-link i {
        margin-right: 10px;
        font-size: 20px;
    }
    
    .settings-content {
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 30px;
        box-shadow: var(--shadow);
    }
    
    .settings-section {
        background-color: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 30px;
        border: 4px solid var(--border-color);
        box-shadow: var(--shadow);
        margin-bottom: 30px;
        display: none;
    }
    
    .settings-section.active {
        display: block;
    }
    
    .settings-section-title {
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-color);
        font-size: 0.9rem;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--text-color);
        font-family: "Press Start 2P", monospace;
        font-size: 0.8rem;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .checkbox-input {
        margin-right: 10px;
        width: 18px;
        height: 18px;
        accent-color: var(--primary-color);
    }
    
    .checkbox-label {
        font-size: 0.9rem;
        color: var(--text-color);
    }
    
    .btn-submit {
        background-color: var(--primary-color);
        color: var(--text-color);
        padding: 12px 25px;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        font-family: "Press Start 2P", monospace;
        font-size: 0.8rem;
        border: 2px solid transparent;
    }
    
    .btn-submit:hover {
        background-color: var(--accent-color);
        border-color: var(--border-color);
    }
    
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: var(--border-radius);
        border: 2px solid transparent;
    }
    
    .alert-success {
        background-color: rgba(0, 200, 81, 0.1);
        border-color: #00c851;
        color: #00c851;
    }
    
    .alert-error {
        background-color: rgba(255, 0, 0, 0.1);
        border-color: #ff0000;
        color: #ff0000;
    }
    
    /* Theme Options Styling */
    .theme-options {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }
    
    .theme-option {
        position: relative;
    }
    
    .theme-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .theme-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        padding: 10px;
        border: 4px solid transparent;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }
    
    .theme-option input[type="radio"]:checked + .theme-card {
        border-color: var(--primary-color);
    }
    
    .theme-preview {
        width: 160px;
        height: 120px;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        overflow: hidden;
        margin-bottom: 10px;
    }
    
    .preview-header {
        height: 20px;
        background-color: #ffffff;
        border-bottom: 2px solid var(--border-color);
    }
    
    .preview-content {
        display: flex;
        height: calc(100% - 20px);
    }
    
    .preview-sidebar {
        width: 30%;
        height: 100%;
        border-right: 2px solid var(--border-color);
    }
    
    .light-theme .preview-sidebar,
    .light-theme .preview-main {
        background-color: #ffffff;
    }
    
    .dark-theme .preview-sidebar,
    .dark-theme .preview-main {
        background-color: #1e1e1e;
    }
    
    .preview-main {
        width: 70%;
        height: 100%;
    }
    
    .theme-name {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-color);
    }
    
    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }
        
        .settings-nav {
            margin-bottom: 20px;
        }
        
        .settings-nav-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .settings-nav-item {
            margin-bottom: 0;
        }
        
        .settings-nav-link {
            padding: 8px 12px;
            font-size: 0.7rem;
        }
        
        .settings-nav-link i {
            margin-right: 5px;
            font-size: 16px;
        }
    }
</style>';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($currentTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'common_styles.php'; ?>
    <?php echo $additionalStyles; ?>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>

    <div class="settings-container">
        <div class="page-header">
            <h1 class="page-title"><i class="material-icons">settings</i> Account Settings</h1>
            <p class="page-description">Manage your profile and security settings</p>
        </div>
        
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-error">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <div class="settings-nav">
                <ul class="settings-nav-list">
                    <li class="settings-nav-item">
                        <a href="#profile" class="settings-nav-link active" data-section="profile">
                            <i class="material-icons">person</i> Profile
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#security" class="settings-nav-link" data-section="security">
                            <i class="material-icons">security</i> Security
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#preferences" class="settings-nav-link" data-section="preferences">
                            <i class="material-icons">palette</i> Appearance
                        </a>
                    </li>


                </ul>
            </div>
            
            <div class="settings-content">
                <!-- Profile Section -->
                <div id="profile" class="settings-section active">
                    <h2 class="settings-section-title">Profile Information</h2>
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <small>Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($userDetails['full_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($userDetails['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea id="bio" name="bio" class="form-control"><?php echo htmlspecialchars($userDetails['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-submit">Save Changes</button>
                    </form>
                </div>
                
                <!-- Security Section -->
                <div id="security" class="settings-section">
                    <h2 class="settings-section-title">Security Settings</h2>
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <small>Password must be at least 8 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="update_password" class="btn-submit">Update Password</button>
                    </form>
                    
                    <div class="reset-code-section" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <h3>Account Recovery Code</h3>
                        <p>This code can be used to recover your account if you forget your password.</p>
                        
                        <div class="reset-code-display" style="background: rgba(var(--primary-color-rgb), 0.1); padding: 20px; border-radius: 5px; margin: 20px 0; border: 3px solid var(--border-color); box-shadow: var(--shadow); text-align: center;">
                            <span style="font-family: 'Press Start 2P', monospace; font-size: 24px; letter-spacing: 3px; color: var(--primary-color);">
                                <?php echo htmlspecialchars($userDetails['reset_code'] ?? 'No code available'); ?>
                            </span>
                        </div>
                        
                        <form action="" method="post">
                            <button type="submit" name="regenerate_reset_code" class="btn-secondary" style="background-color: #6c757d; color: white;">Regenerate Recovery Code</button>
                        </form>
                    </div>
                </div>
                                <!-- Appearance Section -->
                <div id="preferences" class="settings-section">
                    <h2 class="settings-section-title">Appearance Settings</h2>
                    <div class="form-group">
                        <label class="form-label">Theme</label>
                        <div class="theme-options">
                            <div class="theme-option">
                                <input type="radio" id="theme-light" name="theme" value="light" <?php echo ($currentTheme === 'light') ? 'checked' : ''; ?>>
                                <label for="theme-light" class="theme-card light-theme <?php echo ($currentTheme === 'light') ? 'active' : ''; ?>">
                                    <div class="theme-preview">
                                        <div class="preview-header"></div>
                                        <div class="preview-content">
                                            <div class="preview-sidebar"></div>
                                            <div class="preview-main"></div>
                                        </div>
                                    </div>
                                    <span class="theme-name">Light</span>
                                </label>
                            </div>
                            <div class="theme-option">
                                <input type="radio" id="theme-dark" name="theme" value="dark" <?php echo ($currentTheme === 'dark') ? 'checked' : ''; ?>>
                                <label for="theme-dark" class="theme-card dark-theme <?php echo ($currentTheme === 'dark') ? 'active' : ''; ?>">
                                    <div class="theme-preview">
                                        <div class="preview-header"></div>
                                        <div class="preview-content">
                                            <div class="preview-sidebar"></div>
                                            <div class="preview-main"></div>
                                        </div>
                                    </div>
                                    <span class="theme-name">Dark</span>
                                </label>
                            </div>
                        </div>
                        <div class="btn-group" style="margin-top: 15px;">
                            <a href="#" class="btn btn-primary" id="apply-theme" onclick="applySelectedTheme(); return false;">
                                <i class="material-icons">save</i> Apply Theme
                            </a>
                        </div>
                        
                        <script>
                            function applySelectedTheme() {
                                const selectedTheme = document.querySelector('input[name="theme"]:checked').value;
                                window.location.href = 'account_settings.php?set_theme=' + selectedTheme;
                            }
                            
                            // Preview theme when radio buttons are changed
                            document.addEventListener('DOMContentLoaded', function() {
                                const themeOptions = document.querySelectorAll('input[name="theme"]');
                                themeOptions.forEach(option => {
                                    option.addEventListener('change', function() {
                                        const selectedTheme = this.value;
                                        document.documentElement.setAttribute('data-theme', selectedTheme);
                                    });
                                });
                            });
                        </script>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script>
        // Tab navigation for settings
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.settings-nav-link');
            const sections = document.querySelectorAll('.settings-section');
            

            
            // Show active section based on URL hash
            function showActiveSection() {
                const hash = window.location.hash || '#profile';
                const section = hash.substring(1);
                
                // Hide all sections
                sections.forEach(section => {
                    section.classList.remove('active');
                });
                
                // Remove active class from all nav links
                navLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Show active section
                const activeSection = document.getElementById(section);
                if (activeSection) {
                    activeSection.classList.add('active');
                }
                
                // Add active class to active nav link
                const activeLink = document.querySelector(`.settings-nav-link[data-section="${section}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
            }
            
            // Handle nav link clicks
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const section = this.getAttribute('data-section');
                    window.location.hash = section;
                    
                    // Show active section
                    showActiveSection();
                });
            });
            
            // Show active section on page load
            showActiveSection();
            
            // Handle hash change
            window.addEventListener('hashchange', showActiveSection);
            
            <?php if ($profileUpdated): ?>
                // Show profile section if profile was updated
                window.location.hash = 'profile';
                showActiveSection();
            <?php elseif ($passwordUpdated): ?>
                // Show security section if password was updated
                window.location.hash = 'security';
                showActiveSection();

            <?php endif; ?>
        });
    </script>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
    
    <!-- Theme functionality is now handled by theme.js -->
</body>
</html>
