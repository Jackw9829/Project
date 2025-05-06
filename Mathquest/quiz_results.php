<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$quiz_id = $_GET['id'];

try {
    // Get quiz details and score from both tables
    $stmt = $pdo->prepare("
        SELECT 
            q.title, 
            q.description,
            q.due_date,
            CASE WHEN q.due_date < NOW() THEN 'Expired' ELSE 'Active' END as status,
            qa.score,
            qa.points_earned,
            qa.attempted_at,
            u.name as teacher_name
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.user_id
        LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id 
            AND qa.user_id = ? 
            AND qa.score IS NOT NULL 
            AND qa.completed_at IS NOT NULL
        WHERE q.quiz_id = ?
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $quiz_result = $stmt->fetch();

    if (!$quiz_result) {
        header('Location: dashboard.php');
        exit();
    }

    // Get detailed question results
    $stmt = $pdo->prepare("
        SELECT 
            q.question_text,
            q.explanation,
            ua.selected_option_id,
            qo.option_text,
            qo.is_correct,
            q.points_per_question
        FROM questions q
        LEFT JOIN user_answers ua ON q.question_id = ua.question_id 
            AND ua.user_id = ? AND ua.quiz_id = ?
        LEFT JOIN question_options qo ON q.question_id = qo.question_id
        WHERE q.quiz_id = ?
        ORDER BY q.question_order
    ");
    $stmt->execute([$user_id, $quiz_id, $quiz_id]);
    $questions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error in quiz_results.php: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>MathQuest - Quiz Results</title>
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
            font-family: 'Tiny5';
            padding-top: 80px;
            background-color: #f5f5f5;
        }

        .header {
            background-color: #ffdcdc;
            height: 80px;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo img {
            height: 60px;
            margin-right: 10px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            font-family: 'Tiny5';
            background-color: transparent;
            border: 2px solid #333;
            color: #333;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-btn:hover {
            background-color: #333;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .results-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .quiz-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .quiz-meta {
            color: #666;
            margin-bottom: 1rem;
        }

        .quiz-status {
            font-weight: bold;
        }

        .quiz-status.expired {
            color: #f44336;
        }

        .quiz-status.active {
            color: #4caf50;
        }

        .score-display {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 3rem;
        }

        .score {
            font-size: 4rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .score-label {
            font-size: 1.2rem;
            color: #666;
        }

        .points-earned {
            font-size: 1.2rem;
            color: #666;
            margin-top: 1rem;
        }

        .questions-review {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .review-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
        }

        .question-card {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .question-card:last-child {
            border-bottom: none;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .question-text {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .options {
            margin-top: 1rem;
        }

        .option {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .option.selected {
            background-color: #fff3f3;
            border: 1px solid #ffcdd2;
        }

        .option.correct {
            background-color: #f1f8e9;
            border: 1px solid #c5e1a5;
        }

        .option.incorrect {
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
        }

        .option-marker {
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }

        .option-marker.correct {
            color: #4caf50;
        }

        .option-marker.incorrect {
            color: #f44336;
        }

        .explanation {
            margin-top: 2rem;
        }

        .explanation h4 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        footer {
            background-color: #d4e0ee;
            padding: 20px;
            margin-top: 2rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-links a {
            color: #333;
            text-decoration: none;
            margin-left: 20px;
        }

        .footer-links a:hover {
            color: #007bff;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php" class="logo">
            <img src="mathquestlogo.png" alt="MathQuest Logo">
            <span class="logo-text">MathQuest</span>
        </a>
        <div class="nav-buttons">
            <a href="dashboard.php" class="nav-btn">Dashboard</a>
            <a href="leaderboard.html" class="nav-btn">Leaderboard</a>
            <a href="profile.html" class="nav-btn">Profile</a>
        </div>
    </header>

    <div class="container">
        <div class="results-header">
            <h1 class="quiz-title"><?php echo htmlspecialchars($quiz_result['title']); ?></h1>
            <div class="quiz-meta">
                <p>Created by: <?php echo htmlspecialchars($quiz_result['teacher_name']); ?></p>
                <p>Completed on: <?php echo date('F j, Y, g:i a', strtotime($quiz_result['attempted_at'])); ?></p>
                <p>Status: <span class="quiz-status <?php echo strtolower($quiz_result['status']); ?>"><?php echo $quiz_result['status']; ?></span></p>
                <?php if ($quiz_result['due_date']): ?>
                    <p>Due Date: <?php echo date('F j, Y, g:i a', strtotime($quiz_result['due_date'])); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="score-display">
            <div class="score"><?php echo $quiz_result['points_earned']; ?> points</div>
            <div class="score-label">Your Score</div>
        </div>

        <div class="questions-review">
            <h2>Review Your Answers</h2>
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <div class="question-header">
                        <h3>Question <?php echo $index + 1; ?></h3>
                        <span class="points"><?php echo $question['points_per_question']; ?> points</span>
                    </div>
                    <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                    
                    <div class="options">
                        <?php 
                            $stmt = $pdo->prepare("
                                SELECT 
                                    option_id,
                                    option_text,
                                    is_correct
                                FROM question_options
                                WHERE question_id = ?
                                ORDER BY option_order
                            ");
                            $stmt->execute([$question['question_id']]);
                            $options = $stmt->fetchAll();
                        ?>
                        <?php foreach ($options as $option): ?>
                            <div class="option <?php 
                                echo $option['option_id'] == $question['selected_option_id'] ? 'selected' : '';
                                echo $option['is_correct'] ? ' correct' : '';
                                echo $option['option_id'] == $question['selected_option_id'] && !$option['is_correct'] ? ' incorrect' : '';
                            ?>">
                                <?php echo htmlspecialchars($option['option_text']); ?>
                                <?php if ($option['option_id'] == $question['selected_option_id']): ?>
                                    <span class="option-marker <?php echo $option['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                        <?php echo $option['is_correct'] ? '✓' : '✗'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($question['explanation'])): ?>
                        <div class="explanation">
                            <h4>Explanation:</h4>
                            <p><?php echo htmlspecialchars($question['explanation']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="action-btn">Back to Dashboard</a>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <p>&copy; 2024 MathQuest. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">About Us</a>
                <a href="contactadmin.php">Admin Support</a>
            </div>
        </div>
    </footer>
</body>
</html>
