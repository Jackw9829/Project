<?php
/**
 * Quiz Results Page
 * 
 * This file displays the results of a completed quiz attempt.
 * It shows the user's score, correct/incorrect answers, and feedback.
 */

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get attempt ID from URL parameter
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;
$student_id = $_SESSION['student_id'];

// If no attempt ID provided, redirect to quizzes page
if ($attempt_id === 0) {
    header("Location: quizzes.php");
    exit();
}

// Verify that this attempt belongs to the current user
$sql = "SELECT qa.*, q.title as quiz_title, q.time_limit 
        FROM quiz_attempts qa 
        JOIN quizzes q ON qa.quiz_id = q.quiz_id 
        WHERE qa.attempt_id = ? AND qa.student_id = ?";
$attempt_result = executeQuery($sql, [$attempt_id, $student_id]);

if (!$attempt_result || count($attempt_result) === 0) {
    // Attempt not found or doesn't belong to this user
    header("Location: quizzes.php");
    exit();
}

$attempt = $attempt_result[0];
$quiz_id = $attempt['quiz_id'];
$quiz_title = $attempt['quiz_title'];
// Initialize score variable
$score = 0;
// Calculate time taken more accurately
$start_time = new DateTime($attempt['start_time']);
$end_time = new DateTime($attempt['end_time'] ?? date('Y-m-d H:i:s'));
$duration = $start_time->diff($end_time);

// Calculate time in minutes and seconds
$time_taken_minutes = ($duration->h * 60) + $duration->i;
$time_taken_seconds = $duration->s;
$time_taken = $time_taken_minutes + ($time_taken_seconds / 60); // in minutes

// Format for display
$time_display = sprintf("%02d:%02d", $time_taken_minutes, $time_taken_seconds);

// Get total possible score for this quiz
$sql = "SELECT SUM(points) as total_points FROM quiz_questions WHERE quiz_id = ?";
$total_points_result = executeQuery($sql, [$quiz_id]);
$total_points = $total_points_result[0]['total_points'] ?? 0;

// Get detailed answers from quiz_answers table
try {
    // Get the questions for this quiz
    $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id";
    $questions_result = executeQuery($sql, [$quiz_id]);
    
    // Get the user's answers from quiz_answers table
    $sql = "SELECT qa.*, qq.question_text, qq.option_a, qq.option_b, qq.option_c, qq.option_d, qq.correct_answer, qq.points 
            FROM quiz_answers qa 
            JOIN quiz_questions qq ON qa.question_id = qq.question_id 
            WHERE qa.attempt_id = ? 
            ORDER BY qa.answer_time";
    $answers_result = executeQuery($sql, [$attempt_id]);
    
    if (!is_array($answers_result)) {
        $answers_result = [];
    }
    
    // Calculate more accurate time taken using the timestamps from quiz_answers
    if (count($answers_result) >= 2) {
        // Get first and last answer timestamps
        $first_answer_time = new DateTime($answers_result[0]['answer_time']);
        $last_answer_time = new DateTime($answers_result[count($answers_result) - 1]['answer_time']);
        
        // Calculate duration between first and last answer
        $duration = $first_answer_time->diff($last_answer_time);
        
        // Update time display
        $time_taken_minutes = ($duration->h * 60) + $duration->i;
        $time_taken_seconds = $duration->s;
        $time_taken = $time_taken_minutes + ($time_taken_seconds / 60); // in minutes
        $time_display = sprintf("%02d:%02d", $time_taken_minutes, $time_taken_seconds);
    }
} catch (Exception $e) {
    error_log("Error retrieving quiz answers: " . $e->getMessage());
    $answers_result = [];
}

// Count correct and incorrect answers and calculate total points earned
$correct_count = 0;
$incorrect_count = 0;
$score = 0;

if ($answers_result && count($answers_result) > 0) {
    foreach ($answers_result as $answer) {
        if ($answer['is_correct']) {
            $correct_count++;
            // Add points earned for this correct answer
            $score += $answer['points_earned'];
        } else {
            $incorrect_count++;
        }
    }
}

// Log the calculated score for debugging
error_log("Quiz Results - Calculated score from answers: " . $score);

// Calculate percentage score
$percentage = ($total_points > 0) ? round(($score / $total_points) * 100) : 0;

// Log the calculated percentage for debugging
error_log("Quiz Results - Calculated percentage: " . $percentage . "%");

// Determine pass/fail status (passing is 70% or higher)
$passed = $percentage >= 70;

