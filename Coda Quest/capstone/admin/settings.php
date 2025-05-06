<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username, email, reset_code, full_name FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';
$adminEmail = $adminResult[0]['email'] ?? '';
$resetCode = $adminResult[0]['reset_code'] ?? '';
$adminFullName = $adminResult[0]['full_name'] ?? '';

// Handle profile update
$updateMessage = '';
$updateError = '';

// Direct theme change handling - simplified approach
if (isset($_GET['set_theme']) && in_array($_GET['set_theme'], ['dark', 'light'])) {
    // Get theme from URL parameter
    $newTheme = $_GET['set_theme'];
    
    // Update in database
    $updateThemeSql = "UPDATE admins SET theme = ? WHERE admin_id = ?";
    executeQuery($updateThemeSql, [$newTheme, $adminId]);
    
    // Update in session
    $_SESSION['admin_theme'] = $newTheme;
    
    // Redirect to remove the parameter from URL
    header("Location: settings.php?theme_updated=1&t=" . time());
    exit();
}

// Get current theme directly from database
$themeQuery = "SELECT theme FROM admins WHERE admin_id = ?";
$themeResult = executeQuery($themeQuery, [$adminId]);
$currentTheme = ($themeResult && isset($themeResult[0]['theme']) && !empty($themeResult[0]['theme'])) 
    ? $themeResult[0]['theme'] 
    : 'dark';

// Always update session with current theme from database
$_SESSION['admin_theme'] = $currentTheme;

// Show success message if theme was updated
if (isset($_GET['theme_updated']) && $_GET['theme_updated'] == 1) {
    $updateMessage = "Theme updated successfully.";
}

// Get current theme preference from session or database
if (!isset($_SESSION['admin_theme'])) {
    // Get from database
    $themeQuery = "SELECT theme FROM admins WHERE admin_id = ?";
    $themeResult = executeQuery($themeQuery, [$adminId]);
    $currentTheme = ($themeResult && isset($themeResult[0]['theme'])) ? $themeResult[0]['theme'] : 'dark';
    
    // Store in session
    $_SESSION['admin_theme'] = $currentTheme;
} else {
    $currentTheme = $_SESSION['admin_theme'];
}

// Show success message if theme was updated
if (isset($_GET['theme_updated']) && $_GET['theme_updated'] == 1) {
    $updateMessage = "Theme updated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newEmail = trim($_POST['email']);
    $newFullName = trim($_POST['full_name']);

    // Validate email
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $updateError = "Please enter a valid email address.";
    } else {
        $hasChanges = false;
        $fieldsToUpdate = [];
        $valuesToUpdate = [];
        
        // Check if email has changed
        if ($newEmail !== $adminEmail) {
            $fieldsToUpdate[] = "email = ?";
            $valuesToUpdate[] = $newEmail;
            $adminEmail = $newEmail;
            $hasChanges = true;
        }
        
        // Check if full name has changed
        if ($newFullName !== $adminFullName) {
            $fieldsToUpdate[] = "full_name = ?";
            $valuesToUpdate[] = $newFullName;
            $adminFullName = $newFullName;
            $hasChanges = true;
        }
        
        // Update database if there are changes
        if ($hasChanges) {
            $updateSql = "UPDATE admins SET " . implode(", ", $fieldsToUpdate) . " WHERE admin_id = ?";
            $valuesToUpdate[] = $adminId;
            executeQuery($updateSql, $valuesToUpdate);
            $updateMessage = "Profile updated successfully.";
        }
    }
}

