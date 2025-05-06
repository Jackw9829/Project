<?php
/**
 * Take Challenge Page
 * 
 * This file handles the display and submission of coding challenges.
 * It's adapted from the quiz functionality but tailored for coding challenges.
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

// Get challenge ID from URL parameter
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student_id = $_SESSION['student_id'];

// If no challenge ID provided, redirect to challenges page
if ($challenge_id === 0) {
    header("Location: challenges.php");
    exit();
}

// Variables to store challenge data
$challenge = null;
$questions = [];
$current_question = 0;
$total_questions = 0;
$attempt_id = 0;
$time_remaining = 0;

// Initialize or retrieve current question from session
if (!isset($_SESSION['current_question_' . $challenge_id])) {
    $_SESSION['current_question_' . $challenge_id] = 0;
}
$current_question = $_SESSION['current_question_' . $challenge_id];

// Get questions for this challenge first so we can validate the current question index
try {
    // Add detailed debugging
    error_log("Attempting to fetch challenge questions for challenge_id: $challenge_id");
    
    $sql = "SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_id";
    error_log("SQL Query: $sql with challenge_id: $challenge_id");
    
    $questions_result = executeQuery($sql, [$challenge_id]);
    error_log("Query executed, result: " . print_r($questions_result, true));
    
    if ($questions_result && count($questions_result) > 0) {
        $questions = $questions_result;
        $total_questions = count($questions);
        error_log("Found $total_questions questions for challenge ID $challenge_id");
        error_log("First question data: " . print_r($questions[0], true));
        
        // Reset current question if it's out of bounds
        if ($current_question >= $total_questions) {
            error_log("Current question index ($current_question) is out of bounds, resetting to 0");
            $current_question = 0;
            $_SESSION['current_question_' . $challenge_id] = 0;
        }
    } else {
        // No questions found for this challenge
        $questions = [];
        $total_questions = 0;
        error_log("No questions found for challenge ID $challenge_id");
        
        // Debug: Check if the challenge exists
        $check_sql = "SELECT challenge_id, challenge_name FROM challenges WHERE challenge_id = ?";
        $challenge_check = executeQuery($check_sql, [$challenge_id]);
        error_log("Challenge check: " . print_r($challenge_check, true));
        
        // Debug: Check if any challenge questions exist at all
        $all_questions_sql = "SELECT COUNT(*) as count FROM challenge_questions";
        $all_questions_count = executeQuery($all_questions_sql, []);
        error_log("Total challenge questions in database: " . print_r($all_questions_count, true));
    }
} catch (Exception $e) {
    // Log the error and redirect with error message
    error_log("Error loading challenge questions: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    header("Location: challenges.php?error=" . urlencode("Unable to load challenge questions. Please try again later."));
    exit();
}

// Check if this is a new attempt or continuing an existing one
$sql = "SELECT * FROM challenge_attempts WHERE student_id = ? AND challenge_id = ? AND is_completed = 0 ORDER BY attempt_id DESC LIMIT 1";
$existing_attempt = executeQuery($sql, [$student_id, $challenge_id]);

if ($existing_attempt && count($existing_attempt) > 0) {
    // Continue existing attempt
    $attempt_id = $existing_attempt[0]['attempt_id'];
    $start_time = strtotime($existing_attempt[0]['start_time']);
    
    // Get challenge details
    $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
    $challenge_result = executeQuery($sql, [$challenge_id]);
    
    if ($challenge_result && count($challenge_result) > 0) {
        $challenge = $challenge_result[0];
        // Set default time limit of 30 minutes if not specified, is null, is zero, or is empty
        $time_limit = (isset($challenge['time_limit']) && $challenge['time_limit'] !== null && !empty($challenge['time_limit']) && $challenge['time_limit'] > 0) 
            ? intval($challenge['time_limit']) 
            : 30;
        error_log("Existing attempt - Challenge time limit: $time_limit minutes (challenge_id: $challenge_id)");
        $time_limit_seconds = $time_limit * 60;
        $elapsed_seconds = time() - $start_time;
        $time_remaining = max(0, $time_limit_seconds - $elapsed_seconds);
        
        // If time has expired, mark attempt as completed
        if ($time_remaining <= 0) {
            $sql = "UPDATE challenge_attempts SET is_completed = 1, end_time = NOW() WHERE attempt_id = ?";
            executeQuery($sql, [$attempt_id]);
            header("Location: challenge_results.php?attempt_id=" . $attempt_id);
            exit();
        }
    } else {
        // Challenge not found
        header("Location: challenges.php");
        exit();
    }
} else {
    // Start new attempt
    $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
    $challenge_result = executeQuery($sql, [$challenge_id]);
    
    if ($challenge_result && count($challenge_result) > 0) {
        $challenge = $challenge_result[0];
        
        // Set default time limit of 30 minutes if not specified, is null, is zero, or is empty
        $time_limit = (isset($challenge['time_limit']) && $challenge['time_limit'] !== null && !empty($challenge['time_limit']) && $challenge['time_limit'] > 0) 
            ? intval($challenge['time_limit']) 
            : 30;
        error_log("Challenge time limit: $time_limit minutes (challenge_id: $challenge_id)");
        $time_remaining = $time_limit * 60; // Convert minutes to seconds
        
        // Check if this is a retry (student has already completed this challenge before)
        $checkPreviousAttemptSql = "SELECT COUNT(*) as attempt_count FROM challenge_attempts 
                                   WHERE student_id = ? AND challenge_id = ? AND is_completed = 1";
        $previousAttempts = executeQuery($checkPreviousAttemptSql, [$student_id, $challenge_id]);
        $isRetry = ($previousAttempts && $previousAttempts[0]['attempt_count'] > 0);
        
        // Create new attempt
        try {
            // Insert the attempt using insertData function
            $attemptData = [
                'student_id' => $student_id,
                'challenge_id' => $challenge_id,
                'start_time' => date('Y-m-d H:i:s'),
                'is_completed' => 0,
                'points_awarded' => $isRetry ? 0 : 1 // Set points_awarded to 0 for retries
            ];
            
            $attempt_id = insertData('challenge_attempts', $attemptData);
            
            // Set time remaining based on challenge time limit (default 30 minutes if not specified, is null, is zero, or is empty)
            $time_limit = (isset($challenge['time_limit']) && $challenge['time_limit'] !== null && !empty($challenge['time_limit']) && $challenge['time_limit'] > 0) 
                ? intval($challenge['time_limit']) 
                : 30;
            error_log("Challenge time limit: $time_limit minutes (challenge_id: $challenge_id)");
            $time_remaining = $time_limit * 60; // Convert to seconds
        } catch (Exception $e) {
            error_log("Error creating new attempt: " . $e->getMessage());
            header("Location: challenges.php?error=" . urlencode("Unable to start new attempt. Please try again later."));
            exit();
        }
    } else {
        // Challenge not found
        header("Location: challenges.php");
        exit();
    }
}

// We already fetched the questions earlier to validate the current question index

// Process answer submission for multiple choice questions
// Debug all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Current URL: " . $_SERVER['REQUEST_URI']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_answer'])) {
    // Add debugging
    error_log("Processing answer submission");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Current question: $current_question, Total questions: $total_questions");
    
    // Get question ID and selected answer
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $selected_answer = isset($_POST['answer']) ? $_POST['answer'] : '';
    
    if ($question_id > 0 && !empty($selected_answer)) {
        error_log("Question ID: $question_id, Selected Answer: $selected_answer");
        
        // Get correct answer
        $sql = "SELECT correct_answer, points FROM challenge_questions WHERE question_id = ?";
        $question_result = executeQuery($sql, [$question_id]);
        
        error_log("Question result: " . print_r($question_result, true));
        
        if ($question_result && count($question_result) > 0) {
            $correct_answer = $question_result[0]['correct_answer'];
            $points = $question_result[0]['points'];
            $is_correct = ($selected_answer === $correct_answer);
            $earned_points = $is_correct ? $points : 0;
            
            error_log("Correct answer: $correct_answer, Is correct: " . ($is_correct ? 'yes' : 'no') . ", Points earned: $earned_points");
            
            try {
                // Save the answer to challenge_answers table with detailed error handling
                error_log("Attempting to save answer to challenge_answers table with: attempt_id=$attempt_id, question_id=$question_id, selected_answer=$selected_answer");
                
                // Check if the challenge_answers table exists and has the right structure
                $check_table_sql = "SHOW TABLES LIKE 'challenge_answers'";
                $table_exists = executeQuery($check_table_sql, []);
                
                if (empty($table_exists)) {
                    error_log("challenge_answers table doesn't exist, creating it now");
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS `challenge_answers` (
                        `answer_id` int NOT NULL AUTO_INCREMENT,
                        `attempt_id` int NOT NULL,
                        `question_id` int NOT NULL,
                        `selected_answer` varchar(255) DEFAULT NULL,
                        `is_correct` tinyint(1) DEFAULT '0',
                        `points_earned` int DEFAULT '0',
                        `answer_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`answer_id`),
                        KEY `fk_challenge_answers_attempt` (`attempt_id`),
                        KEY `fk_challenge_answers_question` (`question_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";
                    executeQuery($create_table_sql, []);
                    error_log("Created challenge_answers table");
                }
                
                // Check if an answer for this question and attempt already exists
                $check_existing_sql = "SELECT answer_id FROM challenge_answers WHERE attempt_id = ? AND question_id = ?";
                $existing_answer = executeQuery($check_existing_sql, [$attempt_id, $question_id]);
                
                if (!empty($existing_answer)) {
                    // Update existing answer
                    $sql = "UPDATE challenge_answers SET selected_answer = ?, is_correct = ?, points_earned = ? WHERE attempt_id = ? AND question_id = ?";
                    executeQuery($sql, [
                        $selected_answer,
                        $is_correct ? 1 : 0,
                        $earned_points,
                        $attempt_id,
                        $question_id
                    ]);
                    error_log("Updated existing answer in challenge_answers table");
                } else {
                    // Insert the answer
                    $sql = "INSERT INTO challenge_answers (attempt_id, question_id, selected_answer, is_correct, points_earned) 
                            VALUES (?, ?, ?, ?, ?)";
                    executeQuery($sql, [
                        $attempt_id, 
                        $question_id, 
                        $selected_answer, 
                        $is_correct ? 1 : 0, 
                        $earned_points
                    ]);
                    error_log("Answer successfully saved to challenge_answers table");
                }
                
                // Update points in the challenge_attempts table
                // Only add points if the answer is correct and this is a new answer (not an update)
                if ($is_correct && empty($existing_answer)) {
                    $sql = "UPDATE challenge_attempts SET points = points + ? WHERE attempt_id = ?";
                    executeQuery($sql, [$earned_points, $attempt_id]);
                    error_log("Points updated successfully with $earned_points points");
                } else {
                    error_log("No points update: " . ($is_correct ? 'correct' : 'incorrect') . 
                              " answer, " . (empty($existing_answer) ? 'new' : 'existing') . " answer");
                }
                
                // Move to next question or complete the challenge
                $_SESSION['current_question_' . $challenge_id]++;
                if ($_SESSION['current_question_' . $challenge_id] >= $total_questions) {
                    // Challenge completed
                    $sql = "UPDATE challenge_attempts SET is_completed = 1, end_time = NOW() WHERE attempt_id = ?";
                    executeQuery($sql, [$attempt_id]);
                    
                    // Check if this is a retry attempt where points should not be awarded
                    $checkPointsAwardedSql = "SELECT points_awarded FROM challenge_attempts WHERE attempt_id = ?";
                    $pointsAwardedResult = executeQuery($checkPointsAwardedSql, [$attempt_id]);
                    $shouldAwardPoints = ($pointsAwardedResult && isset($pointsAwardedResult[0]['points_awarded']) && $pointsAwardedResult[0]['points_awarded'] == 1);
                    
                    // Award 20 points for completing the challenge (only for first attempt)
                    if ($shouldAwardPoints) {
                        $challengeCompletionPoints = 20; // Fixed 20 points for challenge completion
                        $updateStudentPointsSql = "UPDATE students SET total_points = total_points + ? WHERE student_id = ?";
                        executeQuery($updateStudentPointsSql, [$challengeCompletionPoints, $_SESSION['student_id']]);
                        error_log("Awarded $challengeCompletionPoints points to student ID: {$_SESSION['student_id']} for completing challenge");
                        
                        // Log the points in activity_log
                        $activityLogSql = "INSERT INTO activity_log (student_id, activity_type, description, points_earned, created_at) 
                                           VALUES (?, 'challenge_completion', ?, ?, NOW())";
                        $challengeName = $challenge['challenge_name'] ?? "Challenge #$challenge_id";
                        executeQuery($activityLogSql, [$_SESSION['student_id'], "Completed $challengeName", $challengeCompletionPoints]);
                    } else {
                        error_log("No points awarded for retry challenge attempt for student ID: {$_SESSION['student_id']}");
                    }
                    
                    // Update leaderboard for the student
                    require_once 'leaderboard.php';
                    updateUserLeaderboardEntry($_SESSION['student_id']);
                    error_log("Leaderboard updated for student ID: {$_SESSION['student_id']} after completing challenge");
                    
                    // Log detailed information about the completion
                    error_log("Challenge completed - attempt_id: $attempt_id, challenge_id: $challenge_id");
                    error_log("Redirecting to challenge_results.php?attempt_id=$attempt_id");
                    
                    // Add debug information
                    error_log("DEBUG: About to redirect to challenge_results.php with attempt_id=$attempt_id");
                    
                    // Store the attempt ID in a session variable as a backup
                    $_SESSION['last_completed_challenge_attempt'] = $attempt_id;
                    
                    // Ensure all database operations are complete
                    // Give a slight delay to ensure all DB operations complete
                    usleep(500000); // 0.5 second delay
                    
                    // Force redirect to results page regardless of headers
                    // First, ensure all output buffers are flushed and closed
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    // Start a new output buffer
                    ob_start();
                    
                    // Use multiple redirection methods for redundancy
                    echo "<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Challenge Completed</title>
                        <meta http-equiv='refresh' content='0;url=challenge_results.php?attempt_id=$attempt_id'>
                        <script>window.location.href = 'challenge_results.php?attempt_id=$attempt_id';</script>
                    </head>
                    <body style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>
                        <h2>Challenge Completed!</h2>
                        <p>You are being redirected to the results page...</p>
                        <p>If you are not automatically redirected, please <a href='challenge_results.php?attempt_id=$attempt_id'>click here</a> to view your results.</p>
                    </body>
                    </html>";
                    
                    // Flush the buffer and end the script
                    ob_end_flush();
                    exit();
                } else {
                    // Redirect to next question
                    header("Location: take_challenge.php?id=$challenge_id");
                    exit();
                }
            } catch (Exception $e) {
                error_log("Error processing answer: " . $e->getMessage());
            }

        }
    }
}

// Process code submission (for future coding challenges)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_code'])) {
    // Add debugging
    error_log("Processing code submission");
    error_log("POST data: " . print_r($_POST, true));
    
    $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
    $submitted_code = isset($_POST['code']) ? $_POST['code'] : '';
    
    if ($question_id > 0 && !empty($submitted_code)) {
        error_log("Question ID: $question_id, Submitted Code: " . substr($submitted_code, 0, 50) . "...");
        
        // Get the question details
        $sql = "SELECT * FROM challenge_questions WHERE question_id = ?";
        $question_result = executeQuery($sql, [$question_id]);
        
        error_log("Question result: " . print_r($question_result, true));
        
        if ($question_result && count($question_result) > 0) {
            $test_cases = json_decode($question_result[0]['test_cases'], true);
            $points = $question_result[0]['points'];
            
            // Simple evaluation - in a real system, this would be more sophisticated
            // For now, we'll just check if the code contains certain keywords
            $expected_keywords = explode(',', $question_result[0]['expected_output']);
            $is_correct = true;
            
            foreach ($expected_keywords as $keyword) {
                if (stripos($submitted_code, trim($keyword)) === false) {
                    $is_correct = false;
                    break;
                }
            }
            
            $earned_points = $is_correct ? $points : 0;
            
            error_log("Is correct: " . ($is_correct ? 'yes' : 'no') . ", Points earned: $earned_points");
            
            // Store the answer in challenge_answers table
            try {
                // Check if an answer for this question and attempt already exists
                $check_existing_sql = "SELECT answer_id FROM challenge_answers WHERE attempt_id = ? AND question_id = ?";
                $existing_answer = executeQuery($check_existing_sql, [$attempt_id, $question_id]);
                
                if (!empty($existing_answer)) {
                    // Update existing answer
                    $sql = "UPDATE challenge_answers SET selected_answer = ?, is_correct = ?, points_earned = ? WHERE attempt_id = ? AND question_id = ?";
                    $feedback = $is_correct ? "Great job! Your solution works correctly." : "Your solution doesn't meet all requirements. Try again!";
                    executeQuery($sql, [$submitted_code, $is_correct ? 1 : 0, $earned_points, $attempt_id, $question_id]);
                    error_log("Updated existing coding answer in challenge_answers table");
                } else {
                    // Insert new answer
                    $sql = "INSERT INTO challenge_answers (attempt_id, question_id, selected_answer, is_correct, points_earned) 
                            VALUES (?, ?, ?, ?, ?)";
                    $feedback = $is_correct ? "Great job! Your solution works correctly." : "Your solution doesn't meet all requirements. Try again!";
                    executeQuery($sql, [$attempt_id, $question_id, $submitted_code, $is_correct ? 1 : 0, $earned_points]);
                }
                
                // Update points in the challenge_attempts table
                // Only add points if the answer is correct and this is a new answer (not an update)
                if ($is_correct && empty($existing_answer)) {
                    $sql = "UPDATE challenge_attempts SET points = points + ? WHERE attempt_id = ?";
                    executeQuery($sql, [$earned_points, $attempt_id]);
                    error_log("Points updated successfully with $earned_points points");
                } else {
                    error_log("No points update: " . ($is_correct ? 'correct' : 'incorrect') . 
                              " answer, " . (empty($existing_answer) ? 'new' : 'existing') . " answer");
                }
            } catch (Exception $e) {
                error_log("Error updating points: " . $e->getMessage());
            }
            
            // Move to next question or finish challenge
            $current_question++;
            // Save current question to session
            $_SESSION['current_question_' . $challenge_id] = $current_question;
            error_log("Moving to question: $current_question of $total_questions");
            
            // Check if we've reached the end of questions or if the next question doesn't exist
            if ($current_question >= $total_questions || !isset($questions[$current_question])) {
                // Mark attempt as completed
                $sql = "UPDATE challenge_attempts SET is_completed = 1, end_time = NOW() WHERE attempt_id = ?";
                executeQuery($sql, [$attempt_id]);
                
                // Update leaderboard for the student
                require_once 'leaderboard.php';
                updateUserLeaderboardEntry($_SESSION['student_id']);
                error_log("Leaderboard updated for student ID: {$_SESSION['student_id']} after completing challenge");
                
                // Log detailed information about the completion
                error_log("Challenge completed - attempt_id: $attempt_id, challenge_id: $challenge_id");
                error_log("Redirecting to challenge_results.php?attempt_id=$attempt_id");
                
                // Add debug information
                error_log("DEBUG: About to redirect to challenge_results.php with attempt_id=$attempt_id");
                
                // Store the attempt ID in a session variable as a backup
                $_SESSION['last_completed_challenge_attempt'] = $attempt_id;
                
                // Ensure all database operations are complete
                // Give a slight delay to ensure all DB operations complete
                usleep(500000); // 0.5 second delay
                
                // Force redirect to results page regardless of headers
                // First, ensure all output buffers are flushed and closed
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Start a new output buffer
                ob_start();
                
                // Use multiple redirection methods for redundancy
                echo "<!DOCTYPE html>
                <html>
                <head>
                    <title>Challenge Completed</title>
                    <meta http-equiv='refresh' content='0;url=challenge_results.php?attempt_id=$attempt_id'>
                    <script>window.location.href = 'challenge_results.php?attempt_id=$attempt_id';</script>
                </head>
                <body style='font-family: Arial, sans-serif; text-align: center; margin-top: 50px;'>
                    <h2>Challenge Completed!</h2>
                    <p>You are being redirected to the results page...</p>
                    <p>If you are not automatically redirected, please <a href='challenge_results.php?attempt_id=$attempt_id'>click here</a> to view your results.</p>
                </body>
                </html>";
                
                // Flush the buffer and end the script
                ob_end_flush();
                exit();
            }
        } else {
            error_log("No question found with ID: $question_id");
        }
    } else {
        error_log("Invalid question_id or no code submitted");
    }
}

// Page title
$pageTitle = "Taking Challenge: " . htmlspecialchars($challenge['challenge_name']);

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
    body.dark-mode {
        --bronze-color: #e0916b;
        --disabled-color: #555555;
        --button-text-color: #ffffff;
        --progress-bg-color: rgba(255, 255, 255, 0.1);
    }
    
    /* Reusing quiz container styles for challenge container */
    .quiz-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 30px;
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
    }
    
    /* Reusing quiz header styles for challenge header */
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
    
    .code-editor {
        margin-bottom: 30px;
        margin-top: 20px;
        border: 2px solid var(--primary-color);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.3);
    }
    
    .code-editor textarea {
        width: 100%;
        min-height: 300px;
        font-family: "Courier New", monospace;
        padding: 15px;
        background-color: #1e1e1e;
        color: #d4d4d4;
        border: none;
        border-radius: var(--border-radius);
        resize: vertical;
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
    
    .test-cases {
        margin-top: 20px;
        padding: 15px;
        background-color: rgba(0, 0, 0, 0.1);
        border-radius: var(--border-radius);
    }
    
    .test-case-title {
        font-size: 1.1rem;
        margin-bottom: 10px;
        color: var(--primary-color);
    }
    
    .test-case {
        margin-bottom: 10px;
        padding: 10px;
        background-color: rgba(0, 0, 0, 0.05);
        border-radius: var(--border-radius);
    }
    
    .test-case-label {
        font-weight: bold;
        margin-right: 10px;
    }
    
    /* Quiz form and choices styles */
    .quiz-form {
        margin-top: 20px;
    }
    
    .choices-container {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .choice {
        position: relative;
    }
    
    .choice input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .choice-label {
        display: flex;
        align-items: center;
        padding: 12px;
        background-color: rgba(0, 0, 0, 0.1);
        border-radius: var(--border-radius);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .choice input[type="radio"]:checked + .choice-label {
        background-color: rgba(var(--primary-color-rgb), 0.2);
        box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.5);
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
    .choice:nth-child(1) input[type="radio"]:checked + .choice-label .choice-letter {
        box-shadow: 0 0 10px #FF5252;
    }
    
    .choice:nth-child(2) input[type="radio"]:checked + .choice-label .choice-letter {
        box-shadow: 0 0 10px #4CAF50;
    }
    
    .choice:nth-child(3) input[type="radio"]:checked + .choice-label .choice-letter {
        box-shadow: 0 0 10px #2196F3;
    }
    
    .choice:nth-child(4) input[type="radio"]:checked + .choice-label .choice-letter {
        box-shadow: 0 0 10px #FFC107;
    }
    
    .error-message {
        text-align: center;
        padding: 30px;
    }
    
    .error-message p {
        margin-bottom: 20px;
        color: var(--text-color);
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
    
    <!-- Challenge Background Music -->
    <div class="audio-player">
        <audio id="quiz-audio" loop>
            <source src="audio/quiz-background.mp3" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>
        <button id="toggle-audio" class="audio-control" title="Toggle background music">
            <i class="material-icons">volume_up</i>
        </button>
    </div>
    
    <?php include_once 'includes/header.php'; ?>
    
    <div class="main-content" style="padding-top: 40px;">
        <div class="container">
            <!-- Page header removed as requested -->
            
            <div class="quiz-container">
                <div class="quiz-header">
                    <h2 class="quiz-title"><?php echo htmlspecialchars($challenge['challenge_name']); ?></h2>
                    
                    <div class="quiz-meta">
                        <div class="quiz-points">
                            <i class="material-icons">stars</i>
                            <span><?php echo isset($challenge['points']) ? $challenge['points'] : 10; ?> Points</span>
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
                
                <?php 
                error_log("Current question index: $current_question");
                error_log("Total questions: $total_questions");
                error_log("Questions array: " . print_r($questions, true));
                
                if (isset($questions[$current_question])): 
                    error_log("Question at index $current_question exists");
                ?>
                    <div class="question-container">
                        <h3 class="question-text"><?php echo htmlspecialchars($questions[$current_question]['question_text']); ?></h3>
                        
                        <!-- Display multiple choice options for challenge questions -->
                        <div class="options-container">
                            <?php 
                            // Check if this is a multiple-choice question (has options)
                            if (!empty($questions[$current_question]['option_a'])): 
                            ?>
                                <form method="POST" action="" class="quiz-form" id="challenge-form">
                                    <input type="hidden" name="question_id" value="<?php echo $questions[$current_question]['question_id']; ?>">
                                    
                                    <div class="choices-container">
                                        <?php if (!empty($questions[$current_question]['option_a'])): ?>
                                        <div class="choice">
                                            <input type="radio" id="answer_a" name="answer" value="a" required>
                                            <label for="answer_a" class="choice-label">
                                                <div class="choice-letter">A</div>
                                                <div class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_a']); ?></div>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($questions[$current_question]['option_b'])): ?>
                                        <div class="choice">
                                            <input type="radio" id="answer_b" name="answer" value="b">
                                            <label for="answer_b" class="choice-label">
                                                <div class="choice-letter">B</div>
                                                <div class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_b']); ?></div>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($questions[$current_question]['option_c'])): ?>
                                        <div class="choice">
                                            <input type="radio" id="answer_c" name="answer" value="c">
                                            <label for="answer_c" class="choice-label">
                                                <div class="choice-letter">C</div>
                                                <div class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_c']); ?></div>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($questions[$current_question]['option_d'])): ?>
                                        <div class="choice">
                                            <input type="radio" id="answer_d" name="answer" value="d">
                                            <label for="answer_d" class="choice-label">
                                                <div class="choice-letter">D</div>
                                                <div class="choice-text"><?php echo htmlspecialchars($questions[$current_question]['option_d']); ?></div>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="submit-container">
                                        <input type="hidden" name="submit_answer" value="1">
                                        <button type="submit" id="submit-answer-btn" class="btn-submit">Submit Answer</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <!-- Fallback for coding challenges if they're added later -->
                                <form method="POST" class="code-form">
                                    <input type="hidden" name="question_id" value="<?php echo $questions[$current_question]['question_id']; ?>">
                                    
                                    <div class="code-editor">
                                        <textarea name="code" id="code-editor" placeholder="Write your code here..."></textarea>
                                    </div>
                                    
                                    <div class="submit-container">
                                        <button type="submit" name="submit_code" class="btn-submit">Submit Solution</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: 
                    error_log("Question at index $current_question does NOT exist");
                    error_log("Debug info - current_question: $current_question, total_questions: $total_questions");
                    error_log("Session data: " . print_r($_SESSION, true));
                ?>
                    <div class="error-message">
                        <p>No questions found for this challenge.</p>
                        <p><small>Debug: Current question index: <?php echo $current_question; ?>, Total questions: <?php echo $total_questions; ?></small></p>
                        <a href="challenges.php" class="btn-primary">Back to Challenges</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
    
    <!-- Include matrix background script for challenge page -->
    <script src="matrix-bg.js"></script>
    
    <script>
        // Set up progress, timer, and form handling
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded');
            
            // Simple form validation
            const form = document.getElementById('challenge-form');
            if (form) {
                console.log('Challenge form found');
                
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
                console.error('Challenge form not found!');
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
                    const form = document.getElementById('challenge-form');
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
            const toggleButton = document.getElementById('toggle-audio');
            let isPlaying = false;
            
            // Auto-play audio when page loads (with user interaction)
            document.body.addEventListener('click', function() {
                if (!isPlaying) {
                    audioElement.play();
                    isPlaying = true;
                    toggleButton.innerHTML = '<i class="material-icons">volume_up</i>';
                }
            }, { once: true });
            
            // Toggle audio play/pause
            toggleButton.addEventListener('click', function() {
                if (isPlaying) {
                    audioElement.pause();
                    toggleButton.innerHTML = '<i class="material-icons">volume_off</i>';
                } else {
                    audioElement.play();
                    toggleButton.innerHTML = '<i class="material-icons">volume_up</i>';
                }
                isPlaying = !isPlaying;
            });
        });
    </script>
</body>
</html>
