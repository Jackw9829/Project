<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = $_POST['quiz_id'] ?? null;
$question_id = $_POST['question_id'] ?? null;
$selected_option_id = $_POST['answer'] ?? null;
$is_final = isset($_POST['final']) && $_POST['final'] === 'true';

if (!$quiz_id || !$question_id || !$selected_option_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get or create quiz attempt
    $stmt = $pdo->prepare("
        SELECT attempt_id 
        FROM quiz_attempts 
        WHERE user_id = ? AND quiz_id = ? AND score IS NULL
        ORDER BY attempted_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attempt) {
        // Create new attempt
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, attempted_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $quiz_id]);
        $attempt_id = $pdo->lastInsertId();
    } else {
        $attempt_id = $attempt['attempt_id'];
    }

    // Save the answer
    $stmt = $pdo->prepare("
        SELECT option_text, is_correct 
        FROM question_options 
        WHERE option_id = ? AND question_id = ?
    ");
    $stmt->execute([$selected_option_id, $question_id]);
    $option = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$option) {
        throw new Exception('Invalid option selected');
    }

    // Delete any existing answer for this question in this attempt
    $stmt = $pdo->prepare("
        DELETE FROM quiz_answers 
        WHERE attempt_id = ? AND question_id = ?
    ");
    $stmt->execute([$attempt_id, $question_id]);

    // Save the new answer
    $stmt = $pdo->prepare("
        INSERT INTO quiz_answers (attempt_id, question_id, selected_answer, is_correct, selected_option_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $attempt_id,
        $question_id,
        $option['option_text'],
        $option['is_correct'],
        $selected_option_id
    ]);

    if ($is_final) {
        // Calculate score by summing points for correct answers
        $stmt = $pdo->prepare("
            SELECT SUM(q.points) as total_points
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.question_id
            WHERE qa.attempt_id = ? AND qa.is_correct = 1
        ");
        $stmt->execute([$attempt_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $score = $result['total_points'] ?? 0;

        // Update attempt with score
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET score = ?, completed_at = NOW()
            WHERE attempt_id = ?
        ");
        $stmt->execute([$score, $attempt_id]);

        // Update leaderboard
        $stmt = $pdo->prepare("
            INSERT INTO leaderboard (user_id, total_score, total_quizzes_completed)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE
                total_score = total_score + VALUES(total_score),
                total_quizzes_completed = total_quizzes_completed + 1
        ");
        $stmt->execute([$user_id, $score]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in submit_answer.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
