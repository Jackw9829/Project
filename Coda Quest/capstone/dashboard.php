<?php
// Include authentication check
require_once 'includes/auth_check.php';

// Include student theme helper to ensure theme is applied correctly
require_once 'includes/student_theme_helper.php';

// Start session is already handled in auth_check.php

// User is logged in if we reach this point
$isLoggedIn = true;
$username = $_SESSION['username'];

// Include database connection
require_once 'config/db_connect.php';

// (learning_paths removed from codebase as per new requirements)

// Fetch levels from database
function getLevels() {
    $sql = "SELECT level_id, level_name, description, level_order, is_active 
            FROM levels 
            WHERE is_active = 1 
            ORDER BY level_order ASC";
    
    $result = executeSimpleQuery($sql);
    $levels = [];
    
    if ($result && count($result) > 0) {
        $levels = $result;
    }
    
    return $levels;
}

// Get quizzes for a level
function getQuizzesForLevel($levelId) {
    $sql = "SELECT quiz_id, quiz_title, description 
            FROM quizzes 
            WHERE level_id = ? 
            ORDER BY quiz_id ASC";
    
    $result = executeQuery($sql, [$levelId]);
    $quizzes = [];
    
    if ($result && count($result) > 0) {
        $quizzes = $result;
    }
    
    return $quizzes;
}

// Fetch user progress if logged in
function getUserProgress($studentId) {
    $sql = "SELECT lp.path_id, lp.path_name, up.progress_percentage 
            FROM learning_paths lp
            LEFT JOIN user_progress up ON lp.path_id = up.path_id AND up.student_id = ?
            ORDER BY lp.path_id ASC";
    
    $result = executeQuery($sql, [$studentId]);
    $progress = [];
    
    if ($result && count($result) > 0) {
        $progress = $result;
    }
    
    return $progress;
}

// (learning_paths removed from codebase as per new requirements)

// Get levels
$levels = getLevels();

// If no levels and user is admin, create a default level
if (empty($levels) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && isset($_GET['create_default'])) {
    $defaultLevelData = [
        'level_name' => 'Level 1: Introduction to Python',
        'description' => 'Get started with Python fundamentals including variables, data types, and basic operations.',
        'level_order' => 1,
        'is_active' => 1
    ];
    
    $levelId = insertData('levels', $defaultLevelData);
    
    if ($levelId) {
        // Reload the page to show the new level
        header("Location: dashboard.php");
        exit();
    }
}

// Get user completed levels if logged in
$userCompletedLevels = [];
if ($isLoggedIn) {
    // Get all completed levels for the user
    if (isset($_SESSION['student_id'])) {
        $sql = "SELECT DISTINCT q.level_id 
                FROM quiz_attempts qa 
                JOIN quizzes q ON qa.quiz_id = q.quiz_id 
                WHERE qa.student_id = ? AND qa.is_completed = 1";
        $result = executeQuery($sql, [$_SESSION['student_id']]);
    } else {
        $result = [];
    }
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $userCompletedLevels[$row['level_id']] = true;
        }
    }
}

// Logout is now handled in header.php

