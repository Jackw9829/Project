<?php
session_start();
require_once 'config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['quiz_id']) || !isset($_GET['current_question']) || !isset($_GET['direction'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

try {
    // Get all questions for this quiz ordered by question_id
    $stmt = $pdo->prepare("
        SELECT question_id
        FROM questions
        WHERE quiz_id = ?
        ORDER BY question_id ASC
    ");
    $stmt->execute([$_GET['quiz_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find the current question's index
    $currentIndex = array_search($_GET['current_question'], $questions);
    
    if ($currentIndex === false) {
        echo json_encode(['error' => 'Current question not found']);
        exit;
    }
    
    // Calculate the next/previous question index
    if ($_GET['direction'] === 'next') {
        $newIndex = $currentIndex + 1;
    } else {
        $newIndex = $currentIndex - 1;
    }
    
    // Check if the new index is valid
    if ($newIndex >= 0 && $newIndex < count($questions)) {
        echo json_encode(['question_id' => $questions[$newIndex]]);
    } else {
        echo json_encode(['error' => 'No more questions in this direction']);
    }
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
