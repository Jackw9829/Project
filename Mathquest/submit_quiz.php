<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quiz_id = $_POST['quiz_id'] ?? null;
    $question_id = $_POST['question_id'] ?? null;
    $selected_answer = $_POST['answer'] ?? null;

    if (!$quiz_id || !$question_id || !$selected_answer) {
        $_SESSION['error'] = 'Missing required information';
        header("Location: attempt_quiz.php?id=$quiz_id");
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Save the last answer
        $stmt = $pdo->prepare("
            INSERT INTO user_answers (user_id, quiz_id, question_id, selected_option_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id, $question_id, $selected_answer]);

        // Calculate total points and correct answers
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT q.question_id) as total_questions,
                SUM(CASE WHEN ua.selected_option_id = qo.option_id AND qo.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                q.points_per_question
            FROM questions q
            LEFT JOIN user_answers ua ON q.question_id = ua.question_id 
                AND ua.user_id = ? AND ua.quiz_id = ?
            LEFT JOIN question_options qo ON q.question_id = qo.question_id AND qo.is_correct = 1
            WHERE q.quiz_id = ?
            GROUP BY q.quiz_id, q.points_per_question
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id, $quiz_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_points = $result['correct_answers'] * $result['points_per_question'];
        $score_percentage = ($result['correct_answers'] / $result['total_questions']) * 100;

        // Update quiz attempt with score and points
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET 
                completed = 1, 
                completion_time = NOW(),
                completed_at = NOW(),
                score = ?,
                points_earned = ?
            WHERE user_id = ? AND quiz_id = ? AND completed = 0
        ");
        $stmt->execute([$score_percentage, $total_points, $_SESSION['user_id'], $quiz_id]);

        // Update user's total points
        $stmt = $pdo->prepare("
            UPDATE users 
            SET total_points = total_points + ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$total_points, $_SESSION['user_id']]);

        // Mark the submission as completed
        $stmt = $pdo->prepare("
            UPDATE submissions 
            SET completed = 1, submission_date = NOW() 
            WHERE student_id = ? AND quiz_id = ? AND completed = 0
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);

        // Commit transaction
        $pdo->commit();

        // Redirect to results page
        header("Location: quiz_results.php?quiz_id=$quiz_id");
        exit();

    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error submitting quiz: " . $e->getMessage());
        $_SESSION['error'] = 'Error submitting quiz. Please try again.';
        header("Location: attempt_quiz.php?id=$quiz_id");
        exit();
    }
} else {
    header('Location: dashboard.php');
    exit();
}
?>
