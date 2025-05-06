<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle logout - This needs to be at the top before any output is sent
if (isset($_GET['logout']) && !headers_sent()) {
    // Clear all session data
    $_SESSION = array();
    
    // If a session cookie is used, clear it too
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"
        ]);
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to homepage
    header("Location: /capstone/homepage.php");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : '';

// Check if user is admin
$isAdmin = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Redirect admin users to admin dashboard if they're not already in the admin section
// Only do this if headers haven't been sent yet
if ($isAdmin && !strpos($_SERVER['PHP_SELF'], '/admin/') && !headers_sent()) {
    // Only redirect if not already in the admin section
    $currentScript = basename($_SERVER['PHP_SELF']);
    if ($currentScript !== 'index.php' && $currentScript !== 'login.php' && $currentScript !== 'logout.php') {
        header('Location: /capstone/admin/dashboard.php');
        exit;
    }
}

// Get user profile picture if logged in
$profilePicture = '';
$authProvider = '';
if ($isLoggedIn) {
    // Include database connection
    require_once $_SERVER['DOCUMENT_ROOT'] . '/capstone/config/db_connect.php';
    
    $userId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : $_SESSION['admin_id'];
    $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';
    
    if ($userRole === 'admin') {
        $userSql = "SELECT auth_provider FROM admins WHERE admin_id = ?";
    } else {
        $userSql = "SELECT auth_provider FROM students WHERE student_id = ?";
    }
    
    $userResult = executeQuery($userSql, [$userId]);
    if ($userResult && count($userResult) > 0) {
        $profilePicture = ''; // Default empty since profile_picture column was removed
        $authProvider = $userResult[0]['auth_provider'] ?? 'local';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - CodaQuest' : 'CodaQuest - Python Learning Platform'; ?></title>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/capstone/common_styles.php'; ?>
    <?php if (isset($additionalStyles)): ?>
        <?php echo $additionalStyles; ?>
    <?php endif; ?>
    <script src="/capstone/js/title-shimmer.js"></script>
    <style>
        /* Basic styling for the shimmer title - animation handled by JS */
        .shimmer-title {
            display: inline-block;
            position: relative;
            color: var(--text-color);
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        /* Header and Navigation Styles */
        header {
            background-color: var(--header-bg);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 20;
            border-bottom: 4px solid var(--border-color);
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            width: 33%;
        }
        
        .logo-placeholder {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
        }
        
        .logo-placeholder i {
            font-size: 36px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            height: 36px;
            width: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .site-title {
            text-align: center;
            flex-grow: 1;
            display: flex;
            justify-content: center;
            width: 34%;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .site-title h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        /* This ensures the shimmer effect takes precedence */
        .site-title h1.shimmer-title {
            color: var(--text-color) !important;
        }
        
        .user-section {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            width: 33%;
            gap: 15px;
        }
        
        .welcome-text {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .username {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .profile-pic {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            overflow: hidden;
            border: 2px solid var(--primary-color);
        }
        
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .auth-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }
        
        .auth-buttons a {
            margin: 0 5px;
            white-space: nowrap;
        }
        
        .btn-secondary, .btn-primary {
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
        }
        
        /* Navigation Menu Styles */
        .nav-menu {
            display: flex;
            justify-content: center;
            width: 100%;
            background-color: var(--nav-bg) !important;
            padding: 0;
            margin-top: 0;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            z-index: 20;
            box-shadow: var(--shadow);
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
            background-color: var(--nav-bg) !important;
            width: 100%;
            gap: 20px;
        }
        
        .nav-item {
            position: relative;
            margin: 0;
            padding: 0;
            background-color: var(--nav-bg) !important;
            text-align: center;
        }
        
        .nav-link {
            display: block;
            padding: 15px 25px;
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 600;
            font-size: 1rem;
            background-color: var(--nav-bg) !important;
            text-align: center;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary-color);
            background-color: rgba(var(--primary-color-rgb), 0.1);
            /* Removed border-bottom underline effect */
        }
        
        /* Dropdown Menu Styles */
        .dropdown {
            position: relative;
            display: inline-block;
            z-index: 20;
            background-color: var(--nav-bg);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 4px;
            overflow: hidden;
            right: 0;
            border: 2px solid var(--border-color);
            background-color: var(--card-bg);
        }
        
        .dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: var(--transition);
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .dropdown-content a:last-child {
            border-bottom: none;
        }
        
        .dropdown-content a:hover {
            background-color: var(--nav-hover-bg);
            color: var(--primary-color);
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-toggle {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .dropdown-toggle i {
            font-size: 16px;
            transition: transform 0.3s ease;
        }
        
        .dropdown:hover .dropdown-toggle i {
            transform: rotate(180deg);
        }
        
        /* Mobile Menu Styles */
        .hamburger-menu {
            display: none;
            cursor: pointer;
            padding: 10px;
            z-index: 1000;
        }
        
        .hamburger-menu .bar {
            display: block;
            width: 25px;
            height: 3px;
            margin: 5px auto;
            background-color: var(--text-color);
            transition: all 0.3s ease-in-out;
        }
        
        @media (max-width: 768px) {
            header {
                padding: 10px 15px;
            }
            
            .site-title h1 {
                font-size: 1.5rem;
            }
            
            .hamburger-menu {
                display: block;
            }
            
            .nav-menu {
                position: fixed;
                top: 0;
                left: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background-color: white;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                transition: left 0.3s ease-in-out;
                z-index: 999;
                overflow-y: auto;
                padding-top: 70px;
                flex-direction: column;
            }
            
            .nav-menu.active {
                left: 0;
            }
            
            .nav-links {
                flex-direction: column;
                width: 100%;
            }
            
            .nav-item {
                width: 100%;
            }
            
            .nav-link {
                width: 100%;
                padding: 15px 20px;
                border-bottom: 1px solid #eee;
            }
            
            .dropdown-content {
                position: static;
                box-shadow: none;
                display: none;
                background-color: var(--card-bg);
                padding-left: 20px;
                border: none;
                border-left: 2px solid var(--border-color);
            }
            
            .dropdown.active .dropdown-content {
                display: block;
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                z-index: 998;
            }
            
            .overlay.active {
                display: block;
            }
            
            .hamburger-menu.active .bar:nth-child(1) {
                transform: translateY(8px) rotate(45deg);
            }
            
            .hamburger-menu.active .bar:nth-child(2) {
                opacity: 0;
            }
            
            .hamburger-menu.active .bar:nth-child(3) {
                transform: translateY(-8px) rotate(-45deg);
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background Script -->
    <script src="/capstone/matrix-animation.js"></script>
    <!-- Title Shimmer Effect -->
    <script src="/capstone/js/title-shimmer.js"></script>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Header -->
    <header>
        <div class="logo-section">
            <div class="logo-placeholder">
                <i class="material-icons">code</i>
            </div>
        </div>
        
        <div class="site-title">
            <h1 class="shimmer-title">CodaQuest!</h1>
        </div>
        
        <div class="user-section">
            <?php if ($isLoggedIn): ?>
                <div class="welcome-text">
                    Welcome back, <span class="username"><?php echo htmlspecialchars($username); ?></span>!
                </div>
            <?php else: ?>
                <div class="auth-buttons">
                    <a href="login.php" class="btn btn-secondary">Log In</a>
                    <a href="signup.php" class="btn btn-primary">Sign Up</a>
                </div>
            <?php endif; ?>
            
            <!-- Hamburger Menu for Mobile -->
            <div class="hamburger-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </header>

    <!-- Navigation Menu -->
    <nav class="nav-menu">
        <!-- Overlay for Mobile Menu -->
        <div class="overlay"></div>
        
        <ul class="nav-links">
            <li class="nav-item">
                <a href="homepage.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' || basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    Home
                </a>
            </li>
            <?php if ($isLoggedIn): ?>
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="quizzes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'active' : ''; ?>">
                    Quizzes
                </a>
            </li>
            <li class="nav-item">
                <a href="challenges.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'challenges.php' ? 'active' : ''; ?>">
                    Challenges
                </a>
            </li>
            <li class="nav-item">
                <a href="leaderboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
                    Leaderboard
                </a>
            </li>
            <li class="nav-item">
                <a href="achievements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'achievements.php' ? 'active' : ''; ?>">
                    Achievement
                </a>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle">
                    My Account <i class="material-icons">arrow_drop_down</i>
                </a>
                <div class="dropdown-content">
                    <a href="account_settings.php">Account Settings</a>
                    <a href="/capstone/includes/logout.php">Log Out</a>
                </div>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <script>
        // Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger-menu');
            const navMenu = document.querySelector('.nav-menu');
            const overlay = document.querySelector('.overlay');
            const navLinks = document.querySelectorAll('.nav-link');
            const dropdowns = document.querySelectorAll('.dropdown');
            
            // Toggle mobile menu
            hamburger.addEventListener('click', function() {
                this.classList.toggle('active');
                navMenu.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
            });
            
            // Close menu when overlay is clicked
            overlay.addEventListener('click', function() {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
            
            // Dropdown toggle for both mobile and desktop
            dropdowns.forEach(dropdown => {
                const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
                
                // Handle click events for dropdown toggle
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    dropdown.classList.toggle('active');
                    
                    // Close other dropdowns when one is opened
                    dropdowns.forEach(otherDropdown => {
                        if (otherDropdown !== dropdown && otherDropdown.classList.contains('active')) {
                            otherDropdown.classList.remove('active');
                        }
                    });
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            });
            
            // Close menu when a nav link is clicked
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (!link.classList.contains('dropdown-toggle')) {
                        hamburger.classList.remove('active');
                        navMenu.classList.remove('active');
                        overlay.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            });
        });
    </script>

    <!-- Main Content Container -->
    <div class="main-content">
