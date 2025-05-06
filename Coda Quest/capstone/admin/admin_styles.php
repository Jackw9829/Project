<?php
/**
 * Admin Styles for CodaQuest
 * 
 * This file contains the common CSS styles for all admin pages
 * with a pixel grid square bold design and retro gaming aesthetics.
 */
?>
<?php
// Common admin styles

// Add theme detection code to be available to all admin pages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as admin
if (isset($_SESSION['admin_id'])) {
    // Include database connection if not already included
    if (!function_exists('executeQuery')) {
        require_once '../config/db_connect.php';
    }
    
    // ALWAYS get theme directly from database to avoid caching issues
    $adminId = $_SESSION['admin_id'];
    $themeSql = "SELECT theme FROM admins WHERE admin_id = ?";
    $themeResult = executeQuery($themeSql, [$adminId]);
    
    if ($themeResult && isset($themeResult[0]['theme']) && !empty($themeResult[0]['theme'])) {
        // Valid theme found in database
        $dbTheme = $themeResult[0]['theme'];
        
        // Ensure it's a valid theme value
        if ($dbTheme === 'light' || $dbTheme === 'dark') {
            $currentTheme = $dbTheme;
        } else {
            $currentTheme = 'dark'; // Default if invalid value
            
            // Fix invalid theme in database
            $updateThemeSql = "UPDATE admins SET theme = 'dark' WHERE admin_id = ?";
            executeQuery($updateThemeSql, [$adminId]);
        }
    } else {
        // No theme in database, set default
        $currentTheme = 'dark';
        
        // Update the database with the default theme
        $updateThemeSql = "UPDATE admins SET theme = 'dark' WHERE admin_id = ?";
        executeQuery($updateThemeSql, [$adminId]);
    }
    
    // Update session with current theme
    $_SESSION['admin_theme'] = $currentTheme;
    
} else {
    // Not logged in, use default theme
    $currentTheme = 'dark';
    $_SESSION['admin_theme'] = 'dark';
}
?>
<!-- Common Admin Styles -->
<script>
    // Set the theme immediately at the earliest point possible
    document.documentElement.setAttribute('data-theme', '<?php echo $currentTheme; ?>');
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Material+Icons|Material+Icons+Outlined|Material+Icons+Round" rel="stylesheet">

