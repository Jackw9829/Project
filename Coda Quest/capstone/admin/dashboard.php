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
$adminSql = "SELECT username, theme FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Set theme from database if available
if (isset($adminResult[0]['theme'])) {
    $_SESSION['admin_theme'] = $adminResult[0]['theme'];
}

// Get the admin theme from session
$currentTheme = $_SESSION['admin_theme'] ?? 'dark';

// Get dashboard statistics
function getTotalQuizzes() {
    try {
        $sql = "SELECT COUNT(*) as total FROM quizzes";
        $result = executeSimpleQuery($sql);
        return is_array($result) && isset($result[0]['total']) ? $result[0]['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total quizzes: " . $e->getMessage());
        return 0;
    }
}

function getCompletionRate() {
    try {
        // Modified to use quiz_attempts with students table
        $sql = "SELECT 
                    COUNT(DISTINCT qa.student_id) as completed_users,
                    COUNT(DISTINCT s.student_id) as total_users
                FROM students s
                LEFT JOIN quiz_attempts qa ON s.student_id = qa.student_id AND qa.is_completed = 1";
        $result = executeSimpleQuery($sql);
        
        if (!is_array($result) || !isset($result[0])) {
            return 0;
        }
        
        $completedUsers = $result[0]['completed_users'] ?? 0;
        $totalUsers = $result[0]['total_users'] ?? 0;
        
        if ($totalUsers > 0) {
            return round(($completedUsers / $totalUsers) * 100);
        }
        
        return 0;
    } catch (Exception $e) {
        error_log("Error calculating completion rate: " . $e->getMessage());
        return 0;
    }
}

function getActiveStudents() {
    try {
        $sql = "SELECT COUNT(*) as active 
                FROM students 
                WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = executeSimpleQuery($sql);
        return is_array($result) && isset($result[0]['active']) ? $result[0]['active'] : 0;
    } catch (Exception $e) {
        error_log("Error getting active students: " . $e->getMessage());
        return 0;
    }
}

function getRecentQuizzes($limit = 5) {
    try {
        $sql = "SELECT 
                    q.quiz_id,
                    q.title as quiz_name,
                    q.created_at,
                    (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.quiz_id) as participants,
                    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as questions
                FROM quizzes q
                ORDER BY q.created_at DESC
                LIMIT ?";
        
        $result = executeQuery($sql, [$limit]);
        return is_array($result) ? $result : [];
    } catch (Exception $e) {
        error_log("Error fetching recent quizzes: " . $e->getMessage());
        return [];
    }
}

// Get statistics
$totalQuizzes = getTotalQuizzes();
$completionRate = getCompletionRate();
$activeStudents = getActiveStudents();
$totalLevels = getTotalLevels();
$totalChallenges = getTotalChallenges();
$totalAchievements = getTotalAchievements();

// Set page title
$pageTitle = "Admin Dashboard - CodaQuest";
// New functions for total levels, challenges, achievements
function getTotalLevels() {
    try {
        $sql = "SELECT COUNT(*) as total FROM levels WHERE is_active = 1";
        $result = executeSimpleQuery($sql);
        return is_array($result) && isset($result[0]['total']) ? $result[0]['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total levels: " . $e->getMessage());
        return 0;
    }
}

function getTotalChallenges() {
    try {
        $sql = "SELECT COUNT(*) as total FROM challenges WHERE 1";
        $result = executeSimpleQuery($sql);
        return is_array($result) && isset($result[0]['total']) ? $result[0]['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total challenges: " . $e->getMessage());
        return 0;
    }
}

function getTotalAchievements() {
    try {
        $sql = "SELECT COUNT(*) as total FROM achievements WHERE is_active = 1";
        $result = executeSimpleQuery($sql);
        return is_array($result) && isset($result[0]['total']) ? $result[0]['total'] : 0;
    } catch (Exception $e) {
        error_log("Error getting total achievements: " . $e->getMessage());
        return 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for quizzes table */
        .quizzes-table tbody td {
            color: var(--text-color); /* Use theme variable instead of hardcoded color */
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">dashboard</i> Dashboard Overview</h1>
            </div>

            <div class="dashboard-content">
                <div class="welcome-message">
                    <i class="material-icons">emoji_emotions</i> Welcome, <?php echo htmlspecialchars($adminName); ?>! 
                </div>

                <!-- Levels list section removed -->

                <div class="stats-container">
                    <div class="stat-card">
                        <i class="material-icons stat-icon">assignment</i>
                        <div class="stat-title">Total Quizzes</div>
                        <div class="stat-value"><?php echo $totalQuizzes; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="material-icons stat-icon">layers</i>
                        <div class="stat-title">Total Levels</div>
                        <div class="stat-value"><?php echo $totalLevels; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="material-icons stat-icon">flag</i>
                        <div class="stat-title">Total Challenges</div>
                        <div class="stat-value"><?php echo $totalChallenges; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="material-icons stat-icon">emoji_events</i>
                        <div class="stat-title">Total Achievements</div>
                        <div class="stat-value"><?php echo $totalAchievements; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="material-icons stat-icon">pie_chart</i>
                        <div class="stat-title">Completion Rate</div>
                        <div class="stat-value"><?php echo $completionRate; ?>%</div>
                    </div>
                    <div class="stat-card">
                        <i class="material-icons stat-icon">people</i>
                        <div class="stat-title">Active Students</div>
                        <div class="stat-value"><?php echo $activeStudents; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(quizId) {
            if (confirm('Are you sure you want to delete this quiz? This action cannot be undone.')) {
                window.location.href = 'delete_quiz.php?id=' + quizId;
            }
        }
    </script>
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>
