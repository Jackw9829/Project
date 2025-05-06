<?php
// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if level_id is provided
if (!isset($_GET['level_id']) || !is_numeric($_GET['level_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid level ID']);
    exit;
}

$levelId = (int)$_GET['level_id'];

// Get level details
$levelSql = "SELECT level_id, level_name, description, level_order, active 
            FROM levels 
            WHERE level_id = ?";

$levelResult = executeQuery($levelSql, [$levelId]);
$level = null;

if ($levelResult && count($levelResult) > 0) {
    $level = $levelResult[0];
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Level not found']);
    exit;
}

// Get quizzes for this level
$quizzesSql = "SELECT quiz_id, quiz_title, description 
              FROM quizzes 
              WHERE level_id = ? 
              ORDER BY quiz_id ASC";

$quizzesResult = executeQuery($quizzesSql, [$levelId]);
$quizzes = [];

if ($quizzesResult && count($quizzesResult) > 0) {
    $quizzes = $quizzesResult;
}

// Get user progress for this level if user is logged in
$userProgress = null;
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    $userId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : $_SESSION['admin_id'];
    $progressSql = "SELECT is_completed, points_earned 
                   FROM user_levels 
                   WHERE user_id = ? AND level_id = ?";
    
    $progressResult = executeQuery($progressSql, [$userId, $levelId]);
    
    if ($progressResult && count($progressResult) > 0) {
        $userProgress = $progressResult[0];
    }
}

// Prepare response
$response = [
    'level' => $level,
    'quizzes' => $quizzes,
    'userProgress' => $userProgress
];

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