// Set page title
$pageTitle = "CodaQuest - Learn to Code";
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($currentTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodaQuest - Python Learning Platform</title>
    <?php include_once 'common_styles.php'; ?>
    <style>
        /* Main Content Styles */
        body {
            min-height: 100vh;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        
        .main-content {
            flex: 1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .section-title {
            font-size: 2.2rem;
            color: var(--primary-color);
            margin-bottom: 30px;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .section-title::before,
        .section-title::after {
            content: '';
            height: 4px;
            background-color: var(--border-color);
            flex: 1;
            margin: 0 15px;
        }

        /* Vertical Timeline Layout */
        .timeline {
            position: relative;
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
        }

        .timeline::after {
            content: '';
            position: absolute;
            width: 6px;
            background-color: var(--primary-color);
            top: 0;
            bottom: 0;
            left: 50%;
            margin-left: -3px;
        }

        .timeline-item {
            padding: 10px 40px;
            position: relative;
            width: 50%;
            box-sizing: border-box;
            margin-bottom: 30px;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-item:nth-child(odd)::after {
            right: -14px;
        }

        .timeline-item:nth-child(even)::after {
            left: -14px;
        }

        .timeline-item:nth-child(odd) .timeline-content::after {
            content: '';
            position: absolute;
            border-width: 10px 0 10px 10px;
            border-style: solid;
            border-color: transparent transparent transparent var(--card-bg);
            right: -10px;
            top: 20px;
        }

        .timeline-item:nth-child(even) .timeline-content::after {
            content: '';
            position: absolute;
            border-width: 10px 10px 10px 0;
            border-style: solid;
            border-color: transparent var(--card-bg) transparent transparent;
            left: -10px;
            top: 20px;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: var(--card-bg);
            border: 4px solid var(--primary-color);
            top: 20px;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-content {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: var(--shadow);
            position: relative;
            width: 100%;
            max-width: 100%;
            min-height: 220px;
            transition: var(--transition);
            border: 4px solid var(--border-color);
        }

        .timeline-content:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 0 rgba(0, 0, 0, 0.5);
        }

        .timeline-content .icon {
            color: var(--primary-color);
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
            text-decoration: none;
        }

        .timeline-date {
            display: inline-block;
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            font-size: 0.8rem;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .timeline-description {
            color: var(--text-color);
            font-size: 0.8rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .timeline-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: var(--card-bg);
            color: var(--primary-color);
            font-size: 0.7rem;
            margin-top: 10px;
            margin-right: 5px;
            border: 2px solid var(--primary-color);
            text-transform: uppercase;
        }

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

        /* Footer Styles to match includes/footer.php */
        .site-footer {
            background-color: var(--header-bg);
            color: var(--text-color);
            padding: 30px 0;
            margin-top: auto;
            border-top: 4px solid var(--border-color);
        }
        
        .shimmer-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .pixel-border {
            display: none;
            height: 0;
            background: none;
            margin-bottom: 10px;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(122, 122, 122, 0.5);
            backdrop-filter: blur(3px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Text Box Popup */
        .text-box-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            max-width: 600px;
            width: 90%;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            border-left: 4px solid var(--primary-color);
        }

        .text-box-popup.show {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .text-box-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
            transition: var(--transition);
        }

        .text-box-close:hover {
            color: var(--primary-color);
            transform: rotate(90deg);
        }
        
        .text-box-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .timeline::after {
                left: 31px;
            }
            
            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 25px;
                left: 0 !important;
            }
            
            .timeline-item::after {
                left: 21px;
            }
            
            .timeline-content {
                padding: 25px;
                min-height: 180px;
            }
            
            .timeline-content::after {
                display: none;
            }
            
            .right {
                left: 0;
            }

            .footer-content {
                flex-direction: column;
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 15px;
                flex-wrap: wrap;
            }

            .user-controls {
                margin-top: 10px;
                width: 100%;
                justify-content: space-between;
            }
            
            .timeline-item {
                padding-left: 50px;
                padding-right: 15px;
            }
            
            .timeline::after {
                left: 21px;
            }
            
            .timeline-item::after {
                left: 11px;
                width: 15px;
                height: 15px;
            }
            
            .timeline-content {
                padding: 20px;
                min-height: 150px;
            }
            
            .timeline-date {
                padding: 5px 10px;
                font-size: 0.7rem;
                margin-bottom: 10px;
            }
        }

        .popup-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .close-btn {
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-btn:hover {
            color: var(--primary-color);
        }

        /* Stage Popup */
        .stage-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            width: 90%;
            max-width: 800px;
            background-color: var(--card-bg);
            color: var(--text-color);
            border-radius: 8px;
            overflow: hidden;
            z-index: 1001;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            border: 2px solid var(--primary-color);
        }

        .popup-content {
            padding: 20px;
        }
        
        .quiz-list {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .quiz-item {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid var(--primary-color);
        }
        
        .quiz-title {
            font-weight: bold;
            font-size: 1.1rem;
            color: var(--primary-color);
            display: block;
            margin-bottom: 5px;
        }
        
        .quiz-description {
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        a {
            text-decoration: none;
        }

        .btn {
            text-decoration: none;
        }

        .icon {
            text-decoration: none;
            vertical-align: middle;
        }

        .timeline-content a, 
        .timeline-content button {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
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
            <div class="timeline">
                <?php if (count($levels) > 0): ?>
                    <?php
                    $locked = false;
                    foreach ($levels as $index => $level):
                        // The first level is always unlocked
                        if ($index === 0) {
                            $locked = false;
                        } else {
                            // Check if previous level was completed
                            $prevLevelId = $levels[$index-1]['level_id'];
                            if (!isset($userCompletedLevels[$prevLevelId])) {
                                // Previous level not completed, lock this and all subsequent levels
                                $locked = true;
                            }
                        }
                ?>
                    <div class="timeline-item <?php echo $index % 2 == 0 ? 'left' : 'right'; ?>">
                        <div class="timeline-content">
                            <p class="timeline-date">Level <?php echo htmlspecialchars($level['level_order']); ?></p>
                            <h3><?php 
                                // Extract just the title without any level number
                                $title = preg_replace('/^(Level\s*\d+:?\s*|\d+:?\s*)/', '', $level['level_name']);
                                echo htmlspecialchars($title); 
                            ?></h3>
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="level.php?id=<?php echo $level['level_id']; ?>" class="btn btn-primary">
                                    <i class="material-icons">play_arrow</i> Start Learning
                                </a>
                            </div>
                        </div>  
                    </div>
                    <?php endforeach; ?>
<?php else: ?>
    <div class="timeline-item">
        <div class="timeline-content">
            <h3 style="text-align: center; color: var(--primary-color);">No levels available</h3>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div style="text-align: center; margin-top: 20px;">
                <p>Admin info: SQL query for levels returned <?php echo count($levels); ?> results.</p>
                <p>Please add levels from the admin dashboard or check the database connection.</p>
                <div style="display: flex; justify-content: center; gap: 10px; margin-top: 15px;">
                    <a href="admin/managequiz.php" class="btn btn-secondary">
                        <i class="material-icons">add_circle</i> Manage Levels
                    </a>
                    <a href="dashboard.php?create_default=1" class="btn btn-primary">
                        <i class="material-icons">school</i> Create Default Level
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
            </div>
        </div>
    </div> <!-- End of main-content div -->
    
    <?php include 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
</body>
</html>