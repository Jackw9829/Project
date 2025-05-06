<?php
// Start session
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$userId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null);

// Include database connection
require_once 'config/db_connect.php';

// Get user achievements
function getUserAchievements($userId = null) {
    // Get all achievements from database
    $achievementsSql = "SELECT * FROM achievements WHERE is_active = 1 ORDER BY achievement_id";
    $achievementsResult = executeQuery($achievementsSql);
    
    // Initialize achievements array
    $achievements = [];
    
    // Process achievements from database
    if ($achievementsResult && count($achievementsResult) > 0) {
        foreach ($achievementsResult as $achievement) {
            $achievements[$achievement['achievement_id']] = [
                'id' => $achievement['achievement_id'],
                'name' => $achievement['title'],
                'description' => $achievement['description'],
                'points' => $achievement['points'],
                'icon' => $achievement['icon'],
                'achievement_type' => $achievement['achievement_type'],
                'requirement_value' => $achievement['requirement_value'],
                'status' => 'locked',
                'progress' => 0,
                'total' => 100
            ];
        }
    }
    
    // If user is logged in, check their achievements
    if ($userId && !empty($achievements)) {
        // Check for completed achievements
        
        // Example: First Steps - Complete your first lesson (all quizzes in a level)
        $levelCompletionSql = "SELECT l.level_id, l.level_name, 
                               COUNT(q.quiz_id) as total_quizzes,
                               COUNT(DISTINCT qa.quiz_id) as completed_quizzes
                               FROM levels l
                               JOIN quizzes q ON q.level_id = l.level_id
                               LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id AND qa.student_id = ? AND qa.is_completed = 1
                               GROUP BY l.level_id
                               HAVING COUNT(q.quiz_id) > 0 AND COUNT(DISTINCT qa.quiz_id) = COUNT(q.quiz_id)";
        $levelResult = executeQuery($levelCompletionSql, [$userId]);
        
        if ($levelResult && count($levelResult) > 0) {
            // User has completed at least one level
            // Find achievement with type 'completion' for levels
            foreach ($achievements as $id => $achievement) {
                if ($achievement['achievement_type'] === 'completion' && strpos(strtolower($achievement['name']), 'first steps') !== false) {
                    $achievements[$id]['status'] = 'completed';
                    $achievements[$id]['progress'] = 100;
                    break;
                }
            }
        } else {
            // Check for partial progress in levels
            $levelProgressSql = "SELECT l.level_id, l.level_name, 
                                COUNT(q.quiz_id) as total_quizzes,
                                COUNT(DISTINCT qa.quiz_id) as completed_quizzes
                                FROM levels l
                                JOIN quizzes q ON q.level_id = l.level_id
                                LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id AND qa.student_id = ? AND qa.is_completed = 1
                                GROUP BY l.level_id
                                HAVING COUNT(q.quiz_id) > 0
                                ORDER BY (COUNT(DISTINCT qa.quiz_id) / COUNT(q.quiz_id)) DESC
                                LIMIT 1";
            $progressResult = executeQuery($levelProgressSql, [$userId]);
            
            if ($progressResult && count($progressResult) > 0 && isset($progressResult[0]['total_quizzes']) && $progressResult[0]['total_quizzes'] > 0) {
                $total = $progressResult[0]['total_quizzes'];
                $completed = $progressResult[0]['completed_quizzes'];
                $progress = min(100, ($completed / $total) * 100);
                
                // Find achievement with type 'completion' for levels
                foreach ($achievements as $id => $achievement) {
                    if ($achievement['achievement_type'] === 'completion' && strpos(strtolower($achievement['name']), 'first steps') !== false) {
                        $achievements[$id]['progress'] = $progress;
                        
                        if ($progress > 0 && $progress < 100) {
                            $achievements[$id]['status'] = 'in_progress';
                        }
                        break;
                    }
                }
            }
        }
        
        // Example: Quick Learner - Complete your first quiz
        $quizCompletedSql = "SELECT COUNT(*) as count FROM quiz_attempts WHERE student_id = ? AND is_completed = 1";
        $quizResult = executeQuery($quizCompletedSql, [$userId]);
        if ($quizResult && count($quizResult) > 0 && $quizResult[0]['count'] > 0) {
            // Find achievement with type 'completion' for quizzes
            foreach ($achievements as $id => $achievement) {
                if ($achievement['achievement_type'] === 'completion' && strpos(strtolower($achievement['name']), 'quick learner') !== false) {
                    $achievements[$id]['status'] = 'completed';
                    $achievements[$id]['progress'] = 100;
                    break;
                }
            }
        } else {
            // Show progress if they've started but not completed quizzes
            $quizProgressSql = "SELECT COUNT(*) as count FROM quiz_attempts WHERE student_id = ?";
            $progressResult = executeQuery($quizProgressSql, [$userId]);
            if ($progressResult && count($progressResult) > 0 && $progressResult[0]['count'] > 0) {
                // Find achievement with type 'completion' for quizzes
                foreach ($achievements as $id => $achievement) {
                    if ($achievement['achievement_type'] === 'completion' && strpos(strtolower($achievement['name']), 'quick learner') !== false) {
                        $achievements[$id]['status'] = 'in_progress';
                        $achievements[$id]['progress'] = 50; // Started but not completed
                        break;
                    }
                }
            }
        }
        
        // Example: Python Novice - Completed a challenge
        $challengeCompletedSql = "SELECT COUNT(*) as count FROM challenge_attempts WHERE student_id = ? AND is_completed = 1";
        $challengeResult = executeQuery($challengeCompletedSql, [$userId]);
        if ($challengeResult && count($challengeResult) > 0 && $challengeResult[0]['count'] > 0) {
            // Find achievement with type 'skill' for challenges
            foreach ($achievements as $id => $achievement) {
                if ($achievement['achievement_type'] === 'skill' && strpos(strtolower($achievement['name']), 'python novice') !== false) {
                    $achievements[$id]['status'] = 'completed';
                    $achievements[$id]['progress'] = 100;
                    break;
                }
            }
        } else {
            // Show progress if they've started but not completed challenges
            $challengeProgressSql = "SELECT COUNT(*) as count FROM challenge_attempts WHERE student_id = ?";
            $progressResult = executeQuery($challengeProgressSql, [$userId]);
            if ($progressResult && count($progressResult) > 0 && $progressResult[0]['count'] > 0) {
                // Find achievement with type 'skill' for challenges
                foreach ($achievements as $id => $achievement) {
                    if ($achievement['achievement_type'] === 'skill' && strpos(strtolower($achievement['name']), 'python novice') !== false) {
                        $achievements[$id]['status'] = 'in_progress';
                        $achievements[$id]['progress'] = 50; // Started but not completed
                        break;
                    }
                }
            }
        }
        
        // Example: Perfect Score - Get 100% on any quiz (all correct answers in one quiz)
        $perfectScoreSql = "SELECT 
                            qa.attempt_id,
                            COUNT(qq.question_id) as total_questions,
                            SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                        FROM quiz_attempts qat
                        JOIN quiz_questions qq ON qat.quiz_id = qq.quiz_id
                        LEFT JOIN quiz_answers qa ON qat.attempt_id = qa.attempt_id AND qa.question_id = qq.question_id
                        WHERE qat.student_id = ? AND qat.is_completed = 1
                        GROUP BY qa.attempt_id
                        HAVING COUNT(qq.question_id) > 0 
                        AND SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) = COUNT(qq.question_id)";
        $perfectResult = executeQuery($perfectScoreSql, [$userId]);
        if ($perfectResult && count($perfectResult) > 0) {
            // Find achievement with type 'skill' for perfect score
            foreach ($achievements as $id => $achievement) {
                if ($achievement['achievement_type'] === 'skill' && strpos(strtolower($achievement['name']), 'perfect score') !== false) {
                    $achievements[$id]['status'] = 'completed';
                    $achievements[$id]['progress'] = 100;
                    break;
                }
            }
        } else {
            // Show best performance so far
            $bestScoreSql = "SELECT 
                            qa.attempt_id,
                            COUNT(qq.question_id) as total_questions,
                            SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
                        FROM quiz_attempts qat
                        JOIN quiz_questions qq ON qat.quiz_id = qq.quiz_id
                        LEFT JOIN quiz_answers qa ON qat.attempt_id = qa.attempt_id AND qa.question_id = qq.question_id
                        WHERE qat.student_id = ? AND qat.is_completed = 1
                        GROUP BY qa.attempt_id
                        ORDER BY (SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) * 100 / COUNT(qq.question_id)) DESC
                        LIMIT 1";
            $bestResult = executeQuery($bestScoreSql, [$userId]);
            if ($bestResult && count($bestResult) > 0 && $bestResult[0]['total_questions'] > 0) {
                $totalQuestions = $bestResult[0]['total_questions'];
                $correctAnswers = $bestResult[0]['correct_answers'];
                $progress = ($correctAnswers / $totalQuestions) * 100;
                
                // Find achievement with type 'skill' for perfect score
                foreach ($achievements as $id => $achievement) {
                    if ($achievement['achievement_type'] === 'skill' && strpos(strtolower($achievement['name']), 'perfect score') !== false) {
                        $achievements[$id]['progress'] = $progress;
                        if ($progress > 0) {
                            $achievements[$id]['status'] = 'in_progress';
                        }
                        break;
                    }
                }
            }
        }
        
        // Example: Consistent Learner - Login for 5 consecutive days
        // Calculate login streak based on user activity
        $userActivitySql = "SELECT date_registered, last_login, DATEDIFF(NOW(), date_registered) as days_registered 
                           FROM students WHERE student_id = ?";
        $userActivityResult = executeQuery($userActivitySql, [$userId]);
        
        if ($userActivityResult && count($userActivityResult) > 0) {
            // Calculate a login streak based on days registered and last login
            $daysRegistered = $userActivityResult[0]['days_registered'] ?? 0;
            $lastLogin = $userActivityResult[0]['last_login'] ?? null;
            
            // If user logged in recently (within last 2 days), consider them active
            $isRecentlyActive = false;
            if ($lastLogin) {
                $lastLoginDate = new DateTime($lastLogin);
                $now = new DateTime();
                $daysSinceLastLogin = $now->diff($lastLoginDate)->days;
                $isRecentlyActive = ($daysSinceLastLogin <= 2); // Consider active if logged in within 2 days
            }
            
            // Calculate a login streak value (simplified version)
            // For this example, we'll use days registered as a base and adjust based on activity
            $loginStreak = min(5, max(1, intval($daysRegistered / 7))); // 1 streak point per week registered
            if ($isRecentlyActive) {
                $loginStreak++; // Bonus for recent activity
            }
            
            // Log the calculation for debugging
            error_log("Login streak calculation for user $userId: Days registered=$daysRegistered, Last login=$lastLogin, Calculated streak=$loginStreak");
            
            // Find achievement with type 'progress' for login streak
            foreach ($achievements as $id => $achievement) {
                if ($achievement['achievement_type'] === 'progress' && strpos(strtolower($achievement['name']), 'consistent learner') !== false) {
                    $progress = min(100, ($loginStreak / $achievement['requirement_value']) * 100);
                    $achievements[$id]['progress'] = $progress;
                    
                    if ($progress >= 100) {
                        $achievements[$id]['status'] = 'completed';
                    } else {
                        $achievements[$id]['status'] = 'in_progress';
                    }
                }
            }
        }
    }
    
    return array_values($achievements);
}

