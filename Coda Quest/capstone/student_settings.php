<?php
// Include authentication check
require_once 'includes/auth_check.php';

// Start session is already handled in auth_check.php

// Check if user is logged in as a student
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get student information
$studentId = $_SESSION['student_id'];
$studentSql = "SELECT username, email, full_name FROM students WHERE student_id = ?";
$studentResult = executeQuery($studentSql, [$studentId]);
$studentName = $studentResult[0]['username'] ?? 'Student';
$studentEmail = $studentResult[0]['email'] ?? '';
$studentFullName = $studentResult[0]['full_name'] ?? '';

// Handle profile update
$updateMessage = '';
$updateError = '';

// Direct theme change handling - simplified approach
if (isset($_GET['set_theme']) && in_array($_GET['set_theme'], ['dark', 'light'])) {
    // Get theme from URL parameter
    $newTheme = $_GET['set_theme'];
    
    // Update in database
    $updateThemeSql = "UPDATE students SET theme = ? WHERE student_id = ?";
    executeQuery($updateThemeSql, [$newTheme, $studentId]);
    
    // Update in session
    $_SESSION['student_theme'] = $newTheme;
    
    // Redirect to remove the parameter from URL
    header("Location: student_settings.php?theme_updated=1&t=" . time());
    exit();
}

// Get current theme directly from database
$themeQuery = "SELECT theme FROM students WHERE student_id = ?";
$themeResult = executeQuery($themeQuery, [$studentId]);
$currentTheme = ($themeResult && isset($themeResult[0]['theme']) && !empty($themeResult[0]['theme'])) 
    ? $themeResult[0]['theme'] 
    : 'dark';

// Always update session with current theme from database
$_SESSION['student_theme'] = $currentTheme;

// Show success message if theme was updated
if (isset($_GET['theme_updated']) && $_GET['theme_updated'] == 1) {
    $updateMessage = "Theme updated successfully.";
}

// Handle profile update
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
        if ($newEmail !== $studentEmail) {
            $fieldsToUpdate[] = "email = ?";
            $valuesToUpdate[] = $newEmail;
            $studentEmail = $newEmail;
            $hasChanges = true;
        }
        
        // Check if full name has changed
        if ($newFullName !== $studentFullName) {
            $fieldsToUpdate[] = "full_name = ?";
            $valuesToUpdate[] = $newFullName;
            $studentFullName = $newFullName;
            $hasChanges = true;
        }
        
        // Update database if there are changes
        if ($hasChanges) {
            $updateSql = "UPDATE students SET " . implode(", ", $fieldsToUpdate) . " WHERE student_id = ?";
            $valuesToUpdate[] = $studentId;
            executeQuery($updateSql, $valuesToUpdate);
            $updateMessage = "Profile updated successfully.";
        }
    }
}

// Set page title
$pageTitle = "Settings - CodaQuest";
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
    <?php include_once 'common_styles.php'; ?>
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
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 12px;
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
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 4px solid var(--border-color);
        }

        .section-title {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }

        .section-icon {
            margin-right: 10px;
            color: var(--primary-color);
        }

        .theme-options {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }

        .theme-option {
            flex: 1;
            min-width: 200px;
        }

        .theme-card {
            display: block;
            padding: 15px;
            border: 4px solid transparent;
            border-radius: 0px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .theme-card.active {
            border-color: var(--primary-color);
        }

        .theme-preview {
            width: 100%;
            height: 150px;
            border: 4px solid var(--border-color);
            margin-bottom: 10px;
            overflow: hidden;
            position: relative;
        }

        .preview-header {
            height: 30px;
            background-color: var(--header-bg);
            border-bottom: 4px solid var(--border-color);
        }

        .preview-content {
            display: flex;
            height: calc(100% - 30px);
        }

        .preview-sidebar {
            width: 30%;
            height: 100%;
            background-color: var(--card-bg);
            border-right: 4px solid var(--border-color);
        }

        .preview-main {
            width: 70%;
            height: 100%;
            background-color: var(--background-color);
        }

        .light-theme .preview-header {
            background-color: #ffffff;
        }

        .light-theme .preview-sidebar {
            background-color: #f5f9ff;
        }

        .light-theme .preview-main {
            background-color: #ffffff;
        }

        .dark-theme .preview-header {
            background-color: #1e1e2d;
        }

        .dark-theme .preview-sidebar {
            background-color: #1e1e2d;
        }

        .dark-theme .preview-main {
            background-color: #121212;
        }

        .theme-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-color);
            margin-top: 5px;
            text-align: center;
            display: block;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 4px solid transparent;
            border-radius: 0px;
        }

        .alert-success {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border-color: var(--primary-color);
            color: var(--text-color);
        }

        .alert-danger {
            background-color: rgba(255, 107, 142, 0.1);
            border-color: #ff6b8e;
            color: var(--text-color);
        }

        @media (max-width: 768px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .theme-options {
                flex-direction: column;
                align-items: center;
            }
            
            .theme-option {
                margin-bottom: 15px;
                width: 100%;
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
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>

    <div class="main-content">
        <div class="container">
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

            <div class="settings-grid">
                <div class="settings-card">
                    <div class="section-header">
                        <h2 class="section-title"><i class="material-icons section-icon">person</i> Profile Settings</h2>
                    </div>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($studentName); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($studentFullName); ?>" placeholder="Enter your full name">
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($studentEmail); ?>" required>
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
                        <h2 class="section-title"><i class="material-icons section-icon">palette</i> Theme Settings</h2>
                    </div>
                    <div class="form-group">
                        <label>Select Theme</label>
                        <div class="theme-options">
                            <div class="theme-option">
                                <a href="student_settings.php?set_theme=light" class="theme-card light-theme <?php echo ($currentTheme === 'light') ? 'active' : ''; ?>">
                                    <div class="theme-preview">
                                        <div class="preview-header"></div>
                                        <div class="preview-content">
                                            <div class="preview-sidebar"></div>
                                            <div class="preview-main"></div>
                                        </div>
                                    </div>
                                    <span class="theme-name">Light</span>
                                </a>
                            </div>
                            <div class="theme-option">
                                <a href="student_settings.php?set_theme=dark" class="theme-card dark-theme <?php echo ($currentTheme === 'dark') ? 'active' : ''; ?>">
                                    <div class="theme-preview">
                                        <div class="preview-header"></div>
                                        <div class="preview-content">
                                            <div class="preview-sidebar"></div>
                                            <div class="preview-main"></div>
                                        </div>
                                    </div>
                                    <span class="theme-name">Dark</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
