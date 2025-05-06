<?php
// Include authentication check to redirect non-logged-in users
require_once 'includes/auth_check.php';

// Session is already started in auth_check.php
// No need to call session_start() again

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';

// Include database connection
require_once 'config/db_connect.php';

// Set page title
$pageTitle = "Your Quiz Attempts - CodaQuest";

// Fetch quizzes from database
function getQuizzes() {
    $sql = "SELECT q.quiz_id, q.title, q.description, q.time_limit, q.created_at, 
                   COUNT(qq.question_id) as question_count
            FROM quizzes q
            LEFT JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id
            GROUP BY q.quiz_id
            ORDER BY q.created_at DESC";
    
    $result = executeSimpleQuery($sql);
    $quizzes = [];
    
    if ($result && count($result) > 0) {
        $quizzes = $result;
    }
    
    return $quizzes;
}

// Get user's completed quiz attempts if logged in
function getUserQuizAttempts($studentId) {
    $sql = "SELECT qa.quiz_id, qa.attempt_id, qa.points, 
                   q.title, COUNT(qq.question_id) as total_questions,
                   qa.start_time, qa.end_time
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.quiz_id
            LEFT JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id
            WHERE qa.student_id = ? AND qa.is_completed = 1
            GROUP BY qa.attempt_id
            ORDER BY qa.end_time DESC";
    
    $result = executeQuery($sql, [$studentId]);
    $attempts = [];
    
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            // Calculate percentage score based on max possible score
            $maxScoreSql = "SELECT SUM(points) as max_score FROM quiz_questions WHERE quiz_id = ?";
            $maxScoreResult = executeQuery($maxScoreSql, [$row['quiz_id']]);
            $maxScore = $maxScoreResult[0]['max_score'] ?? 100; // Default to 100 if not found
            
            $scorePercentage = ($maxScore > 0) ? round(($row['points'] / $maxScore) * 100) : 0;
            
            if (!isset($attempts[$row['quiz_id']])) {
                $attempts[$row['quiz_id']] = [
                    'title' => $row['title'],
                    'best_score' => $scorePercentage,
                    'best_attempt_id' => $row['attempt_id'],
                    'attempts' => 1,
                    'latest_attempt' => $row['end_time']
                ];
            } else {
                $attempts[$row['quiz_id']]['attempts']++;
                // Update best score if this attempt is better
                if ($scorePercentage > $attempts[$row['quiz_id']]['best_score']) {
                    $attempts[$row['quiz_id']]['best_score'] = $scorePercentage;
                    $attempts[$row['quiz_id']]['best_attempt_id'] = $row['attempt_id'];
                }
            }
        }
    }
    
    return $attempts;
}

// Get all quizzes
$allQuizzes = getQuizzes();

// Get user attempts if logged in
$userAttempts = [];
$attemptedQuizzes = [];
if ($isLoggedIn && isset($_SESSION['student_id'])) {
    $userAttempts = getUserQuizAttempts($_SESSION['student_id']);
    
    // Filter quizzes to show only attempted ones
    foreach ($allQuizzes as $quiz) {
        if (isset($userAttempts[$quiz['quiz_id']])) {
            $attemptedQuizzes[] = array_merge($quiz, ['attempt_data' => $userAttempts[$quiz['quiz_id']]]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'common_styles.php'; ?>
    <link rel="stylesheet" href="quiz-card-new.css">
    <style>
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
        
        body {
            padding: 0;
            margin: 0;
        }
        
        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background-color: transparent;
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
        
        .quiz-actions {
            position: static;
            display: flex;
            margin-top: 1rem;
            width: 100%;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        
        .view-btn, .edit-btn {
            font-size: 0.8rem;
            padding: 5px 10px;
            border: 2px solid var(--primary-color);
            background-color: transparent;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .view-btn:hover, .edit-btn:hover {
            background-color: var(--primary-color);
            color: var(--card-bg);
            transform: translateY(-2px);
        }
        
        .quiz-empty {
            text-align: center;
            padding: 3rem;
            background-color: transparent;
            border-radius: var(--border-radius);
            border: 4px solid var(--border-color);
        }
        
        .quiz-empty-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .quiz-empty-text {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 2rem;
        }
        
        .quiz-container {
            padding: 20px 0;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin: 0 auto;
            max-width: 1400px;
        }
        
        @media (max-width: 1200px) {
            .quiz-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .quiz-container {
                grid-template-columns: 1fr;
            }
            
            .quiz-card {
                min-height: auto;
            }
        }
        
        .quiz-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            min-height: 320px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }
        
        .quiz-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }
        
        .quiz-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .quiz-info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .quiz-header {
            margin-bottom: 10px;
        }
        
        .quiz-title-row {
            margin-bottom: 8px;
        }
        
        .quiz-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
            line-height: 1.4;
        }
        
        .quiz-description {
            color: var(--text-color);
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
            opacity: 0.9;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .quiz-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 8px;
            border-radius: 8px;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border: none;
        }
        
        .meta-label {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .meta-value {
            font-size: 0.9rem;
            color: var(--text-color);
            font-weight: 500;
        }
        
        .quiz-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            gap: 12px;
        }
        
        .view-btn, .edit-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: none;
            letter-spacing: normal;
            transition: all 0.2s ease;
        }
        
        .view-btn {
            background-color: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .edit-btn {
            background-color: var(--primary-color);
            border: 1px solid var(--primary-color);
            color: white;
        }
        
        .view-btn:hover, .edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Your Quiz Attempts</h1>
            <p class="page-description">Review your quiz attempts and results. New quizzes are available through the dashboard in your current level.</p>
        </div>
        
        <div class="quiz-container">
            <?php if (count($attemptedQuizzes) > 0): ?>
                <?php foreach ($attemptedQuizzes as $quiz): ?>
                    <div class="quiz-card">
                        <div class="quiz-info">
                            <div class="quiz-header">
                                <div class="quiz-title-row">
                                    <h3 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                </div>
                            </div>
                            
                            <p class="quiz-description"><?php echo htmlspecialchars($quiz['description']); ?></p>
                            
                            <div class="quiz-meta">
                                <div class="meta-item">
                                    <div class="meta-label">Questions</div>
                                    <div class="meta-value"><?php echo $quiz['question_count']; ?></div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">Time Limit</div>
                                    <div class="meta-value"><?php echo $quiz['time_limit']; ?> min</div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">Best Score</div>
                                    <div class="meta-value"><?php echo $quiz['attempt_data']['best_score']; ?>%</div>
                                </div>
                                
                                <div class="meta-item">
                                    <div class="meta-label">Attempts</div>
                                    <div class="meta-value"><?php echo $quiz['attempt_data']['attempts']; ?></div>
                                </div>
                            </div>
                            
                            <div class="quiz-actions">
                                <?php if ($quiz['attempt_data']['attempts'] > 0): ?>
                                    <a href="quiz_results.php?attempt_id=<?php echo $quiz['attempt_data']['best_attempt_id']; ?>" class="btn view-btn">View Results</a>
                                <?php endif; ?>
                                <a href="take_quiz.php?id=<?php echo $quiz['quiz_id']; ?>" class="btn edit-btn">
                                    <?php echo $quiz['attempt_data']['attempts'] > 0 ? 'Retry Quiz' : 'Start Quiz'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="quiz-empty">
                    <div class="quiz-empty-icon">
                        <i class="material-icons">quiz</i>
                    </div>
                    <p class="quiz-empty-text">You haven't attempted any quizzes yet. Visit your dashboard to start quizzes from your current level.</p>
                    <a href="dashboard.php" class="btn view-btn">Go to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
