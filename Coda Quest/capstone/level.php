<?php
// Start session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_error.log');

// Include student theme helper to ensure theme is applied correctly
require_once 'includes/student_theme_helper.php';

// Debug logging function
function debug_log($message, $data = null) {
    $log = "[DEBUG] " . $message;
    if ($data !== null) {
        $log .= ": " . print_r($data, true);
    }
    error_log($log);
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';

// Include database connection
require_once 'config/db_connect.php';

// Get level details with a simpler, more direct approach
$levelId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$level = null;

if ($levelId > 0) {
    // Direct query for level data - no need to check columns first
    $levelSql = "SELECT * FROM levels WHERE level_id = ? AND is_active = 1";
    $levelResult = executeQuery($levelSql, [$levelId]);
    
    if ($levelResult && is_array($levelResult) && count($levelResult) > 0) {
        $level = $levelResult[0];
        // Debug log for admin users
        if (isset($_SESSION['admin_id'])) {
            error_log("Level data loaded: " . print_r($level, true));
        }
    }
}

// Get quizzes for this level
$quizzes = [];
if (isset($level['level_id'])) {
    // Check the structure of the quizzes table
    $tableInfoSql = "SHOW COLUMNS FROM quizzes";
    $tableInfo = executeQuery($tableInfoSql);
    
    // Prepare column names based on table structure
    $columns = [];
    if ($tableInfo && is_array($tableInfo)) {
        foreach ($tableInfo as $column) {
            $columns[] = $column['Field'];
        }
    }
    
    // Build a query with the correct column names
    $quizColumns = !empty($columns) ? implode(', ', $columns) : '*';
    $quizzesSql = "SELECT $quizColumns FROM quizzes WHERE level_id = ? ORDER BY quiz_id ASC";
    
    $quizzesResult = executeQuery($quizzesSql, [$level['level_id']]);
    
    if ($quizzesResult && is_array($quizzesResult)) {
        $quizzes = $quizzesResult;
    }
    
    // Debug information
    $debug = [
        'level_id' => $level['level_id'],
        'quizzes_count' => count($quizzes),
        'query' => $quizzesSql,
        'columns' => $columns
    ];
}

// Get user progress for this level if logged in
$userProgress = null;
if ($isLoggedIn && isset($level['level_id'])) {
    $userId = $_SESSION['student_id'];
    // Check if the quiz_attempts table has a 'score' or 'points' column
    $checkColumnSql = "SHOW COLUMNS FROM quiz_attempts LIKE 'score'";
    $scoreColumnExists = executeQuery($checkColumnSql);
    
    $pointsField = $scoreColumnExists && count($scoreColumnExists) > 0 ? 'qa.score' : 'qa.points';
    error_log("Using $pointsField for quiz points calculation");
    
    $progressSql = "SELECT COUNT(qa.quiz_id) as completed_quizzes, SUM($pointsField) as total_points 
                   FROM quiz_attempts qa
                   JOIN quizzes q ON qa.quiz_id = q.quiz_id
                   WHERE qa.student_id = ? AND q.level_id = ? AND qa.is_completed = 1
                   GROUP BY qa.student_id";
    
    $progressResult = executeQuery($progressSql, [$userId, $level['level_id']]);
    
    if ($progressResult && count($progressResult) > 0) {
        $userProgress = [
            'is_completed' => ($progressResult[0]['completed_quizzes'] > 0),
            'points_earned' => $progressResult[0]['total_points'] ?? 0
        ];
    } else {
        // Default values if no progress found
        $userProgress = [
            'is_completed' => false,
            'points_earned' => 0
        ];
    }
}

// Get user quiz attempts if logged in
$quizAttempts = [];
if ($isLoggedIn && isset($level['level_id'])) {
    $userId = $_SESSION['student_id'];
    // Use the same points field as determined above
    $pointsField = isset($pointsField) ? $pointsField : (executeQuery("SHOW COLUMNS FROM quiz_attempts LIKE 'score'") ? 'qa.score' : 'qa.points');
    
    $attemptsSql = "SELECT qa.quiz_id, $pointsField as score, qa.is_completed, q.title as quiz_title
                   FROM quiz_attempts qa
                   JOIN quizzes q ON qa.quiz_id = q.quiz_id
                   WHERE qa.student_id = ? AND q.level_id = ?
                   ORDER BY qa.attempt_id DESC";
    
    $attemptsResult = executeQuery($attemptsSql, [$userId, $level['level_id']]);
    
    if ($attemptsResult && count($attemptsResult) > 0) {
        // Group attempts by quiz_id
        foreach ($attemptsResult as $attempt) {
            if (!isset($quizAttempts[$attempt['quiz_id']])) {
                $quizAttempts[$attempt['quiz_id']] = [];
            }
            $quizAttempts[$attempt['quiz_id']][] = $attempt;
        }
    }
}

// Set page title
$pageTitle = "Level: " . htmlspecialchars($level['level_name']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars($currentTheme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CodaQuest</title>
    <?php include_once 'common_styles.php'; ?>
    <style>
        body {
            background-color: var(--background-color);
            color: var(--text-color);
            font-family: var(--font-family);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
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
        /* Level page specific styles */
        .level-header {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid var(--primary-color);
            position: relative;
        }
        
        .level-header h1 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 1.8rem;
        }
        
        .level-description {
            margin-bottom: 20px;
            font-size: 1rem;
            line-height: 1.5;
        }
        
        .level-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .meta-item {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 8px 15px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .meta-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .meta-value {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .quiz-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .quiz-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            transition: transform 0.3s ease, border-color 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .quiz-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }
        
        .quiz-card-header {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .quiz-card-title {
            margin: 0;
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        .quiz-card-body {
            padding: 15px;
            flex-grow: 1;
        }
        
        .quiz-card-description {
            margin-bottom: 15px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .quiz-card-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .quiz-card-footer {
            padding: 15px;
            border-top: 2px solid var(--border-color);
            text-align: center;
        }
        
        .quiz-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .status-completed {
            background-color: #2e7d32;
            color: white;
        }
        
        .status-attempted {
            background-color: #f57c00;
            color: white;
        }
        
        .status-not-attempted {
            background-color: #616161;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 15px;
        }
        
        .empty-state-text {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 20px;
        }
        
        .back-button {
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Level notes and media styles */
        .level-content-section {
            margin-bottom: 30px;
        }
        
        .level-notes {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid var(--border-color);
        }
        
        .level-notes h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .level-notes-content {
            line-height: 1.6;
            font-size: 1rem;
        }
        
        .level-media {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 20px;
            border: 2px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .level-media h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .media-container {
            display: flex;
            justify-content: center;
            max-width: 100%;
            margin: 0 auto;
        }
        
        .media-container iframe,
        .media-container video,
        .media-container img {
            max-width: 100%;
            border-radius: 4px;
            border: 2px solid var(--border-color);
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
        <a href="dashboard.php" class="btn btn-outline back-button">
            <i class="material-icons">arrow_back</i> Back to Levels
        </a>
        
        <div class="level-header">
            <h1><?php echo htmlspecialchars($level['level_name']); ?></h1>
            <p class="level-description"><?php echo htmlspecialchars($level['description']); ?></p>
            
            <!-- Debug information -->
            <?php if (isset($_SESSION['admin_id'])): ?>
            <div style="background: rgba(0,0,0,0.1); padding: 10px; margin: 10px 0; border-radius: 4px;">
                <p>Debug Info:</p>
                <ul>
                    <li>Has Notes Column: <?php echo !empty($level['notes']) ? 'Yes' : 'No'; ?></li>
                    <li>Has Notes Media Column: <?php echo !empty($level['notes_media']) ? 'Yes' : 'No'; ?></li>
                    <li>Notes Content: <?php echo !empty($level['notes']) ? 'Present' : 'Empty'; ?></li>
                    <li>Notes Media Path: <?php echo !empty($level['notes_media']) ? htmlspecialchars($level['notes_media']) : 'Empty'; ?></li>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="level-meta">
                <div class="meta-item">
                    <i class="material-icons">stairs</i>
                    <span class="meta-label">Level:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($level['level_order']); ?></span>
                </div>
                
                <div class="meta-item">
                    <i class="material-icons">quiz</i>
                    <span class="meta-label">Quizzes:</span>
                    <span class="meta-value"><?php echo count($quizzes); ?></span>
                </div>
                
                <?php if ($isLoggedIn && $userProgress): ?>
                <div class="meta-item">
                    <i class="material-icons">stars</i>
                    <span class="meta-label">Points Earned:</span>
                    <span class="meta-value"><?php echo htmlspecialchars($userProgress['points_earned']); ?></span>
                </div>
                
                <?php if ($userProgress['is_completed']): ?>
                <div class="meta-item">
                    <i class="material-icons">check_circle</i>
                    <span class="meta-label">Status:</span>
                    <span class="meta-value">Completed</span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Display level notes if available -->
        <?php if (isset($level['notes']) && !empty($level['notes'])): ?>
        <div class="level-content-section">
            <div class="level-notes">
                <h3><i class="material-icons">notes</i> Learning Notes</h3>
                <div class="level-notes-content">
                    <?php echo nl2br(htmlspecialchars($level['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Display media content if available -->
        <?php if (isset($level['notes_media']) && !empty($level['notes_media'])): ?>
        <div class="level-content-section">
            <div class="level-media">
                <h3><i class="material-icons">video_library</i> Learning Resources</h3>
                <div class="media-container">
                    <?php 
                    $mediaPath = $level['notes_media'];
                    
                    // Handle image files
                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $mediaPath)) {
                        echo '<img src="' . htmlspecialchars($mediaPath) . '" alt="Learning resource" style="max-width: 100%; height: auto;">';
                    } 
                    // Handle video files
                    elseif (preg_match('/\.(mp4|webm|ogg)$/i', $mediaPath)) {
                        echo '<video controls width="720"><source src="' . htmlspecialchars($mediaPath) . '" type="video/mp4">Your browser does not support the video tag.</video>';
                    }
                    // Handle YouTube videos
                    elseif (strpos($mediaPath, 'youtube.com') !== false || strpos($mediaPath, 'youtu.be') !== false) {
                        preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $mediaPath, $matches);
                        $youtubeId = $matches[1] ?? '';
                        if ($youtubeId) {
                            echo '<iframe width="720" height="405" src="https://www.youtube.com/embed/' . $youtubeId . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                        }
                    }
                    // Generic link fallback
                    else {
                        echo '<a href="' . htmlspecialchars($mediaPath) . '" target="_blank" class="btn btn-primary">View Resource</a>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quizzes section -->
        <div class="level-notes">
            <h3><i class="material-icons">quiz</i> Available Quizzes</h3>
        </div>
        
        <!-- Quiz cards grid -->    
        <?php if (count($quizzes) > 0): ?>
        <div class="quiz-grid">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="quiz-card">
                    <div class="quiz-card-header">
                        <h3 class="quiz-card-title"><?php echo htmlspecialchars(isset($quiz['quiz_title']) ? $quiz['quiz_title'] : (isset($quiz['title']) ? $quiz['title'] : 'Quiz')); ?></h3>
                    </div>
                    <div class="quiz-card-body">
                        <p class="quiz-card-description"><?php echo htmlspecialchars(isset($quiz['description']) ? $quiz['description'] : 'No description available.'); ?></p>
                        <div class="quiz-card-meta">
                            <div>
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">timer</i>
                                <div class="quiz-time-limit">
                                    <i class="fas fa-clock"></i> Time Limit: 
                                    <?php 
                                    // Always show a time limit - default 30 min if not set, null, zero, or empty
                                    $timeLimit = (isset($quiz['time_limit']) && $quiz['time_limit'] !== null && !empty($quiz['time_limit']) && $quiz['time_limit'] > 0) 
                                        ? intval($quiz['time_limit']) 
                                        : 30;
                                    echo "<strong>$timeLimit min</strong>";
                                    ?>                                    
                                </div>
                            </div>
                            <div>
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">quiz</i>
                                Quiz
                            </div>
                        </div>
                        
                        <?php if ($isLoggedIn && isset($quizAttempts[$quiz['quiz_id']])): ?>
                            <div class="quiz-status status-attempted" style="background-color: var(--primary-color); color: var(--text-color); padding: 5px 10px; border-radius: 4px; margin-bottom: 10px; font-weight: bold;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">history</i> 
                                Quiz Attempted
                            </div>
                        <?php else: ?>
                            <div class="quiz-status status-not-attempted">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">play_circle</i> Not Attempted
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="quiz-card-footer">
                        <?php if ($isLoggedIn && isset($quizAttempts[$quiz['quiz_id']])): ?>
                            <!-- Quiz has been attempted - only show retry option -->
                            <a href="take_quiz.php?id=<?php echo isset($quiz['quiz_id']) ? $quiz['quiz_id'] : $quiz['id']; ?>" class="btn btn-primary" style="background-color: #FF5722;">
                                <i class="material-icons">refresh</i> Retry Quiz
                            </a>
                        <?php else: ?>
                            <!-- Quiz has not been attempted - show take quiz option -->
                            <a href="take_quiz.php?id=<?php echo isset($quiz['quiz_id']) ? $quiz['quiz_id'] : $quiz['id']; ?>" class="btn btn-primary">
                                <i class="material-icons">play_arrow</i> Take Quiz
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="material-icons">quiz</i>
            </div>
            <div class="empty-state-text">No quizzes available for this level yet.</div>
            <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
    <script src="matrix-bg.js"></script>
</body>
</html>
