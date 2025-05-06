<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'teacher' && $_SESSION['user_type'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

// Check if quiz_id was provided via POST
if (!isset($_POST['quiz_id'])) {
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}

$quiz_id = $_POST['quiz_id'];
$teacher_id = $_SESSION['user_id'];

try {
    // If user is a teacher, verify they own the quiz
    if ($_SESSION['user_type'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT teacher_id FROM quizzes WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $quiz = $stmt->fetch();

        if (!$quiz || $quiz['teacher_id'] != $teacher_id) {
            throw new Exception('Unauthorized access');
        }
    }

    // Begin transaction
    $pdo->beginTransaction();

    // 1. Get all questions for this quiz
    $stmt = $pdo->prepare("SELECT question_id FROM questions WHERE quiz_id = ?");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 2. Delete question options for all questions in this quiz
    if (!empty($questions)) {
        $placeholders = str_repeat('?,', count($questions) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id IN ($placeholders)");
        $result = $stmt->execute($questions);
        error_log("Deleted question_options for questions. Rows affected: " . $stmt->rowCount());
    }

    // 3. Delete questions
    $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
    $result = $stmt->execute([$quiz_id]);
    error_log("Deleted questions for quiz $quiz_id. Rows affected: " . $stmt->rowCount());

    // 4. Finally, delete the quiz
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
    $result = $stmt->execute([$quiz_id]);
    error_log("Deleted quiz $quiz_id. Rows affected: " . $stmt->rowCount());

    // Commit transaction
    $pdo->commit();
    error_log("Successfully completed deletion of quiz $quiz_id");

    // Set success message
    $_SESSION['success'] = "Quiz deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMessage = "Error deleting quiz $quiz_id: " . $e->getMessage();
    error_log($errorMessage);
    
    // Get the last SQL error if available
    $errorInfo = $stmt->errorInfo();
    if ($errorInfo[0] !== '00000') {
        error_log("SQL Error: " . implode(', ', $errorInfo));
    }
    
    // Set error message
    $_SESSION['error'] = "Error deleting quiz. Please try again.";
}

// Always redirect back to dashboard
header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
exit();
?>