<style>
    /* CSS Variables */
    :root {
        /* Common variables for dark theme (default) */
        --primary-color: #ff6b8e;
        --primary-color-rgb: 255, 107, 142;
        --secondary-color: #e64c7a;
        --accent-color: #8b5cff;
        --highlight-color: #ffde59;
        --border-radius: 0px;
        --shadow: 0 4px 0 rgba(0, 0, 0, 0.5);
        --transition: all 0.2s ease;
        --font-family: 'Press Start 2P', cursive;
        
        /* Dark theme (default) */
        --text-color: #ffffff;
        --text-muted: #aaaaaa;
        --background-color: #121212;
        --card-bg: #1e1e1e;
        --header-bg: #1e1e1e;
        --nav-bg: #1e1e1e;
        --border-color: #ff6b8e;
        --input-bg: #2a2a2a;
        --card-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    /* Light theme variables */
    [data-theme="light"] {
        --matrix-color: #ffffff; /* White for light theme */
        --primary-color: #b2defd;
        --primary-color-rgb: 178, 222, 253;
        --secondary-color: #a0cdf5;
        --accent-color: #a6d1f8;
        --highlight-color: #a1cff5;
        --text-color: #222222;
        --text-muted: #666666;
        --background-color: #ffffff;
        --card-bg: #f5f9ff;
        --header-bg: #ffffff;
        --nav-bg: #ffffff;
        --border-color: #b2defd;
        --input-bg: #ffffff;
        --card-shadow: 0 4px 0 rgba(0, 0, 0, 0.2);
    }

    /* Common Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: var(--font-family);
        font-weight: 400;
        font-style: normal;
        image-rendering: pixelated;
    }

    body {
        background-color: var(--background-color);
        color: var(--text-color);
        min-height: 100vh;
        line-height: 1.6;
        position: relative;
        z-index: 1;
        image-rendering: pixelated;
    }

    /* Material Icons styling */
    .material-icons {
        font-family: 'Material Icons';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }

    .material-icons-outlined {
        font-family: 'Material Icons Outlined';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }

    .material-icons-round {
        font-family: 'Material Icons Round';
        vertical-align: middle;
        margin-right: 5px;
        font-size: 18px;
        font-weight: normal;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        display: inline-block;
        white-space: nowrap;
        word-wrap: normal;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        -webkit-font-smoothing: antialiased;
    }
    
    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        border: 4px solid var(--border-color);
    }

    /* Button Styles */
    .btn {
        padding: 10px 16px;
        border-radius: var(--border-radius);
        border: 4px solid var(--border-color);
        cursor: pointer;
        font-size: 0.7rem;
        transition: var(--transition);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: var(--shadow);
        text-transform: uppercase;
    }

    .btn-primary {
        background-color: var(--primary-color);
        color: var(--text-color);
    }

    .btn-primary:hover {
        background-color: var(--secondary-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.5);
    }

    .btn-secondary {
        background-color: var(--card-bg);
        color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-secondary:hover {
        background-color: var(--primary-color);
        color: var(--text-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.5);
    }

    .btn-danger {
        background-color: var(--accent-color);
        color: white;
        border-color: #ff4040;
    }

    .btn-danger:hover {
        background-color: #ff4040;
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.5);
    }

    /* Admin Dashboard Specific Styles */
    .admin-layout {
        display: flex;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        background-color: var(--card-bg);
        border-right: 4px solid var(--border-color);
        position: fixed;
        height: 100%;
        z-index: 10;
    }

    .logo-container {
        padding: 20px;
        border-bottom: 4px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 68px; /* Match the header height */
        box-sizing: border-box;
    }

    .logo {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary-color);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 15px 20px;
        color: var(--text-color);
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        font-size: 12px;
        text-transform: uppercase;
    }

    .menu-item:hover {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        border-left: 4px solid var(--primary-color);
    }

    .menu-item.active {
        background-color: rgba(var(--primary-color-rgb), 0.2);
        border-left: 4px solid var(--primary-color);
        color: var(--primary-color);
    }

    .menu-item .material-icons {
        margin-right: 10px;
    }

    .main-content {
        flex: 1;
        margin-left: 250px;
        padding: 0;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background-color: var(--card-bg);
        border-bottom: 4px solid var(--border-color);
        height: 68px; /* Set fixed height to match logo-container */
        box-sizing: border-box;
    }

    .header-title {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary-color);
        text-transform: uppercase;
        display: flex;
        align-items: center;
        width: 100%;
    }

    /* User avatar styles removed */

    .dashboard-content {
        padding: 20px;
        background-color: var(--background-color);
    }

    .welcome-message {
        font-size: 14px;
        margin-bottom: 30px;
        color: var(--primary-color);
        text-transform: uppercase;
        border: 4px solid var(--border-color);
        padding: 15px;
        background-color: var(--card-bg);
    }

    .stats-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        padding: 20px;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-shadow: var(--shadow);
    }

    .stat-icon {
        font-size: 24px;
        color: var(--primary-color);
        margin-bottom: 15px;
    }

    .stat-title {
        font-size: 12px;
        color: var(--text-color);
        margin-bottom: 10px;
        text-transform: uppercase;
        text-align: center;
    }

    .stat-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary-color);
    }

    .recent-section {
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        padding: 20px;
        box-shadow: var(--shadow);
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 4px solid var(--border-color);
        padding-bottom: 15px;
    }

    .section-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        text-transform: uppercase;
    }

    .section-icon {
        color: var(--primary-color);
        margin-right: 10px;
    }

    .view-all {
        color: var(--primary-color);
        text-decoration: none;
        display: flex;
        align-items: center;
        font-size: 12px;
        text-transform: uppercase;
        border: 2px solid var(--primary-color);
        padding: 5px 10px;
    }

    .view-all:hover {
        background-color: var(--primary-color);
        color: var(--text-color);
    }

    .quizzes-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .quizzes-table th,
    .quizzes-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 2px solid var(--border-color);
        font-size: 12px;
    }

    .quizzes-table th {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        font-weight: 600;
        color: var(--primary-color);
        text-transform: uppercase;
    }

    .quizzes-table tr:last-child td {
        border-bottom: none;
    }

    .quizzes-table tr:hover td {
        background-color: rgba(var(--primary-color-rgb), 0.05);
    }

    .action-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 2px solid;
        cursor: pointer;
        margin-right: 5px;
        transition: all 0.3s ease;
        background-color: var(--card-bg);
    }

    .edit-btn {
        color: var(--highlight-color);
        border-color: var(--highlight-color);
    }

    .edit-btn:hover {
        background-color: var(--highlight-color);
        color: var(--text-color);
    }

    .delete-btn {
        color: var(--accent-color);
        border-color: var(--accent-color);
    }

    .delete-btn:hover {
        background-color: var(--accent-color);
        color: white;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-color);
    }

    .form-control {
        width: 100%;
        padding: 10px;
        border: 4px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-color);
        font-size: 12px;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 70px;
        }

        .logo-container {
            justify-content: center;
        }

        .logo {
            display: none;
        }

        .menu-item span {
            display: none;
        }

        .menu-item {
            justify-content: center;
        }

        .menu-item .material-icons {
            margin-right: 0;
        }

        .main-content {
            margin-left: 70px;
        }
    }

    @media (max-width: 576px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
    }

    /* File upload button styling */
    input[type="file"] {
        cursor: pointer;
        font-family: 'Press Start 2P', 'Courier New', monospace;
        font-size: 12px;
    }

    input[type="file"]::-webkit-file-upload-button,
    input[type="file"]::file-selector-button {
        background-color: var(--primary-color);
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        margin-right: 10px;
        cursor: pointer;
        font-family: 'Press Start 2P', 'Courier New', monospace;
        font-size: 11px;
        transition: all 0.3s ease;
    }

    input[type="file"]::-webkit-file-upload-button:hover,
    input[type="file"]::file-selector-button:hover {
        background-color: var(--accent-color);
        transform: translateY(-1px);
    }

    /* Custom wrapper for file inputs to allow more styling options */
    .file-upload-wrapper {
        position: relative;
        display: block;
        width: 100%;
    }

    .file-upload-wrapper .form-control {
        padding: 12px;
        color: var(--text-color);
    }

    .file-upload-label {
        display: block;
        padding: 10px 15px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 4px;
        text-align: center;
        cursor: pointer;
        margin-bottom: 8px;
        transition: all 0.3s ease;
    }

    .file-upload-label:hover {
        background-color: var(--accent-color);
        transform: translateY(-2px);
    }

    .file-name-display {
        display: block;
        font-size: 12px;
        margin-top: 5px;
        color: var(--text-color);
    }