// Get achievements data
$achievements = getUserAchievements($userId);

// Logout is now handled in header.php

// Set page title
$pageTitle = "CodaQuest - Achievements";
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
    <link rel="stylesheet" href="/capstone/common.css">
    <style>
        /* Use the same variables as the rest of the site for consistency */
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --accent-color: #292f36;
            --text-color: #ffffff;
            --background-color: #121212;
            --card-bg: #1e1e1e;
            --border-color: #333;
            --border-radius: 4px;
            --shadow: 0 4px 8px rgba(0, 0, 0, 0.5);
            --transition: all 0.3s ease;
            --font-family: "Press Start 2P", "Tiny5", monospace;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: var(--font-family);
            font-weight: 400;
            font-style: normal;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--background-color);
            color: var(--text-color);
            padding: 0;
            margin: 0;
            min-height: 100vh;
            line-height: 1.6;
            background-image: url('assets/grid-bg.png');
            background-size: 50px 50px;
            background-repeat: repeat;
        }

        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Custom header styles for achievements page are removed as they're now handled by the includes/header.php */

        /* Custom logo and header elements removed as they're handled by includes/header.php */

        /* Custom profile elements removed as they're handled by includes/header.php */

        /* Global h1 styles removed as they're now handled by the page-title class */

        .page-header {
            background-color: var(--card-bg);
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            text-align: center;
            border: 4px solid var(--border-color);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }
        
        .page-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-transform: uppercase;
            text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
            font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .page-description {
            color: var(--text-color);
            max-width: 800px;
            margin: 0 auto;
            font-family: inherit;
        }

        /* Achievements Styles */
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 2rem;
        }

        .achievement-card {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .achievement-card {
            background-color: transparent;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 4px solid var(--border-color);
            padding: 20px;
            position: relative;
        }

        .achievement-icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: block;
            text-shadow: 2px 2px 0 var(--accent-color);
        }
        
        .achievement-locked .achievement-icon {
            color: #888;
        }

        .achievement-title {
            font-size: 1.2rem;
            margin: 0 0 10px 0;
            color: var(--text-color);
            font-family: "Press Start 2P", monospace;
            letter-spacing: 1px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .achievement-description {
            font-size: 0.9rem;
            color: #444;
            margin-bottom: 10px;
        }

        .achievement-progress {
            height: 8px;
            background-color: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .achievement-progress-bar {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: var(--border-radius);
            width: 0;
            transition: width 1s ease;
            box-shadow: 0 0 8px var(--primary-color);
        }

        .achievement-status {
            font-size: 0.8rem;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        .achievement-locked {
            filter: grayscale(1);
            opacity: 0.7;
        }

        .achievement-locked::before {
            content: '🔒';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
        }

        .achievement-completed {
            border: 2px solid var(--primary-color);
        }

        .achievement-completed::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.2rem;
            background-color: var(--primary-color);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Leaderboard Styles */
        .leaderboard-container {
            background-color: transparent;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            max-width: 1000px;
            margin: 0 auto;
            border: 4px solid var(--border-color);
        }

        .leaderboard-header {
            display: grid;
            grid-template-columns: 60px 1fr 100px 100px;
            padding: 15px;
            font-weight: 700;
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .leaderboard-header > div {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .leaderboard-item {
            display: grid;
            grid-template-columns: 60px 1fr 100px 100px;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: var(--transition);
        }

        .leaderboard-item:last-child {
            border-bottom: none;
        }

        .leaderboard-item:hover {
            background-color: rgba(0, 184, 212, 0.05);
            transform: translateX(5px);
        }

        .rank {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .top-rank {
            color: white;
            position: relative;
        }

        .rank-1 {
            background: linear-gradient(45deg, #FFD700, #FFA500);
        }

        .rank-1::after {
            content: "";
            position: absolute;
            top: -10px;
            right: -10px;
            width: 24px;
            height: 24px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" fill="gold"><path d="M0 0h24v24H0z" fill="none"/><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM7 10.82C5.84 10.4 5 9.3 5 8V7h2v3.82zM19 8c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>');
            background-repeat: no-repeat;
            background-size: contain;
        }

        .rank-2 {
            background: linear-gradient(45deg, #C0C0C0, #A9A9A9);
        }

        .rank-2::after {
            content: "";
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" fill="silver"><path d="M0 0h24v24H0z" fill="none"/><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM7 10.82C5.84 10.4 5 9.3 5 8V7h2v3.82zM19 8c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>');
            background-repeat: no-repeat;
            background-size: contain;
        }

        .rank-3 {
            background: linear-gradient(45deg, #CD7F32, #8B4513);
        }

        .rank-3::after {
            content: "";
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" fill="%23CD7F32"><path d="M0 0h24v24H0z" fill="none"/><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V19H7v2h10v-2h-4v-3.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM7 10.82C5.84 10.4 5 9.3 5 8V7h2v3.82zM19 8c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>');
            background-repeat: no-repeat;
            background-size: contain;
        }

        .player {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .player-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ddd;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }

        .player-name {
            font-weight: 600;
        }

        .current-user {
            background-color: rgba(0, 184, 212, 0.05);
            border-left: 4px solid var(--primary-color);
        }

        .score, .achievements-count {
            font-weight: 600;
        }

        /* Footer styles are now handled by includes/footer.php */

        /* Additional footer styles removed as they're handled by includes/footer.php */

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            text-align: center;
            margin-top: 30px;
            border: 4px solid var(--border-color);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            text-shadow: 2px 2px 0 var(--accent-color);
        }
        
        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: var(--text-color);
            font-family: "Press Start 2P", monospace;
        }
        
        .empty-state p {
            margin: 0;
            color: var(--text-color);
            max-width: 400px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="material-icons">emoji_events</i> Achievements</h1>
            <p class="page-description">Complete challenges and quizzes to unlock achievements and earn points</p>
        </div>

        <div id="achievements">
            <div class="achievements-grid">
                <?php if (count($achievements) > 0): ?>
                    <?php foreach ($achievements as $achievement): ?>
                        <div class="achievement-card <?php 
                            echo $achievement['status'] === 'completed' ? 'achievement-completed' : 
                                ($achievement['status'] === 'locked' ? 'achievement-locked' : ''); 
                        ?>" data-id="<?php echo htmlspecialchars($achievement['id']); ?>">
                            <div class="achievement-icon">
                                <?php
                                $icon = $achievement['icon'] ?? 'stars';
                                // Check if the icon is in the material-icons: format and extract just the icon name
                                if (strpos($icon, 'material-icons:') === 0) {
                                    $icon = str_replace('material-icons:', '', $icon);
                                }
                                ?>
                                <i class="material-icons"><?php echo $icon; ?></i>
                            </div>
                            <h3 class="achievement-title"><?php echo htmlspecialchars($achievement['name']); ?></h3>
                            <p class="achievement-description"><?php echo htmlspecialchars($achievement['description']); ?></p>
                            <div class="achievement-progress">
                                <div class="achievement-progress-bar" style="width: <?php echo $achievement['progress']; ?>%"></div>
                            </div>
                            <div class="achievement-status">
                                <span>
                                    <?php 
                                    if ($achievement['status'] === 'completed') {
                                        echo 'Completed';
                                    } elseif ($achievement['status'] === 'in_progress') {
                                        echo $achievement['progress'] . '% complete';
                                    } else {
                                        echo 'Locked';
                                    }
                                    ?>
                                </span>
                                <span>+<?php echo $achievement['points']; ?> points</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="material-icons">emoji_events</i>
                        <h3>No achievements available</h3>
                        <p>Achievements will be added soon. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </div>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        // Add animation to achievement cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.achievement-card');
            
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
                
                // Animate progress bars
                const progressBar = card.querySelector('.achievement-progress-bar');
                if (progressBar) {
                    const width = progressBar.style.width;
                    progressBar.style.width = '0';
                    setTimeout(() => {
                        progressBar.style.width = width;
                    }, 500 + (100 * index));
                }
            });
        });
    </script>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