// Set page title
$pageTitle = "Settings - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($currentTheme); ?>">
<!-- Force no-cache for theme changes -->
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to settings page */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .settings-card {
            background-color: var(--card-bg);
            border-radius: 0px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: 4px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .settings-card p {
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border-radius: 0px;
            border: 4px solid var(--border-color);
            background-color: var(--input-bg);
            color: var(--text-color);
            font-family: 'Press Start 2P', 'Courier New', monospace;
            font-size: 0.8rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.25);
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .setting-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .setting-description {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        /* Theme options styling */
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
            border-radius: 0px;
            transition: all 0.3s ease;
        }
        
        .theme-option input[type="radio"]:checked + .theme-card {
            border-color: var(--primary-color);
        }
        
        .theme-preview {
            width: 160px;
            height: 120px;
            border: 4px solid var(--border-color);
            border-radius: 0px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .preview-header {
            height: 20px;
            border-bottom: 4px solid var(--border-color);
        }
        
        .preview-content {
            display: flex;
            height: calc(100% - 20px);
        }
        
        .preview-sidebar {
            width: 30%;
            height: 100%;
            border-right: 4px solid var(--border-color);
        }
        
        .light-theme .preview-header {
            background-color: #b2defd;
        }
        
        .light-theme .preview-sidebar,
        .light-theme .preview-main {
            background-color: #f5f9ff;
        }
        
        .dark-theme .preview-header {
            background-color: #242832;
        }
        
        .dark-theme .preview-sidebar,
        .dark-theme .preview-main {
            background-color: #1e1e2d;
        }
        
        .theme-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-color);
            margin-top: 5px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-color);
        }

        .info-value {
            color: var(--text-color);
            background-color: rgba(var(--primary-color-rgb), 0.05);
            padding: 4px 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-card {
                margin-bottom: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .theme-options {
                flex-direction: column;
                align-items: center;
            }
            
            .theme-option {
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .form-group {
                margin-bottom: 15px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                margin-bottom: 10px;
                width: 100%;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-value {
                margin-top: 5px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">settings</i> Settings</h1>
                <div class="user-info">

                    <!-- User avatar removed -->
                </div>
            </div>

            <?php if (!empty($updateMessage)): ?>
                <div class="alert alert-success">
                    <?php echo $updateMessage; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($updateError)): ?>
                <div class="alert alert-danger">
                    <?php echo $updateError; ?>
                </div>
            <?php endif; ?>


            <div class="dashboard-content">
                <div class="settings-grid">
                    <div class="settings-card">
                        <div class="section-header">
                            <h2 class="section-title"><i class="material-icons section-icon">person</i> Profile Settings</h2>
                        </div>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($adminName); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($adminFullName); ?>" placeholder="Enter your full name">
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($adminEmail); ?>" required>
                            </div>
                            <div class="btn-group">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="material-icons">save</i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="settings-card">
                        <div class="section-header">
                            <h2 class="section-title"><i class="material-icons section-icon">security</i> Security Information</h2>
                        </div>
                        <div class="form-group">
                            <label>Reset Code</label>
                            <div class="reset-code-display">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($resetCode); ?>" readonly>
                                <small class="form-text">This code can be used to reset your account if you get locked out.</small>
                            </div>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="section-header">
                            <h2 class="section-title"><i class="material-icons section-icon">palette</i> Theme Settings</h2>
                        </div>
                        <div class="form-group">
                            <label>Select Theme</label>
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
                            <!-- Theme info removed as requested -->
                            <div class="btn-group" style="margin-top: 15px;">
                                <a href="#" class="btn btn-primary" id="apply-theme" onclick="applySelectedTheme(); return false;">
                                    <i class="material-icons">save</i> Apply Theme
                                </a>
                            </div>
                            
                            <script>
                                function applySelectedTheme() {
                                    const selectedTheme = document.querySelector('input[name="theme"]:checked').value;
                                    window.location.href = 'settings.php?set_theme=' + selectedTheme;
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
    </div>

    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>

    <!-- Theme functionality script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get theme form elements
            const themeForm = document.getElementById('theme-form');
            const themeOptions = document.querySelectorAll('input[name="theme"]');
            const themeSubmitButton = document.getElementById('apply-theme');
            
            // Preview theme when radio buttons are changed
            themeOptions.forEach(option => {
                option.addEventListener('change', function() {
                    const selectedTheme = this.value;
                    document.documentElement.setAttribute('data-theme', selectedTheme);
                });
            });
            
            // Handle form submission directly
            if (themeForm) {
                themeForm.addEventListener('submit', function(e) {
                    // Let the form submit normally - the PHP will handle it
                    console.log('Theme form submitted');
                });
            }
        });
    </script>
</body>
</html>