</style>

<!-- Common Sidebar Menu -->
<div class="sidebar">
    <div class="logo-container">
        <div class="logo">CodaQuest</div>
    </div>
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="material-icons">dashboard</i>
            <span>Dashboard</span>
        </a>
        <a href="managequiz.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'managequiz.php' ? 'active' : ''; ?>">
            <i class="material-icons">quiz</i>
            <span>Content Management</span>
        </a>
        <a href="analytics.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <i class="material-icons">bar_chart</i>
            <span>Analytics</span>
        </a>
        <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
            <i class="material-icons">people</i>
            <span>Users</span>
        </a>
        <a href="admin_messages.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admin_messages.php' ? 'active' : ''; ?>">
            <i class="material-icons">message</i>
            <span>Messages</span>
        </a>
        <!-- Maintenance link removed -->
        <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="material-icons">settings</i>
            <span>Settings</span>
        </a>
        <a href="../includes/logout.php" class="menu-item">
            <i class="material-icons">logout</i>
            <span>Log Out</span>
        </a>
    </div>
</div>

<!-- Theme initialization script -->
<script>
    // Apply the theme from the data-theme attribute on page load
    document.addEventListener('DOMContentLoaded', function() {
        const htmlElement = document.documentElement;
        const theme = htmlElement.getAttribute('data-theme') || 'dark';
        htmlElement.setAttribute('data-theme', theme);
        
        // Also store in localStorage for client-side persistence
        localStorage.setItem('codaquest-theme', theme);
    });
</script>
