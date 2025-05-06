<?php
/**
 * Challenge Results Page
 * 
 * This file displays the results of a completed challenge attempt.
 * It shows the user's score, submitted code, and feedback.
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

// Get attempt ID from URL parameter or session variable as fallback
$attempt_id = isset($_GET['attempt_id']) ? intval($_GET['attempt_id']) : 0;

// If attempt_id is not in URL, try to get it from session
if ($attempt_id === 0 && isset($_SESSION['last_completed_challenge_attempt'])) {
    $attempt_id = intval($_SESSION['last_completed_challenge_attempt']);
    error_log("Challenge Results - Using attempt_id from session: $attempt_id");
}

$student_id = $_SESSION['student_id'];

// Add detailed error logging
error_log("Challenge Results - Attempt ID: $attempt_id, Student ID: $student_id");

// If no attempt ID provided, redirect to challenges page
if ($attempt_id === 0) {
    header("Location: challenges.php");
    exit();
}

// Verify that this attempt belongs to the current user
$sql = "SELECT ca.*, c.challenge_name, c.description, c.difficulty_level, c.points as total_possible_points
        FROM challenge_attempts ca 
        JOIN challenges c ON ca.challenge_id = c.challenge_id 
        WHERE ca.attempt_id = ? AND ca.student_id = ?";
error_log("Challenge Results - SQL Query: $sql with attempt_id=$attempt_id and student_id=$student_id");
$attempt_result = executeQuery($sql, [$attempt_id, $student_id]);
error_log("Challenge Results - Query result: " . print_r($attempt_result, true));

if (!$attempt_result || count($attempt_result) === 0) {
    // Attempt not found or doesn't belong to this user
    error_log("Challenge Results - Attempt not found or doesn't belong to this user");
    header("Location: challenges.php");
    exit();
}

$attempt = $attempt_result[0];
$challenge_id = $attempt['challenge_id'];
$challenge_name = $attempt['challenge_name'];
$score = $attempt['score'] ?? $attempt['points'] ?? 0; // Try both 'score' and 'points' fields
$total_possible_points = $attempt['total_possible_points'];

// Calculate time taken more accurately with validation
$start_time = new DateTime($attempt['start_time']);
// Make sure end_time is set, if not use current time
if (empty($attempt['end_time'])) {
    $updateEndTimeSql = "UPDATE challenge_attempts SET end_time = NOW() WHERE attempt_id = ?";
    executeQuery($updateEndTimeSql, [$attempt_id]);
    $end_time = new DateTime();
} else {
    $end_time = new DateTime($attempt['end_time']);
}
$duration = $start_time->diff($end_time);

// Calculate time in minutes and seconds
$time_taken_minutes = ($duration->h * 60) + $duration->i;
$time_taken_seconds = $duration->s;
$time_taken = $time_taken_minutes + ($time_taken_seconds / 60); // in minutes

// Format for display
$time_display = sprintf("%02d:%02d", $time_taken_minutes, $time_taken_seconds);

// Calculate percentage score with validation
$percentage = ($total_possible_points > 0 && is_numeric($score)) ? round(($score / $total_possible_points) * 100) : 0;
$percentage = min(100, max(0, $percentage)); // Ensure percentage is between 0-100

// Determine pass/fail status (passing is 70% or higher)
$passed = $percentage >= 70;

// Get detailed answers from challenge_answers table
try {
    // Get the questions for this challenge
    $sql = "SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_id";
    error_log("Challenge Results - Getting questions SQL: $sql with challenge_id=$challenge_id");
    $questions_result = executeQuery($sql, [$challenge_id]);
    error_log("Challenge Results - Questions result: " . print_r($questions_result, true));
    
    // Get the user's answers from challenge_answers table
    $sql = "SELECT ca.*, cq.question_text, cq.points, 
            cq.option_a, cq.option_b, cq.option_c, cq.option_d, cq.correct_answer
            FROM challenge_answers ca 
            JOIN challenge_questions cq ON ca.question_id = cq.question_id 
            WHERE ca.attempt_id = ? 
            ORDER BY cq.question_order, ca.answer_id";
    error_log("Challenge Results - Getting answers SQL: $sql with attempt_id=$attempt_id");
    $answers_result = executeQuery($sql, [$attempt_id]);
    error_log("Challenge Results - Answers result: " . print_r($answers_result, true));
    
    if (!is_array($answers_result) || empty($answers_result)) {
        error_log("Challenge Results - Answers result is empty or not an array");
        // If no answers found, get the questions directly
        // First, get the challenge_id from the attempt
        $challenge_id = $attempt_result[0]['challenge_id'];
        error_log("Challenge Results - Using challenge_id: $challenge_id from attempt");
        
        $sql = "SELECT cq.*, ca.attempt_id 
                FROM challenge_questions cq
                LEFT JOIN challenge_attempts ca ON ca.challenge_id = cq.challenge_id
                WHERE ca.attempt_id = ?
                ORDER BY cq.question_id";
        $questions_result = executeQuery($sql, [$attempt_id]);
        error_log("Challenge Results - Fallback questions result: " . print_r($questions_result, true));
        $answers_result = [];
        
        // If we found questions, create placeholder answers
        if ($questions_result && count($questions_result) > 0) {
            foreach ($questions_result as $question) {
                // Check if there's an answer in the database that we might have missed
                $checkAnswerSql = "SELECT * FROM challenge_answers WHERE attempt_id = ? AND question_id = ?";
                $existingAnswer = executeQuery($checkAnswerSql, [$attempt_id, $question['question_id']]);
                
                if ($existingAnswer && count($existingAnswer) > 0) {
                    // Use the existing answer data
                    $answer = $existingAnswer[0];
                    $answer['question_text'] = $question['question_text'];
                    $answer['option_a'] = $question['option_a'] ?? null;
                    $answer['option_b'] = $question['option_b'] ?? null;
                    $answer['option_c'] = $question['option_c'] ?? null;
                    $answer['option_d'] = $question['option_d'] ?? null;
                    $answer['correct_answer'] = $question['correct_answer'];
                    $answer['points'] = $question['points'];
                    $answers_result[] = $answer;
                } else {
                    // Create a placeholder answer
                    $answers_result[] = [
                        'question_id' => $question['question_id'],
                        'question_text' => $question['question_text'],
                        'option_a' => $question['option_a'] ?? null,
                        'option_b' => $question['option_b'] ?? null,
                        'option_c' => $question['option_c'] ?? null,
                        'option_d' => $question['option_d'] ?? null,
                        'correct_answer' => $question['correct_answer'],
                        'selected_answer' => 'not_answered',
                        'is_correct' => false,
                        'points_earned' => 0,
                        'points' => $question['points'],
                        'answer_time' => $attempt['end_time']
                    ];
                }
            }
        }
    }
    
    // Calculate time taken if we have answers
    if (count($answers_result) > 0 && isset($answers_result[0]['answer_time'])) {
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
    } else {
        // If no answers with timestamps, use attempt start and end time
        if (isset($attempt_result[0]['start_time']) && isset($attempt_result[0]['end_time'])) {
            $start_time = new DateTime($attempt_result[0]['start_time']);
            $end_time = new DateTime($attempt_result[0]['end_time']);
            
            // Calculate duration
            $duration = $start_time->diff($end_time);
            
            // Update time display
            $time_taken_minutes = ($duration->h * 60) + $duration->i;
            $time_taken_seconds = $duration->s;
            $time_taken = $time_taken_minutes + ($time_taken_seconds / 60); // in minutes
            $time_display = sprintf("%02d:%02d", $time_taken_minutes, $time_taken_seconds);
        } else {
            // Default if no timestamps available
            $time_taken = 0;
            $time_display = "00:00";
        }
    }
} catch (Exception $e) {
    error_log("Error processing challenge answers: " . $e->getMessage());
    $answers_result = [];
}

// Count correct and incorrect answers
$correct_count = 0;
$incorrect_count = 0;

// Get the total possible points and count of questions for this challenge
$questionsQuery = "SELECT COUNT(*) as question_count FROM challenge_questions WHERE challenge_id = ?";
$questionsResult = executeQuery($questionsQuery, [$challenge_id]);
$question_count = ($questionsResult && count($questionsResult) > 0) ? $questionsResult[0]['question_count'] : 0;

// Get the total possible points from the challenge table
$challengePointsQuery = "SELECT points as total_points FROM challenges WHERE challenge_id = ?";
$challengePointsResult = executeQuery($challengePointsQuery, [$challenge_id]);
if ($challengePointsResult && count($challengePointsResult) > 0) {
    $total_possible_points = $challengePointsResult[0]['total_points'];
}

// Calculate points per question (distribute total points evenly)
$points_per_question = ($question_count > 0 && $total_possible_points > 0) ? ($total_possible_points / $question_count) : 0;

error_log("Challenge Results - Total possible points: $total_possible_points, Question count: $question_count, Points per question: $points_per_question");

// Process answers to ensure is_correct is properly set
if ($answers_result && count($answers_result) > 0) {
    foreach ($answers_result as &$answer) {
        // Make sure is_correct is properly set based on selected_answer and correct_answer
        if (isset($answer['selected_answer']) && isset($answer['correct_answer'])) {
            $answer['is_correct'] = ($answer['selected_answer'] === $answer['correct_answer']);
        } else {
            $answer['is_correct'] = false;
        }
        
        // Count correct and incorrect answers
        if ($answer['is_correct']) {
            $correct_count++;
            // Award points based on the even distribution of total points
            $answer['points_earned'] = $points_per_question;
        } else {
            $incorrect_count++;
            // No points for incorrect answers
            $answer['points_earned'] = 0;
        }
        
        // Log the points for this answer
        error_log("Challenge Results - Answer for question {$answer['question_id']}: is_correct={$answer['is_correct']}, points_earned={$answer['points_earned']}, points={$answer['points']}");
    }
    unset($answer); // Unset reference to last element
    
    // Recalculate score based on correct answers and points per question
    $score = $correct_count * $points_per_question;
    
    // Round the score to the nearest integer
    $score = round($score);
    
    // Update the score in the database
    $updateScoreQuery = "UPDATE challenge_attempts SET points = ?, end_time = IFNULL(end_time, NOW()) WHERE attempt_id = ?";
    // This ensures end_time is set if it wasn't already
    executeQuery($updateScoreQuery, [$score, $attempt_id]);
    error_log("Challenge Results - Updated points in database: $score for attempt_id=$attempt_id");
    
    // Update percentage based on recalculated score
    $percentage = ($total_possible_points > 0) ? round(($score / $total_possible_points) * 100) : 0;
    $passed = $percentage >= 70; // Keep the variable for internal logic but don't display it
    
    // Log the recalculated score and percentage for debugging
    error_log("Challenge Results - Recalculated points: $score, Percentage: $percentage, Passed: " . ($passed ? 'Yes' : 'No'));
}

// Update user progress and points
if ($passed) {
    // Initialize earned achievements array
    $earned_achievements = [];
    
    // Add points to user's total
    try {
        $sql = "UPDATE students SET total_points = total_points + ? WHERE student_id = ?";
        executeQuery($sql, [$score, $student_id]);
        error_log("Updated student points successfully");
    } catch (Exception $e) {
        error_log("Error updating student points: " . $e->getMessage());
    }
    
    // Set a session variable to indicate a challenge was completed
    $_SESSION['challenge_completed'] = true;
    $_SESSION['challenge_score'] = $score;
    $_SESSION['challenge_id'] = $challenge_id;
    
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
    
    // Add logging statement to track earned achievements
    error_log("Challenge Results - Earned achievements: " . print_r($earned_achievements, true));
    
    // Log that we've completed a challenge for debugging
    error_log("Challenge completed successfully. Points: $score, Challenge ID: $challenge_id");
}

// Ensure achievements are always available for display
if (!isset($earned_achievements)) {
    $earned_achievements = [];
}

// Page title
$pageTitle = "Challenge Results";

// Additional styles for results page - using the same styles as quiz_results.php
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
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: var(--primary-color);
        margin-bottom: 5px;
        font-family: "Press Start 2P", cursive;
    }
    
    .stat-label {
        font-size: 0.9rem;
        color: var(--text-color);
        text-transform: uppercase;
        letter-spacing: 1px;
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
        background-color: rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .answer-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
    
    .code-block {
        background-color: rgba(0, 0, 0, 0.2);
        padding: 15px;
        border-radius: var(--border-radius);
        margin: 15px 0;
        overflow-x: auto;
        font-family: monospace;
        white-space: pre-wrap;
        color: var(--text-color);
        border-left: 4px solid var(--primary-color);
        box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.3);
        line-height: 1.5;
    }
    
    .feedback-block {
        padding: 15px;
        border-radius: var(--border-radius);
        margin: 15px 0;
        background-color: rgba(0, 0, 0, 0.2);
        border-left: 4px solid var(--primary-color);
        box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.3);
        font-family: var(--font-family);
        line-height: 1.5;
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
            <!-- Page header removed to match quiz_results.php -->
            
            <div class="results-container">
                <div class="results-header">
                    <h2 class="quiz-title"><?php echo htmlspecialchars($challenge_name); ?></h2>
                    
                    <div class="score-display"><?php echo $percentage; ?>%</div>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $score; ?>/<?php echo $total_possible_points; ?></div>
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
                    
                    <?php if (is_array($answers_result) && count($answers_result) > 0): ?>
                        <?php foreach ($answers_result as $index => $answer): ?>
                            <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                <h4 class="question-text">Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></h4>
                                
                                <?php if (!empty($answer['option_a'])): ?>
                                <!-- Multiple choice question display -->
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
                                
                                <?php else: ?>
                                <!-- Coding challenge display -->
                                <div class="code-display"><?php echo htmlspecialchars($answer['selected_answer']); ?></div>
                                
                                <div class="feedback <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                    <strong><?php echo $answer['is_correct'] ? 'Correct' : 'Incorrect'; ?>:</strong> 
                                    <?php if ($answer['is_correct']): ?>
                                        Your solution meets all the requirements! You earned <?php echo isset($answer['points_earned']) ? $answer['points_earned'] : (isset($answer['points']) ? $answer['points'] : 1); ?> points.
                                    <?php else: ?>
                                        Your solution doesn't meet all requirements. The correct answer is <?php echo strtoupper($answer['correct_answer']); ?>.
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No submissions found for this challenge attempt.</p>
                    <?php endif; ?>
                </div>
                
                <div class="actions-container">
                    <a href="challenges.php" class="btn-secondary">Back to Challenges</a>
                    
                    <a href="take_challenge.php?id=<?php echo $challenge_id; ?>" class="btn-primary">Try Again</a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
