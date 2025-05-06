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
$page_title = "Grade Quiz";

try {
    // Get quiz information and statistics
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            u.name as teacher_name,
            (SELECT COUNT(DISTINCT user_id) FROM quiz_attempts WHERE quiz_id = ?) as unique_students,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ?) as total_attempts,
            (SELECT AVG(score) FROM (
                SELECT user_id, MAX(score) as score 
                FROM quiz_attempts 
                WHERE quiz_id = ? 
                GROUP BY user_id
            ) best_scores) as average_score,
            (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = ?) as highest_score,
            (SELECT MIN(score) FROM (
                SELECT user_id, MAX(score) as score 
                FROM quiz_attempts 
                WHERE quiz_id = ? 
                GROUP BY user_id
            ) best_scores) as lowest_score
        FROM quizzes q
        LEFT JOIN users u ON q.teacher_id = u.user_id
        WHERE q.quiz_id = ? AND (q.teacher_id = ? OR ? = 'admin')
    ");
    $stmt->execute([$quiz_id, $quiz_id, $quiz_id, $quiz_id, $quiz_id, $quiz_id, $user_id, $user_type]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error_message'] = "Quiz not found or you don't have permission to view it.";
        header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
        exit();
    }

    // Get all attempts for this quiz with student information
    $stmt = $pdo->prepare("
        SELECT qa.*, u.name as student_name, u.email as student_email
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.user_id
        WHERE qa.quiz_id = ? 
        AND qa.score IS NOT NULL  -- Only show completed attempts
        AND qa.completed_at IS NOT NULL  -- Ensure attempt was completed
        AND qa.score = (
            SELECT MAX(score) 
            FROM quiz_attempts 
            WHERE quiz_id = ? 
            AND user_id = qa.user_id
            AND score IS NOT NULL
            AND completed_at IS NOT NULL
        )
        ORDER BY qa.score DESC, qa.attempted_at DESC
    ");
    $stmt->execute([$quiz_id, $quiz_id]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching quiz data.";
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - MathQuest</title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="css/quiz-card-new.css">
    <style>
        .grade-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .quiz-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Button Styles */
        .attempts-table .view-details-btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #c1bbdd;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            font-size: 14px;
            line-height: 1.5;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .attempts-table .view-details-btn:hover {
            background-color: #b0a9d1;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Table Styles */
        .attempts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
        }

        .attempts-table th,
        .attempts-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .attempts-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        .attempts-table tr:hover {
            background: #f1f5f9;
        }

        .student-email {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .score {
            font-weight: 600;
        }

        .score-high {
            color: #059669;
        }

        .score-medium {
            color: #b45309;
        }

        .score-low {
            color: #dc2626;
        }

        .attempt-date {
            color: #374151;
            font-size: 0.95rem;
            font-weight: 500;
            display: block;
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .attempts-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="grade-container">
        <div class="quiz-header">
            <h1 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <p class="quiz-description"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></p>
            
            <div class="quiz-meta">
                <div class="meta-item">
                    <span class="meta-label">Total Attempts</span>
                    <span class="meta-value"><?php echo number_format($quiz['total_attempts'] ?? 0); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Unique Students</span>
                    <span class="meta-value"><?php echo number_format($quiz['unique_students'] ?? 0); ?></span>
                </div>
                <?php if (isset($quiz['average_score'])): ?>
                <div class="meta-item">
                    <span class="meta-label">Average Score</span>
                    <span class="meta-value"><?php echo number_format($quiz['average_score'], 1); ?> points</span>
                </div>
                <?php endif; ?>
                <?php if (isset($quiz['highest_score'])): ?>
                <div class="meta-item">
                    <span class="meta-label">Highest Score</span>
                    <span class="meta-value"><?php echo number_format($quiz['highest_score'], 1); ?> points</span>
                </div>
                <?php endif; ?>
                <?php if (isset($quiz['lowest_score'])): ?>
                <div class="meta-item">
                    <span class="meta-label">Lowest Score</span>
                    <span class="meta-value"><?php echo number_format($quiz['lowest_score'], 1); ?> points</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($attempts)): ?>
            <p>No attempts have been made for this quiz yet.</p>
        <?php else: ?>
            <table class="attempts-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Score</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $attempt): ?>
                        <tr>
                            <td>
                                <div><?php echo htmlspecialchars($attempt['student_name']); ?></div>
                                <div class="student-email"><?php echo htmlspecialchars($attempt['student_email']); ?></div>
                            </td>
                            <td>
                                <?php
                                $scoreClass = '';
                                if ($attempt['score'] >= 8) {
                                    $scoreClass = 'score-high';
                                } elseif ($attempt['score'] >= 6) {
                                    $scoreClass = 'score-medium';
                                } else {
                                    $scoreClass = 'score-low';
                                }
                                ?>
                                <span class="score <?php echo $scoreClass; ?>">
                                    <?php 
                                    if (isset($attempt['score'])) {
                                        $totalPoints = $attempt['score'];
                                        echo $totalPoints . ' points';
                                    } else {
                                        echo 'In Progress';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="attempt-date">
                                    <div>Attempted on:</div>
                                    <?php echo date('F j, Y', strtotime($attempt['attempted_at'])); ?>
                                    <div><?php echo date('g:i A', strtotime($attempt['attempted_at'])); ?></div>
                                </div>
                            </td>
                            <td>
                                <a href="feedback_quiz.php?quiz_id=<?php echo $quiz_id; ?>&attempt_id=<?php echo $attempt['attempt_id']; ?>&student_id=<?php echo $attempt['user_id']; ?>" 
                                   class="view-details-btn"
                                   title="View attempt details for <?php echo htmlspecialchars($attempt['student_name']); ?>">
                                   View Details
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        // Add any JavaScript functionality here if needed
    </script>
</body>
</html>
