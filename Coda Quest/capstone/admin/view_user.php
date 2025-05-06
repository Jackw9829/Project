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
$pdo = getDbConnection(); // Ensure PDO is defined for this page

// Include theme helper
require_once 'theme_helper.php';

// Get the current theme
$currentTheme = isset($_SESSION['admin_theme']) ? $_SESSION['admin_theme'] : 'dark';

// Get admin information
$adminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit();
}

$viewUserId = $_GET['id'];

// Try to get student details
$studentSql = "SELECT student_id as id, username, email, date_registered, is_active, 'student' as role, total_points, current_level, NULL as bio, NULL as profile_image FROM students WHERE student_id = ?";
$studentResult = executeQuery($studentSql, [$viewUserId]);

if ($studentResult && count($studentResult) > 0) {
    $user = $studentResult[0];
    $user['user_type'] = 'student';
    // Fetch last login from activity_log (use created_at)
    $lastLoginSql = "SELECT MAX(created_at) as last_login FROM activity_log WHERE student_id = ? AND activity_type = 'login'";
    $lastLoginResult = executeQuery($lastLoginSql, [$user['id']]);
    $user['last_login'] = $lastLoginResult[0]['last_login'] ?? null;
} else {
    // Try to get admin details
    $adminSql = "SELECT admin_id as id, username, email, date_registered, is_active, 'admin' as role, NULL as total_points, NULL as current_level, NULL as bio, NULL as profile_image FROM admins WHERE admin_id = ?";
    $adminResult = executeQuery($adminSql, [$viewUserId]);
    if ($adminResult && count($adminResult) > 0) {
        $user = $adminResult[0];
        $user['user_type'] = 'admin';
        // Fetch last login from activity_log (use created_at)
        $lastLoginSql = "SELECT MAX(created_at) as last_login FROM activity_log WHERE admin_id = ? AND activity_type = 'login'";
        $lastLoginResult = executeQuery($lastLoginSql, [$user['id']]);
        $user['last_login'] = $lastLoginResult[0]['last_login'] ?? null;
    } else {
        // User not found
        header("Location: users.php");
        exit();
    }
}

// Only fetch progress and achievements for students
if ($user['user_type'] === 'student') {
    try {
        $progressSql = "SELECT COUNT(*) as completed_quizzes FROM quiz_attempts WHERE student_id = ? AND is_completed = 1";
        $progressResult = executeQuery($progressSql, [$user['id']]);
        $completedQuizzes = $progressResult[0]['completed_quizzes'] ?? 0;
    } catch (Exception $e) {
        $completedQuizzes = 0;
    }

} else {
    $completedQuizzes = 0;
    $achievements = [];
}

// Set page title
$pageTitle = "View User - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for view user page */
        .user-info-card {
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .user-info-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .user-info-title {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .user-info-title i {
            margin-right: 10px;
        }
        
        .user-status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: #4CAF50;
            color: white;
        }
        
        .status-inactive {
            background-color: #F44336;
            color: white;
        }
        
        .role-badge {
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
            background-color: var(--primary-color);
            color: var(--text-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-section {
            background-color: rgba(var(--primary-color-rgb), 0.05);
            border-radius: var(--border-radius);
            padding: 15px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .info-section-title {
            font-size: 1rem;
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        
        .info-section-title i {
            margin-right: 8px;
            font-size: 18px;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
            box-shadow: var(--card-shadow);
        }
        
        .stat-value {
            font-size: 1.8rem;
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-buttons .btn {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-section {
                margin-bottom: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-status-badge {
                margin-top: 10px;
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
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">person</i> User Profile</h1>
            </div>

            <div class="dashboard-content">
                <div class="user-info-card">
                    <div class="user-info-header">
                        <h2 class="user-info-title">
                            <i class="material-icons"><?php echo $user['role'] === 'admin' ? 'admin_panel_settings' : 'person'; ?></i>
                            <?php echo htmlspecialchars($user['username']); ?>
                            <span class="role-badge"><?php echo ucfirst($user['role']); ?></span>
                        </h2>
                        <span class="user-status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label"><i class="material-icons" style="font-size: 16px; vertical-align: text-bottom;">email</i> Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <?php if ($user['user_type'] === 'student'): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($user['total_points']); ?></div>
                            <div class="stat-label">Total Points</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $user['current_level']; ?></div>
                            <div class="stat-label">Current Level</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $completedQuizzes; ?></div>
                            <div class="stat-label">Completed Quizzes</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-grid">
                        <div class="info-section">
                            <h3 class="info-section-title">
                                <i class="material-icons">account_circle</i> Account Information
                            </h3>
                            <div class="info-item">
                                <span class="info-label">User ID:</span>
                                <span class="info-value"><?php echo htmlspecialchars($user['id']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Joined Date:</span>
                                <span class="info-value"><?php echo date('F d, Y', strtotime($user['date_registered'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Login:</span>
                                <span class="info-value"><?php echo $user['last_login'] ? date('F d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Account Type:</span>
                                <span class="info-value"><?php echo ucfirst($user['user_type']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($user['user_type'] === 'student'): ?>
                        <div class="info-section">
                            <h3 class="info-section-title">
                                <i class="material-icons">school</i> Learning Progress
                            </h3>
                            <div class="info-item">
                                <span class="info-label">Quizzes Completed:</span>
                                <span class="info-value"><?php echo $completedQuizzes; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Current Level:</span>
                                <span class="info-value"><?php echo $user['current_level']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Points:</span>
                                <span class="info-value"><?php echo number_format($user['total_points']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="users.php" class="btn btn-secondary">
                            <i class="material-icons">arrow_back</i> Back to Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

