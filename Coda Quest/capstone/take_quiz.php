<?php
/**
 * Take Quiz Page
 * 
 * This file handles the quiz-taking functionality for CodaQuest.
 * It displays questions, choices, a timer, and processes user answers.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get quiz ID from URL parameter
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student_id = $_SESSION['student_id'];

// If no quiz ID provided, redirect to quizzes page
if ($quiz_id === 0) {
    header("Location: quizzes.php");
    exit();
}

// Variables to store quiz data
$quiz = null;
$questions = [];
$total_questions = 0;
$attempt_id = 0;
$time_remaining = 0;

// Initialize or retrieve current question from session
if (!isset($_SESSION['current_question_' . $quiz_id])) {
    $_SESSION['current_question_' . $quiz_id] = 0;
}
$current_question = $_SESSION['current_question_' . $quiz_id];

// Initialize redirect URL variable
$redirect_url = null;

// Check if this is a new attempt or continuing an existing one
$sql = "SELECT * FROM quiz_attempts WHERE student_id = ? AND quiz_id = ? AND is_completed = 0 ORDER BY attempt_id DESC LIMIT 1";
$existing_attempt = executeQuery($sql, [$student_id, $quiz_id]);

if ($existing_attempt && count($existing_attempt) > 0) {
    // Continue existing attempt
    $attempt_id = $existing_attempt[0]['attempt_id'];
    $start_time = strtotime($existing_attempt[0]['start_time']);
    
    // Get quiz details
    $sql = "SELECT * FROM quizzes WHERE quiz_id = ?";
    $quiz_result = executeQuery($sql, [$quiz_id]);
    
    if ($quiz_result && count($quiz_result) > 0) {
        $quiz = $quiz_result[0];
        // Set default time limit of 30 minutes if not specified, is null, is zero, or is empty
        $time_limit = (isset($quiz['time_limit']) && $quiz['time_limit'] !== null && !empty($quiz['time_limit']) && $quiz['time_limit'] > 0) 
            ? intval($quiz['time_limit']) 
            : 30;
        error_log("Existing attempt - Quiz time limit: $time_limit minutes (quiz_id: $quiz_id)");
        $time_limit_seconds = $time_limit * 60;
        $elapsed_seconds = time() - $start_time;
        $time_remaining = max(0, $time_limit_seconds - $elapsed_seconds);
        
        // If time has expired, mark attempt as completed
        if ($time_remaining <= 0) {
            $sql = "UPDATE quiz_attempts SET is_completed = 1, end_time = ? WHERE attempt_id = ?";
            $currentTime = date('Y-m-d H:i:s'); // Use the same datetime format and timezone as start_time
            executeQuery($sql, [$currentTime, $attempt_id]);
            header("Location: quiz_results.php?attempt_id=" . $attempt_id);
            exit();
        }
    } else {
        // Quiz not found
        header("Location: quizzes.php");
        exit();
    }
} else {
    // Start new attempt
    $sql = "SELECT * FROM quizzes WHERE quiz_id = ?";
    $quiz_result = executeQuery($sql, [$quiz_id]);
    
    if ($quiz_result && count($quiz_result) > 0) {
        $quiz = $quiz_result[0];
        // Set default time limit of 30 minutes if not specified, is null, is zero, or is empty
        $time_limit = (isset($quiz['time_limit']) && $quiz['time_limit'] !== null && !empty($quiz['time_limit']) && $quiz['time_limit'] > 0) 
            ? intval($quiz['time_limit']) 
            : 30;
        error_log("Quiz time limit: $time_limit minutes (quiz_id: $quiz_id)");
        $time_remaining = $time_limit * 60; // Convert minutes to seconds
        
        // Check if this is a retry (student has already completed this quiz before)
        $checkPreviousAttemptSql = "SELECT COUNT(*) as attempt_count FROM quiz_attempts 
                                   WHERE student_id = ? AND quiz_id = ? AND is_completed = 1";
        $previousAttempts = executeQuery($checkPreviousAttemptSql, [$student_id, $quiz_id]);
        $isRetry = ($previousAttempts && $previousAttempts[0]['attempt_count'] > 0);
        
        // Create new attempt
        try {
            // Insert the attempt using insertData function
            $attemptData = [
                'student_id' => $student_id,
                'quiz_id' => $quiz_id,
                'start_time' => date('Y-m-d H:i:s'),
                'is_completed' => 0,
                'points_awarded' => $isRetry ? 0 : 1 // Set points_awarded to 0 for retries
            ];
            
            $attempt_id = insertData('quiz_attempts', $attemptData);
            
            if (!$attempt_id) {
                throw new Exception("Failed to create quiz attempt");
            }
        } catch (Exception $e) {
            // Log the error and redirect with error message
            error_log("Error starting quiz: " . $e->getMessage());
            header("Location: quizzes.php?error=" . urlencode("Unable to start quiz. Please try again later."));
            exit();
        }
    } else {
        // Quiz not found
        header("Location: quizzes.php");
        exit();
    }
}

// Get questions for this quiz
try {
    $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id";
    $questions_result = executeQuery($sql, [$quiz_id]);
    
    if ($questions_result && count($questions_result) > 0) {
        $questions = $questions_result;
        $total_questions = count($questions);
        
        // Ensure current question is valid
        if ($current_question >= $total_questions) {
            $current_question = 0;
            $_SESSION['current_question_' . $quiz_id] = $current_question;
        }
    } else {
        // No questions found for this quiz
        $questions = [];
        $total_questions = 0;
    }
} catch (Exception $e) {
    // Log the error and redirect with error message
    error_log("Error loading quiz questions: " . $e->getMessage());
    header("Location: quizzes.php?error=" . urlencode("Unable to load quiz questions. Please try again later."));
    exit();
}

// Process answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    // Add debugging
    error_log("Processing answer submission");
    error_log("POST data: " . print_r($_POST, true));
    
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $selected_answer = isset($_POST['answer']) ? $_POST['answer'] : '';
    
    error_log("Question ID: $question_id, Selected Answer: $selected_answer");
    
    if ($question_id > 0 && !empty($selected_answer)) {
        // Get correct answer
        $sql = "SELECT correct_answer, points FROM quiz_questions WHERE question_id = ?";
        $question_result = executeQuery($sql, [$question_id]);
        
        error_log("Question result: " . print_r($question_result, true));
        
        if ($question_result && count($question_result) > 0) {
            $correct_answer = $question_result[0]['correct_answer'];
            $points = $question_result[0]['points'];
            $is_correct = ($selected_answer === $correct_answer);
            $earned_points = $is_correct ? $points : 0;
            
            error_log("Correct answer: $correct_answer, Is correct: " . ($is_correct ? 'yes' : 'no') . ", Points earned: $earned_points");
            
            try {
                // First, save the answer to the quiz_answers table
                $sql = "INSERT INTO quiz_answers (attempt_id, question_id, selected_answer, is_correct, points_earned, answer_time) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                executeQuery($sql, [
                    $attempt_id, 
                    $question_id, 
                    $selected_answer, 
                    $is_correct ? 1 : 0, 
                    $earned_points
                ]);
                error_log("Answer saved to quiz_answers table");
                
                // Then update the points in the quiz_attempts table
                $sql = "UPDATE quiz_attempts SET points = points + ? WHERE attempt_id = ?";
                executeQuery($sql, [$earned_points, $attempt_id]);
                error_log("Points updated successfully");
            } catch (Exception $e) {
                error_log("Error saving answer or updating points: " . $e->getMessage());
            }
            
            // Increment the current question after successfully processing the answer
            $current_question++;
            $_SESSION['current_question_' . $quiz_id] = $current_question;
            error_log("Moving to next question: $current_question of $total_questions");
            
            // Check if this is a retry attempt where points should not be awarded
            $checkPointsAwardedSql = "SELECT points_awarded FROM quiz_attempts WHERE attempt_id = ?";
            $pointsAwardedResult = executeQuery($checkPointsAwardedSql, [$attempt_id]);
            $shouldAwardPoints = ($pointsAwardedResult && isset($pointsAwardedResult[0]['points_awarded']) && $pointsAwardedResult[0]['points_awarded'] == 1);
            
            // Check if this is the last question
            if ($current_question >= $total_questions) {
                // This was the last question, mark as completed
                error_log("Last question completed, marking quiz as done");
                
                // Mark attempt as completed
                $sql = "UPDATE quiz_attempts SET is_completed = 1, end_time = ? WHERE attempt_id = ?";
                $currentTime = date('Y-m-d H:i:s'); // Use the same datetime format and timezone as start_time
                executeQuery($sql, [$currentTime, $attempt_id]);
                
                error_log("Quiz completed, redirecting to results");
                $redirect_url = "quiz_results.php?attempt_id=" . $attempt_id;
                
                // Points check already done above
            } else {
                // Not the last question, stay on the same page to show the next question
                error_log("Not the last question, continuing quiz");
                $redirect_url = null; // Don't redirect
                
                // Only update student's points if this is not a retry
                if ($shouldAwardPoints) {
                    $updatePointsSql = "UPDATE students SET total_points = total_points + ? WHERE student_id = ?";
                    executeQuery($updatePointsSql, [$earned_points, $_SESSION['student_id']]);
                    error_log("Student points updated for ID: {$_SESSION['student_id']} after completing quiz");
                } else {
                    error_log("No points awarded for retry attempt for student ID: {$_SESSION['student_id']}");
                }
                
                // Update the leaderboard table directly
                $checkLeaderboardSql = "SELECT leaderboard_id FROM leaderboard WHERE student_id = ?";
                $leaderboardEntry = executeQuery($checkLeaderboardSql, [$_SESSION['student_id']]);
                
                // Get current stats for the student
                $statsSql = "SELECT 
                    s.total_points,
                    (SELECT COUNT(DISTINCT quiz_id) FROM quiz_attempts WHERE student_id = s.student_id AND is_completed = 1) as completed_quizzes,
                    (SELECT COUNT(DISTINCT challenge_id) FROM challenge_attempts WHERE student_id = s.student_id AND is_completed = 1) as completed_challenges
                FROM students s WHERE s.student_id = ?";
                $stats = executeQuery($statsSql, [$_SESSION['student_id']]);
                
                if ($stats && count($stats) > 0) {
                    $totalPoints = $stats[0]['total_points'];
                    $completedQuizzes = $stats[0]['completed_quizzes'];
                    $completedChallenges = $stats[0]['completed_challenges'];
                    
                    if ($leaderboardEntry && count($leaderboardEntry) > 0) {
                        // Update existing entry
                        $updateLeaderboardSql = "UPDATE leaderboard 
                            SET total_points = ?, 
                                total_quizzes_completed = ?, 
                                total_challenges_completed = ?,
                                last_updated = NOW() 
                            WHERE student_id = ?";
                        executeQuery($updateLeaderboardSql, [$totalPoints, $completedQuizzes, $completedChallenges, $_SESSION['student_id']]);
                    } else {
                        // Create new entry
                        $insertLeaderboardSql = "INSERT INTO leaderboard 
                            (student_id, total_points, total_quizzes_completed, total_challenges_completed, last_updated) 
                            VALUES (?, ?, ?, ?, NOW())";
                        executeQuery($insertLeaderboardSql, [$_SESSION['student_id'], $totalPoints, $completedQuizzes, $completedChallenges]);
                    }
                    error_log("Leaderboard updated for student ID: {$_SESSION['student_id']} after completing quiz");
                }
            }
        } else {
            error_log("No question found with ID: $question_id");
        }
    } else {
        error_log("Invalid question_id or no answer selected");
    }
}

// Check if we need to redirect after processing form
if (isset($redirect_url) && !headers_sent()) {
    header("Location: " . $redirect_url);
    exit();
}

// Final check to ensure current question doesn't exceed total questions
if ($current_question >= $total_questions && $total_questions > 0) {
    $current_question = $total_questions - 1;
    $_SESSION['current_question_' . $quiz_id] = $current_question;
} else if ($total_questions == 0) {
    // If no questions, reset to 0
    $current_question = 0;
    $_SESSION['current_question_' . $quiz_id] = 0;
}

// Page title
$pageTitle = "Taking Quiz: " . htmlspecialchars($quiz['title']);

// Additional styles for quiz page
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
    
    /* Add theme variables for this page */
    :root {
        --bronze-color: #cd7f32;
        --disabled-color: #cccccc;
        --button-text-color: #ffffff;
        --progress-bg-color: rgba(0, 0, 0, 0.1);
    }
    body.dark-mode {
        --bronze-color: #e0916b;
        --disabled-color: #555555;
        --button-text-color: #ffffff;
        --progress-bg-color: rgba(255, 255, 255, 0.1);
    }
    
    .quiz-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 30px;
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
    }
    
    .quiz-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--border-color);
    }
    
    .quiz-title {
        font-size: 1.5rem;
        color: var(--primary-color);
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.3);
    }
    
    .quiz-progress {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .progress-bar {
        width: 200px;
        height: 20px;
        background-color: var(--progress-bg-color, rgba(0, 0, 0, 0.1));
        border: 2px solid var(--border-color);
    }
    
    .progress-fill {
        height: 100%;
        background-color: var(--primary-color);
        width: 0%; /* Will be set by JavaScript */
    }
    
    .quiz-info {
        display: flex;
        gap: 20px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    
    .quiz-timer {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 1rem;
        color: var(--text-color);
        background-color: rgba(255, 0, 0, 0.1);
        padding: 5px 10px;
        border-radius: var(--border-radius);
        border-left: 3px solid #ff5252;
    }
    
    .quiz-timer i {
        color: #ff5252;
        font-size: 18px;
    }
    
    .quiz-timer.warning {
        background-color: rgba(255, 165, 0, 0.2);
        border-left-color: orange;
        animation: pulse 1s infinite;
    }
    
    .quiz-timer.danger {
        background-color: rgba(255, 0, 0, 0.2);
        border-left-color: red;
        animation: pulse 0.5s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }
    
    .quiz-points {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 1rem;
        color: var(--text-color);
        background-color: rgba(var(--primary-color-rgb), 0.1);
        padding: 5px 10px;
        border-radius: var(--border-radius);
        border-left: 3px solid var(--primary-color);
    }
    
    .quiz-points i {
        color: var(--primary-color);
        font-size: 18px;
    }
    
    .question-container {
        margin-bottom: 30px;
    }
    
    .question-text {
        font-size: 1.2rem;
        margin-bottom: 20px;
        line-height: 1.5;
        color: var(--text-color);
        text-shadow: 0 0 5px rgba(var(--primary-color-rgb), 0.5);
        font-family: "Press Start 2P", cursive;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.2);
        border-left: 4px solid var(--primary-color);
        border-radius: var(--border-radius);
    }
    
    .answer-choices {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
        margin-top: 25px;
    }
    
    .choice {
        position: relative;
    }
    
    .choice input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        cursor: pointer;
        z-index: 1;
    }
    
    .choice label {
        display: flex;
        align-items: center;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.2);
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: "Press Start 2P", cursive;
        font-size: 0.9rem;
        position: relative;
        z-index: 0;
    }
    
    .choice input[type="radio"]:checked + label {
        background-color: rgba(var(--primary-color-rgb), 0.2);
        border-color: var(--primary-color);
        box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.5);
        transform: translateY(-2px);
    }
    
    .choice label:hover {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        border-color: rgba(var(--primary-color-rgb), 0.5);
    }
    
    .choice-letter {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 36px;
        height: 36px;
        min-width: 36px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 4px;
        margin-right: 15px;
        font-weight: bold;
        font-family: "Press Start 2P", cursive;
        font-size: 0.8rem;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
        text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.5);
    }
    
    .choice-text {
        flex: 1;
        line-height: 1.4;
        font-size: 0.9rem;
        font-family: var(--font-family);
    }
    
    /* Specific colors for each option */
    .choice:nth-child(1) .choice-letter {
        background-color: #FF5252; /* Red for A */
    }
    
    .choice:nth-child(2) .choice-letter {
        background-color: #4CAF50; /* Green for B */
    }
    
    .choice:nth-child(3) .choice-letter {
        background-color: #2196F3; /* Blue for C */
    }
    
    .choice:nth-child(4) .choice-letter {
        background-color: #FFC107; /* Yellow for D */
    }
    
    /* Glowing effect when selected */
    .choice:nth-child(1) input[type="radio"]:checked + label .choice-letter {
        box-shadow: 0 0 10px #FF5252;
    }
    
    .choice:nth-child(2) input[type="radio"]:checked + label .choice-letter {
        box-shadow: 0 0 10px #4CAF50;
    }
    
    .choice:nth-child(3) input[type="radio"]:checked + label .choice-letter {
        box-shadow: 0 0 10px #2196F3;
    }
    
    .choice:nth-child(4) input[type="radio"]:checked + label .choice-letter {
        box-shadow: 0 0 10px #FFC107;
    }
    
    .submit-container {
        display: flex;
        justify-content: center;
        margin-top: 40px;
    }
    
    /* Audio Player Styles */
    .audio-player {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 100;
    }
    
    .audio-control {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    }
    
    .audio-control:hover {
        transform: scale(1.1);
        background-color: var(--secondary-color);
    }
    
    .audio-control i {
        font-size: 24px;
    }
    
    .btn-submit {
        padding: 15px 30px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        font-family: "Press Start 2P", cursive;
        font-size: 1rem;
        transition: all 0.3s ease;
        text-shadow: 1px 1px 0px rgba(0, 0, 0, 0.3);
        box-shadow: 0 4px 0 rgba(0, 0, 0, 0.2);
        position: relative;
        top: 0;
        letter-spacing: 1px;
    }
    
    .btn-submit:hover {
        background-color: var(--secondary-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0, 0, 0, 0.2);
    }
    
    .btn-submit:active {
        top: 2px;
        box-shadow: 0 2px 0 rgba(0, 0, 0, 0.2);
    }
    
    .btn-submit:disabled {
        background-color: var(--disabled-color, #ccc);
        cursor: not-allowed;
        box-shadow: none;
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
    
    <!-- Quiz Background Music -->
    <div class="audio-player">
        <audio id="quiz-audio" loop>
            <source src="quiz.mp3" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>
    </div>
    
    <?php include_once 'includes/header.php'; ?>
    
    <div class="main-content" style="padding-top: 40px;">
        <div class="container">
            <!-- Page header removed as requested -->
            
            <div class="quiz-container">
                <div class="quiz-header">
                    <h2 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    
                    <div class="quiz-info">
                        <div class="quiz-points">
                            <i class="material-icons">stars</i>
                            <span><?php echo isset($quiz['points']) ? $quiz['points'] : 10; ?> Points</span>
                        </div>
                        <div class="quiz-timer" id="quiz-timer">
                            <i class="material-icons">timer</i>
                            <span id="timer-display">00:00</span>
                        </div>
                    </div>
                    
                    <div class="quiz-progress">
                        <span>Question <?php echo $current_question + 1; ?> of <?php echo $total_questions; ?></span>
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                    </div>
                </div>
                
                <?php if ($total_questions > 0 && isset($questions[$current_question])): ?>
                    <div class="question-container">
                        <h3 class="question-text"><?php echo htmlspecialchars($questions[$current_question]['question_text']); ?></h3>
                        
                        <form method="POST" action="" class="answer-form" id="quiz-form">
                            <input type="hidden" name="question_id" value="<?php echo $questions[$current_question]['question_id']; ?>">
                            
                            <div class="answer-choices">
                                <div class="choice">
                                    <input type="radio" name="answer" id="answer_a" value="a" required>
                                    <label for="answer_a">
                                        <span class="choice-letter">A</span>
                                        <span class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_a'] ?? ''); ?></span>
                                    </label>
                                </div>
                                
                                <div class="choice">
                                    <input type="radio" name="answer" id="answer_b" value="b" required>
                                    <label for="answer_b">
                                        <span class="choice-letter">B</span>
                                        <span class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_b'] ?? ''); ?></span>
                                    </label>
                                </div>
                                
                                <?php if (!empty($questions[$current_question]['option_c'] ?? '')): ?>
                                <div class="choice">
                                    <input type="radio" name="answer" id="answer_c" value="c">
                                    <label for="answer_c">
                                        <span class="choice-letter">C</span>
                                        <span class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_c'] ?? ''); ?></span>
                                    </label>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($questions[$current_question]['option_d'] ?? '')): ?>
                                <div class="choice">
                                    <input type="radio" name="answer" id="answer_d" value="d">
                                    <label for="answer_d">
                                        <span class="choice-letter">D</span>
                                        <span class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_d'] ?? ''); ?></span>
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="submit-container">
                                <input type="hidden" name="submit_answer" value="1">
                                <button type="submit" class="btn-submit" id="submit-btn">
                                    <?php echo ($current_question >= $total_questions - 1) ? 'Finish Quiz' : 'Next Question'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <p>No questions found for this quiz.</p>
                        <a href="quizzes.php" class="btn-primary">Back to Quizzes</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
    
    <!-- Include matrix background script for quiz page -->
    <script src="matrix-bg.js"></script>
    
    <script>
        // Set up timer, progress, and form handling
        document.addEventListener('DOMContentLoaded', function() {
            // Form submission handling
            const form = document.getElementById('quiz-form');
            if (form) {
                console.log('Quiz form found');
                
                form.addEventListener('submit', function(e) {
                    console.log('Form submit event triggered');
                    const selectedOption = document.querySelector('input[name="answer"]:checked');
                    
                    if (!selectedOption) {
                        e.preventDefault();
                        alert('Please select an answer before submitting.');
                        return false;
                    }
                    
                    console.log('Form being submitted with answer: ' + selectedOption.value);
                    // Allow form to submit naturally
                });
            } else {
                console.error('Quiz form not found!');
            }
            // Set progress bar
            const progressFill = document.getElementById('progress-fill');
            const currentQuestion = <?php echo $current_question; ?>;
            const totalQuestions = <?php echo $total_questions; ?>;
            const progressPercentage = ((currentQuestion + 1) / totalQuestions) * 100;
            progressFill.style.width = progressPercentage + '%';
            
            // Set up timer
            const timerDisplay = document.getElementById('timer-display');
            const timerContainer = document.getElementById('quiz-timer');
            let timeRemaining = <?php echo $time_remaining; ?>;
            
            function updateTimer() {
                if (timeRemaining <= 0) {
                    // Time's up - submit the form automatically
                    clearInterval(timerInterval);
                    const form = document.getElementById('quiz-form');
                    if (form) {
                        console.log('Time expired - submitting form automatically');
                        form.submit();
                    }
                    return;
                }
                
                // Update timer display
                const minutes = Math.floor(timeRemaining / 60);
                const seconds = timeRemaining % 60;
                timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Add warning classes based on time remaining
                if (timeRemaining <= 30) {
                    timerContainer.classList.add('danger');
                } else if (timeRemaining <= 60) {
                    timerContainer.classList.add('warning');
                }
                
                timeRemaining--;
            }
            
            // Initial timer update
            updateTimer();
            
            // Update timer every second
            const timerInterval = setInterval(updateTimer, 1000);
            
            // Audio player functionality
            const audioElement = document.getElementById('quiz-audio');
            let isPlaying = false;
            
            // Auto-play audio when page loads (with user interaction)
            document.body.addEventListener('click', function() {
                if (!isPlaying) {
                    audioElement.play();
                    isPlaying = true;
                }
            }, { once: true });
        });
    </script>
</body>
</html>