// Update user progress and points
if ($passed) {
    // Add points to user's total
    try {
        $sql = "UPDATE students SET total_points = total_points + ? WHERE student_id = ?";
        executeQuery($sql, [$score, $student_id]);
        error_log("Updated student points successfully");
    } catch (Exception $e) {
        error_log("Error updating student points: " . $e->getMessage());
    }
    
    // Note: The content_id column doesn't exist in the quizzes table
    // We'll skip the user_progress update for now
    
    // Check for achievements
    // This is a simplified version - in a full implementation, you would check various achievement criteria
    $earned_achievements = [];
    
    // Quick Learner achievement - first quiz completed
    $quizCompletedSql = "SELECT COUNT(*) as count FROM quiz_attempts 
                         WHERE student_id = ? AND is_completed = 1";
    $quizCompletedResult = executeQuery($quizCompletedSql, [$student_id]);
    $completedQuizCount = ($quizCompletedResult && count($quizCompletedResult) > 0) ? $quizCompletedResult[0]['count'] : 0;
    
    // If this is their first completed quiz
    if ($completedQuizCount <= 1) {
        $earned_achievements[] = [
            'achievement_id' => 2,
            'title' => 'Quick Learner',
            'description' => 'Complete your first quiz!',
            'points' => 15,
            'icon' => 'quiz'
        ];
    }
    
    // Perfect Score achievement - 100% on a quiz
    if ($percentage === 100 && $correct_count === count($answers_result)) {
        $earned_achievements[] = [
            'achievement_id' => 4,
            'title' => 'Perfect Score',
            'description' => 'Get 100% on any quiz',
            'points' => 25,
            'icon' => 'grade'
        ];
    }
    
    // First Steps achievement - Complete all quizzes in a level
    // Check if this quiz completion has completed an entire level
    $levelCompletionSql = "SELECT l.level_id, l.level_name, 
                          COUNT(q.quiz_id) as total_quizzes,
                          COUNT(DISTINCT qa.quiz_id) as completed_quizzes
                          FROM levels l
                          JOIN quizzes q ON q.level_id = l.level_id
                          LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.quiz_id 
                                AND qa.student_id = ? 
                                AND qa.is_completed = 1
                          WHERE q.quiz_id = ? OR qa.quiz_id = ?
                          GROUP BY l.level_id
                          HAVING COUNT(q.quiz_id) > 0 
                                AND COUNT(DISTINCT qa.quiz_id) = COUNT(q.quiz_id)";
    $levelResult = executeQuery($levelCompletionSql, [$student_id, $quiz_id, $quiz_id]);
    
    if ($levelResult && count($levelResult) > 0) {
        // This quiz completion has completed an entire level
        $earned_achievements[] = [
            'achievement_id' => 1,
            'title' => 'First Steps',
            'description' => 'Complete your first level!',
            'points' => 10,
            'icon' => 'school'
        ];
    }
    
    // Log the achievements for debugging
    error_log("Quiz Results - Earned achievements: " . print_r($earned_achievements, true));
}

// Ensure achievements are always available for display
if (!isset($earned_achievements)) {
    $earned_achievements = [];
}

// Page title
$pageTitle = "Quiz Results";

