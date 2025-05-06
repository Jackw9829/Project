<?php
session_start();
error_log("START_QUIZ_DEBUG: Script started");
error_log("START_QUIZ_DEBUG: SESSION: " . print_r($_SESSION, true));
error_log("START_QUIZ_DEBUG: GET: " . print_r($_GET, true));
require_once 'config/db_connect.php';

// Ensure error reporting is on
ini_set('display_errors', 1);
error_reporting(E_ALL);

function debug_log($message) {
    // Log to error log instead of echoing to browser
    error_log("[START_QUIZ_DEBUG] " . $message);
}

// Add error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    debug_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false; // Let PHP handle the error after logging
}, E_ALL);

// Database connection check
try {
    $stmt = $pdo->query("SELECT 1");
    debug_log("Database connection successful");
} catch (PDOException $e) {
    debug_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed");
}

debug_log("Starting quiz attempt...");
debug_log("Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
debug_log("GET id: " . (isset($_GET['id']) ? $_GET['id'] : 'not set'));

// Detailed session and GET parameter logging
debug_log("Full SESSION contents: " . print_r($_SESSION, true));
debug_log("Full GET contents: " . print_r($_GET, true));

// Validate session and GET parameters
if (!isset($_SESSION['user_id'])) {
    debug_log("CRITICAL: No user_id in session");
    $_SESSION['error'] = "User not logged in. Please log in first.";
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    debug_log("CRITICAL: No quiz ID provided in GET parameters");
    $_SESSION['error'] = "No quiz ID provided. Cannot start quiz.";
    header('Location: dashboard.php');
    exit();
}

// Validate quiz ID is numeric
if (!is_numeric($_GET['id'])) {
    debug_log("CRITICAL: Invalid quiz ID format: " . $_GET['id']);
    $_SESSION['error'] = "Invalid quiz ID format.";
    header('Location: dashboard.php');
    exit();
}

$quiz_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

debug_log("Validated Quiz ID: $quiz_id, User ID: $user_id");

try {
    // Detailed quiz existence check
    $stmt = $pdo->prepare("
        SELECT q.quiz_id, q.title, q.due_date, q.is_active,
               (SELECT COUNT(*) FROM questions WHERE quiz_id = q.quiz_id) as has_questions
        FROM quizzes q
        WHERE q.quiz_id = ?
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log raw SQL query and parameters
    debug_log("Quiz Query SQL: " . $stmt->queryString);
    debug_log("Quiz Query Parameters: " . json_encode([$quiz_id]));
    debug_log("Quiz data: " . print_r($quiz, true));
    debug_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));

    if (!$quiz) {
        debug_log("Quiz not found");
        $_SESSION['error'] = "Quiz not found. Please check the quiz ID.";
        header('Location: dashboard.php');
        exit();
    }

    // Check if quiz is active and not past due date
    if (!$quiz['is_active'] || ($quiz['due_date'] && strtotime($quiz['due_date']) < time())) {
        debug_log("Quiz not available. Active: {$quiz['is_active']}, Due date: {$quiz['due_date']}");
        $_SESSION['error'] = "Quiz is not currently available.";
        header('Location: dashboard.php');
        exit();
    }

    // Check if quiz has questions
    if ($quiz['has_questions'] == 0) {
        debug_log("Quiz has no questions");
        $_SESSION['error'] = "This quiz has no questions and cannot be started.";
        header('Location: dashboard.php');
        exit();
    }

    // Check if student already completed this quiz
    $stmt = $pdo->prepare("
        SELECT attempt_id, score, attempted_at, 
               (SELECT COUNT(*) FROM quiz_answers WHERE attempt_id = qa.attempt_id) as answer_count
        FROM quiz_attempts qa 
        WHERE user_id = ? AND quiz_id = ?
        ORDER BY attempted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $existing_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    debug_log("Detailed Existing Attempt: " . print_r($existing_attempt, true));

    // Determine how to handle the existing attempt
    if ($existing_attempt) {
        // If the attempt has a score, it's completed
        if ($existing_attempt['score'] !== null) {
            debug_log("Quiz already completed");
            $_SESSION['error'] = "You have already completed this quiz.";
            header('Location: dashboard.php');
            exit();
        }

        // If no answers have been recorded, use the existing attempt
        if ($existing_attempt['answer_count'] == 0) {
            $attempt_id = $existing_attempt['attempt_id'];
            debug_log("Reusing existing incomplete attempt: $attempt_id");
        } else {
            // Create a new attempt if previous attempt has some answers
            $stmt = $pdo->prepare("
                INSERT INTO quiz_attempts (user_id, quiz_id, attempted_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $quiz_id]);
            $attempt_id = $pdo->lastInsertId();
            debug_log("Created new attempt due to previous incomplete attempt: $attempt_id");
        }
    } else {
        // No existing attempt, create a new one
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, attempted_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $quiz_id]);
        $attempt_id = $pdo->lastInsertId();
        debug_log("Created new attempt: $attempt_id");
    }

    // Store attempt_id in session
    $_SESSION['current_attempt'] = $attempt_id;
    debug_log("Stored attempt_id in session: " . $_SESSION['current_attempt']);

    // Get first question
    $stmt = $pdo->prepare("
        SELECT question_id 
        FROM questions 
        WHERE quiz_id = ? 
        ORDER BY question_id ASC 
        LIMIT 1
    ");
    $stmt->execute([$quiz_id]);
    $first_question = $stmt->fetch(PDO::FETCH_ASSOC);

    debug_log("First question query executed with quiz_id: $quiz_id");
    debug_log("First question query result: " . print_r($first_question, true));

    if ($first_question) {
        $redirect_url = "attempt_quiz.php?id=$quiz_id&question=" . $first_question['question_id'];
        debug_log("Redirecting to: $redirect_url");
        header("Location: $redirect_url");
        exit();
    } else {
        debug_log("No first question found");
        $_SESSION['error'] = "No questions found for this quiz.";
        header('Location: dashboard.php');
        exit();
    }

} catch (PDOException $e) {
    debug_log("PDO Error in start_quiz.php: " . $e->getMessage());
    debug_log("Error details: " . print_r($e, true));
    $_SESSION['error'] = "A database error occurred while starting the quiz.";
    header('Location: dashboard.php');
    exit();
} catch (Exception $e) {
    debug_log("Unexpected Error in start_quiz.php: " . $e->getMessage());
    debug_log("Error details: " . print_r($e, true));
    $_SESSION['error'] = "An unexpected error occurred while starting the quiz.";
    header('Location: dashboard.php');
    exit();
}
