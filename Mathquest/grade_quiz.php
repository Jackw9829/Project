<?php
session_start();
require_once 'config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['teacher', 'admin'])) {
    $_SESSION['error_message'] = "You must be logged in as a teacher or admin to access this page.";
    header('Location: login.php');
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    $_SESSION['error_message'] = "Invalid quiz ID.";
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}

$quiz_id = (int)$_GET['quiz_id'];
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$page_title = "View Quiz Grades";

try {
    // First, check if the quiz exists and if the user has access
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            u.name as teacher_name,
            CASE WHEN q.due_date < NOW() OR q.is_active = 0 THEN 0 ELSE 1 END as is_active,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND score IS NOT NULL) as total_attempts,
            (SELECT COUNT(DISTINCT user_id) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND score IS NOT NULL) as unique_students,
            (SELECT AVG(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND score IS NOT NULL) as average_score,
            (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as highest_score,
            (SELECT MIN(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND score IS NOT NULL) as lowest_score
        FROM quizzes q 
        JOIN users u ON q.teacher_id = u.user_id 
        WHERE q.quiz_id = ? AND (q.teacher_id = ? OR ? = 'admin')
    ");
    
    $stmt->execute([$quiz_id, $user_id, $user_type]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error_message'] = "Quiz not found or you don't have permission to view it.";
        header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
        exit();
    }

    $page_title = "View Grades: " . htmlspecialchars($quiz['title']);

    // Get all attempts for this quiz with student information
    $stmt = $pdo->prepare("
        WITH RankedAttempts AS (
            SELECT 
                qa.*,
                ROW_NUMBER() OVER (PARTITION BY qa.user_id ORDER BY qa.attempted_at) as attempt_number
            FROM quiz_attempts qa
            WHERE qa.quiz_id = ? AND qa.score IS NOT NULL
        )
        SELECT 
            ra.*,
            u.name as student_name,
            u.email as student_email,
            ra.attempt_number,
            ra.completed_at as submitted_at,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) as total_questions,
            (SELECT COUNT(*) 
             FROM answers a 
             JOIN question_options qo ON a.selected_option_id = qo.option_id 
             WHERE a.quiz_id = ? AND a.user_id = ra.user_id AND qo.is_correct = 1) as correct_answers
        FROM RankedAttempts ra
        JOIN users u ON ra.user_id = u.user_id
        ORDER BY u.name ASC, ra.attempted_at DESC
    ");
    $stmt->execute([$quiz_id, $quiz_id, $quiz_id]);
    $attempts = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Error in grade_quiz.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while loading the grades.";
    header('Location: ' . ($user_type === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MathQuest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tiny5', sans-serif;
            background-color: #f5f5f5;
            min-height: 100vh;
            padding-top: 80px;
        }

        .grade-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .quiz-info {
            flex-grow: 1;
        }

        .quiz-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .quiz-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .quiz-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #333;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .attempts-table {
            width: 100%;
            overflow-x: auto;
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            font-size: 0.9rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .score {
            font-weight: 600;
        }

        .score-high {
            color: #166534;
        }

        .score-medium {
            color: #854d0e;
        }

        .score-low {
            color: #991b1b;
        }

        .empty-message {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .grade-container {
                margin: 1rem;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .attempts-table {
                font-size: 0.8rem;
            }

            th, td {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="grade-container">
        <div class="quiz-header">
            <div class="quiz-info">
                <h1 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                <div class="quiz-meta">
                    <p>Created by: <?php echo htmlspecialchars($quiz['teacher_name']); ?></p>
                    <p>Due Date: <?php echo date('M j, Y g:i A', strtotime($quiz['due_date'])); ?></p>
                </div>
            </div>
            <div class="quiz-status <?php echo $quiz['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Attempts</h3>
                <p><?php echo $quiz['total_attempts']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Unique Students</h3>
                <p><?php echo $quiz['unique_students']; ?></p>
            </div>
            <div class="stat-card">
                <h3>Average Score</h3>
                <p><?php echo $quiz['average_score'] ? number_format($quiz['average_score'], 1) . '%' : 'N/A'; ?></p>
            </div>
            <div class="stat-card">
                <h3>Highest Score</h3>
                <p><?php echo $quiz['highest_score'] ? number_format($quiz['highest_score'], 1) . '%' : 'N/A'; ?></p>
            </div>
            <div class="stat-card">
                <h3>Lowest Score</h3>
                <p><?php echo $quiz['lowest_score'] ? number_format($quiz['lowest_score'], 1) . '%' : 'N/A'; ?></p>
            </div>
        </div>

        <div class="attempts-table">
            <?php if (empty($attempts)): ?>
                <div class="empty-message">No attempts have been made on this quiz yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Attempt #</th>
                            <th>Score</th>
                            <th>Correct Answers</th>
                            <th>Submission Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($attempt['student_email']); ?></td>
                                <td><?php echo $attempt['attempt_number']; ?></td>
                                <td class="score <?php 
                                    if ($attempt['score'] >= 80) echo 'score-high';
                                    elseif ($attempt['score'] >= 60) echo 'score-medium';
                                    else echo 'score-low';
                                ?>">
                                    <?php echo number_format($attempt['score'], 1); ?>%
                                </td>
                                <td><?php echo $attempt['correct_answers']; ?> / <?php echo $attempt['total_questions']; ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($attempt['submitted_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