// Additional styles for results page
$additionalStyles = '
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
    
    .results-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 30px;
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
    }
    
    .results-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .quiz-title {
        font-size: 1.8rem;
        color: var(--primary-color);
        margin-bottom: 10px;
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
    }
    
    .score-display {
        font-size: 3rem;
        font-weight: bold;
        margin: 20px 0;
        color: var(--text-color);
    }
    
    .pass-badge, .fail-badge {
        display: inline-block;
        padding: 8px 15px;
        border-radius: var(--border-radius);
        font-size: 1.2rem;
        margin-bottom: 20px;
    }
    
    .pass-badge {
        background-color: #4CAF50;
        color: white;
    }
    
    .fail-badge {
        background-color: #F44336;
        color: white;
    }
    
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        padding: 15px;
        border-radius: var(--border-radius);
        text-align: center;
        border: 2px solid var(--border-color);
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-color);
    }
    
    .answers-container {
        margin-top: 30px;
    }
    
    .answers-title {
        font-size: 1.5rem;
        margin-bottom: 20px;
        color: var(--primary-color);
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
    }
    
    .answer-item {
        margin-bottom: 25px;
        padding: 15px;
        border-radius: var(--border-radius);
        border: 2px solid var(--border-color);
    }
    
    .answer-item.correct {
        border-left: 10px solid #4CAF50;
    }
    
    .answer-item.incorrect {
        border-left: 10px solid #F44336;
    }
    
    .question-text {
        font-size: 1.1rem;
        margin-bottom: 15px;
        line-height: 1.5;
    }
    
    .options-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .option-item {
        padding: 10px;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        position: relative;
    }
    
    .option-text {
        flex: 1;
    }
    
    .option-indicators {
        margin-left: auto;
        display: flex;
        align-items: center;
    }
    
    .option-prefix {
        display: inline-block;
        width: 25px;
        height: 25px;
        line-height: 25px;
        text-align: center;
        background-color: var(--primary-color);
        color: white;
        border-radius: 50%;
        margin-right: 10px;
        flex-shrink: 0;
    }
    
    .option-item.selected {
        background-color: rgba(var(--primary-color-rgb), 0.2);
        border: 2px solid var(--primary-color);
    }
    
    .option-item.correct {
        background-color: rgba(76, 175, 80, 0.2);
        border: 2px solid #4CAF50;
    }
    
    .option-item.incorrect {
        background-color: rgba(244, 67, 54, 0.2);
        border: 2px solid #F44336;
    }
    
    .user-selected-correct, .user-selected-incorrect, .correct-answer {
        font-size: 20px;
        margin-left: 5px;
    }
    
    .user-selected-correct {
        color: #4CAF50;
    }
    
    .user-selected-incorrect {
        color: #F44336;
    }
    
    .correct-answer {
        color: #4CAF50;
    }
    
    .answer-feedback {
        font-size: 0.9rem;
        margin-top: 10px;
        padding: 10px;
        border-radius: var(--border-radius);
    }
    
    .answer-feedback.correct {
        background-color: rgba(76, 175, 80, 0.1);
        color: #4CAF50;
    }
    
    .answer-feedback.incorrect {
        background-color: rgba(244, 67, 54, 0.1);
        color: #F44336;
    }
    
    .actions-container {
        display: flex;
        justify-content: space-between;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid var(--border-color);
    }
    
    .btn-primary, .btn-secondary {
        padding: 12px 25px;
        border-radius: var(--border-radius);
        font-family: var(--font-family);
        font-size: 1rem;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-block;
        text-align: center;
        text-shadow: 1px 1px 0px rgba(0, 0, 0, 0.3);
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        border: none;
    }
    
    .btn-secondary {
        background-color: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }
    
    .btn-primary:hover, .btn-secondary:hover {
        transform: translateY(-2px);
    }
    
    .btn-primary:hover {
        background-color: var(--secondary-color);
    }
    
    .btn-secondary:hover {
        background-color: rgba(var(--primary-color-rgb), 0.1);
    }
    
    .achievement-earned {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        padding: 15px;
        border-radius: var(--border-radius);
        margin-top: 20px;
        border: 2px solid var(--primary-color);
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .achievement-icon {
        width: 50px;
        height: 50px;
        background-color: var(--primary-color);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .achievement-details {
        flex: 1;
    }
    
    .achievement-title {
        font-size: 1.2rem;
        color: var(--primary-color);
        margin-bottom: 5px;
    }
    
    .achievement-description {
        font-size: 0.9rem;
        color: var(--text-color);
    }
    
    .achievement-points {
        background-color: var(--primary-color);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.9rem;
    }
</style>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'common_styles.php'; ?>
    <?php echo $additionalStyles; ?>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
    
    <?php include_once 'includes/header.php'; ?>
    
    <div class="main-content" style="padding-top: 40px;">
        <div class="container">
            <!-- Page header removed as requested -->
            
            <div class="results-container">
                <div class="results-header">
                    <h2 class="quiz-title"><?php echo htmlspecialchars($quiz_title); ?></h2>
                    
                    <div class="score-display"><?php echo $percentage; ?>%</div>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $score; ?>/<?php echo $total_points; ?></div>
                            <div class="stat-label">Points</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $correct_count; ?>/<?php echo (is_array($answers_result) ? count($answers_result) : 0); ?></div>
                            <div class="stat-label">Correct Answers</div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $time_display; ?></div>
                            <div class="stat-label">Time Taken</div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($earned_achievements) && count($earned_achievements) > 0): ?>
                    <div class="achievements-container">
                        <h3 class="answers-title">Achievements Earned</h3>
                        
                        <?php foreach ($earned_achievements as $achievement): ?>
                            <div class="achievement-earned">
                                <div class="achievement-icon">
                                    <i class="material-icons"><?php echo htmlspecialchars($achievement['icon']); ?></i>
                                </div>
                                <div class="achievement-details">
                                    <h4 class="achievement-title"><?php echo htmlspecialchars($achievement['title']); ?></h4>
                                    <p class="achievement-description"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                </div>
                                <div class="achievement-points">+<?php echo $achievement['points']; ?> pts</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="answers-container">
                    <h3 class="answers-title">Question Review</h3>
                    
                    <?php if ($answers_result && count($answers_result) > 0): ?>
                        <?php foreach ($answers_result as $index => $answer): ?>
                            <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                <h4 class="question-text">Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></h4>
                                
                                <div class="options-list">
                                    <div class="option-item <?php echo ($answer['correct_answer'] === 'a') ? 'correct' : ''; ?> <?php echo ($answer['selected_answer'] === 'a') ? 'selected' : ''; ?> <?php echo ($answer['selected_answer'] === 'a' && $answer['correct_answer'] !== 'a') ? 'incorrect' : ''; ?>">
                                        <span class="option-prefix">A</span>
                                        <span class="option-text"><?php echo htmlspecialchars($answer['option_a']); ?></span>
                                        <div class="option-indicators">
                                            <?php if ($answer['selected_answer'] === 'a' && $answer['correct_answer'] === 'a'): ?>
                                                <i class="material-icons user-selected-correct">verified</i>
                                            <?php elseif ($answer['selected_answer'] === 'a'): ?>
                                                <i class="material-icons user-selected-incorrect">close</i>
                                            <?php elseif ($answer['correct_answer'] === 'a'): ?>
                                                <i class="material-icons correct-answer">check_circle</i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="option-item <?php echo ($answer['correct_answer'] === 'b') ? 'correct' : ''; ?> <?php echo ($answer['selected_answer'] === 'b') ? 'selected' : ''; ?> <?php echo ($answer['selected_answer'] === 'b' && $answer['correct_answer'] !== 'b') ? 'incorrect' : ''; ?>">
                                        <span class="option-prefix">B</span>
                                        <span class="option-text"><?php echo htmlspecialchars($answer['option_b']); ?></span>
                                        <div class="option-indicators">
                                            <?php if ($answer['selected_answer'] === 'b' && $answer['correct_answer'] === 'b'): ?>
                                                <i class="material-icons user-selected-correct">verified</i>
                                            <?php elseif ($answer['selected_answer'] === 'b'): ?>
                                                <i class="material-icons user-selected-incorrect">close</i>
                                            <?php elseif ($answer['correct_answer'] === 'b'): ?>
                                                <i class="material-icons correct-answer">check_circle</i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($answer['option_c'])): ?>
                                    <div class="option-item <?php echo ($answer['correct_answer'] === 'c') ? 'correct' : ''; ?> <?php echo ($answer['selected_answer'] === 'c') ? 'selected' : ''; ?> <?php echo ($answer['selected_answer'] === 'c' && $answer['correct_answer'] !== 'c') ? 'incorrect' : ''; ?>">
                                        <span class="option-prefix">C</span>
                                        <span class="option-text"><?php echo htmlspecialchars($answer['option_c']); ?></span>
                                        <div class="option-indicators">
                                            <?php if ($answer['selected_answer'] === 'c' && $answer['correct_answer'] === 'c'): ?>
                                                <i class="material-icons user-selected-correct">verified</i>
                                            <?php elseif ($answer['selected_answer'] === 'c'): ?>
                                                <i class="material-icons user-selected-incorrect">close</i>
                                            <?php elseif ($answer['correct_answer'] === 'c'): ?>
                                                <i class="material-icons correct-answer">check_circle</i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($answer['option_d'])): ?>
                                    <div class="option-item <?php echo ($answer['correct_answer'] === 'd') ? 'correct' : ''; ?> <?php echo ($answer['selected_answer'] === 'd') ? 'selected' : ''; ?> <?php echo ($answer['selected_answer'] === 'd' && $answer['correct_answer'] !== 'd') ? 'incorrect' : ''; ?>">
                                        <span class="option-prefix">D</span>
                                        <span class="option-text"><?php echo htmlspecialchars($answer['option_d']); ?></span>
                                        <div class="option-indicators">
                                            <?php if ($answer['selected_answer'] === 'd' && $answer['correct_answer'] === 'd'): ?>
                                                <i class="material-icons user-selected-correct">verified</i>
                                            <?php elseif ($answer['selected_answer'] === 'd'): ?>
                                                <i class="material-icons user-selected-incorrect">close</i>
                                            <?php elseif ($answer['correct_answer'] === 'd'): ?>
                                                <i class="material-icons correct-answer">check_circle</i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="answer-feedback <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                    <?php if ($answer['is_correct']): ?>
                                        <i class="material-icons">check_circle</i> Correct! You earned <?php echo isset($answer['points_earned']) ? $answer['points_earned'] : (isset($answer['points']) ? $answer['points'] : 1); ?> points.
                                    <?php else: ?>
                                        <i class="material-icons">cancel</i> Incorrect. The correct answer is <?php echo strtoupper($answer['correct_answer']); ?>.
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No answers found for this attempt.</p>
                    <?php endif; ?>
                </div>
                
                <div class="actions-container">
                    <a href="quizzes.php" class="btn-secondary">Back to Quizzes</a>
                    
                    <?php if (!$passed): ?>
                        <a href="take_quiz.php?id=<?php echo $quiz_id; ?>" class="btn-primary">Try Again</a>
                    <?php else: ?>
                        <a href="module.php?id=<?php echo $attempt['module_id'] ?? 0; ?>" class="btn-primary">Continue Learning</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
