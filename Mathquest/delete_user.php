<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No user specified']);
    exit();
}

$user_id = $_POST['user_id'];

try {
    // Start transaction
    $pdo->beginTransaction();

    // Delete user's quiz submissions
    $stmt = $pdo->prepare("DELETE FROM submissions WHERE student_id = ?");
    $stmt->execute([$user_id]);

    // Delete user's quizzes if they are a teacher
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE teacher_id = ?");
    $stmt->execute([$user_id]);

    // Delete user's activity logs
    $stmt = $pdo->prepare("DELETE FROM user_activity WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Delete user's leaderboard entry
    $stmt = $pdo->prepare("DELETE FROM leaderboard WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    error_log("Error deleting user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}
?>
