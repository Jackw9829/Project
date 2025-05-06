<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php');
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['quiz_id'])) {
    header('Location: ' . ($_SESSION['user_type'] === 'teacher' ? 'teacherdashboard.php' : 'dashboard.php'));
    exit();
}

$quiz_id = $_GET['quiz_id'];
$attempt_id = isset($_GET['attempt_id']) ? $_GET['attempt_id'] : null;
$error = '';
$quiz = null;
$userAnswers = [];
$feedbackData = null;

try {
    error_log("\n=== Starting Preview for Quiz ID: $quiz_id ===");

    // First, verify the quiz exists and check its status
    $stmt = $pdo->prepare("
        SELECT q.*, 
               CASE WHEN q.due_date < NOW() THEN 0 ELSE 1 END as not_expired
        FROM quizzes q 
        WHERE q.quiz_id = ?
    ");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        error_log("Quiz ID $quiz_id not found in database!");
        throw new Exception("Quiz not found");
    }

    // If student is reviewing and quiz is expired, prevent access
    if ($_SESSION['user_type'] === 'student' && !$quiz['not_expired']) {
        $_SESSION['error_message'] = "This quiz has expired and is no longer available for review.";
        header('Location: dashboard.php');
        exit();
    }

    error_log("Found quiz: " . json_encode($quiz));

    // If reviewing an attempt, get the user's answers and feedback
    if ($attempt_id !== null) {
        // Get answers
        $stmt = $pdo->prepare("
            SELECT qa.*, q.question_text
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.question_id
            WHERE qa.attempt_id = ?
        ");
        $stmt->execute([$attempt_id]);
        while ($answer = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userAnswers[$answer['question_id']] = [
                'selected_answer' => $answer['selected_answer'],
                'is_correct' => $answer['is_correct']
            ];
        }

        // Get feedback
        $stmt = $pdo->prepare("
            SELECT qf.feedback 
            FROM quiz_answers qa
            LEFT JOIN question_feedback qf ON qa.answer_id = qf.answer_id
            WHERE qa.attempt_id = ?
            ORDER BY qa.answer_id ASC
            LIMIT 1
        ");
        $stmt->execute([$attempt_id]);
        $feedbackData = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all questions for this quiz
    $stmt = $pdo->prepare("
        SELECT question_id, question_text, image_path, question_order
        FROM questions 
        WHERE quiz_id = ?
        ORDER BY question_order ASC
    ");
    $stmt->execute([$quiz_id]);
    $questions = [];
    
    while ($question = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get options for this question
        $stmt2 = $pdo->prepare("
            SELECT option_id, option_text, is_correct
            FROM question_options
            WHERE question_id = ?
            ORDER BY option_id ASC
        ");
        $stmt2->execute([$question['question_id']]);
        $question['options'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $questions[] = $question;
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - <?php echo $attempt_id ? 'Review Attempt' : 'Preview Quiz'; ?></title>
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
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
            padding-top: 120px;
        }

        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .dashboard-container {
            padding: 2rem;
            position: relative;
            z-index: 1;
            margin-top: 3rem;
        }

        .content-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #eee;
        }

        h1, h2 {
            color: #333;
            margin: 0;
        }

        h1 {
            font-size: 2rem;
        }

        h2 {
            font-size: 1.5rem;
            margin-top: 2rem;
        }

        .quiz-info {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 2px solid #eee;
        }

        .quiz-info p {
            margin: 0.5rem 0;
            color: #555;
        }

        .quiz-score {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 2px solid #eee;
            text-align: center;
        }

        .quiz-score h3 {
            font-size: 1.5rem;
            color: #333;
            margin: 0;
        }

        .score-number {
            color: #333;
            margin: 0 0.5rem;
        }

        .teacher-feedback {
            background: #f9f9f9;
            padding: 1rem; /* Reduced padding */
            border-radius: 8px;
            margin: 1rem 0; /* Reduced margin */
            border: 2px solid #eee;
        }

        .teacher-feedback h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .teacher-feedback p {
            color: #555;
            line-height: 1.5;
            margin: 0;
        }

        .no-feedback {
            color: #666;
            font-style: italic;
        }

        .questions-list {
            list-style: none;
        }

        .question-item {
            background: #f9f9f9;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            border: 2px solid #eee;
        }

        .question-header {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .question-text {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .question-image {
            margin: 1rem auto;
            max-width: 400px;
            text-align: center;
        }

        .question-image img {
            max-width: 100%;
            width: auto;
            max-height: 300px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .option-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 1rem;
        }

        .option-item {
            padding: 1rem;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .option-item.correct {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .option-item.incorrect {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .option-item .tick-mark {
            display: none;
            color: #28a745;
            font-size: 1.2rem;
            margin-left: 0.5rem;
        }

        .option-item.correct .tick-mark {
            display: inline-block;
        }

        .result-indicator {
            font-size: 1.2rem;
            margin-left: 0.5rem;
            font-weight: bold;
        }

        .result-indicator.correct {
            color: #28a745;
        }

        .result-indicator.incorrect {
            color: #dc3545;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            border: 2px solid #333;
            border-radius: 6px;
            background: #ffdcdc;
            color: #333;
            text-decoration: none;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 1rem;
        }

        .btn:hover {
            background: #333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-secondary {
            background: #dcffdc;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 2px solid #ef9a9a;
        }

        .preview-quiz-container {
            max-width: 1200px;
            margin: 4rem auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .user-answer {
            font-size: 1.2rem;
            margin-top: 1rem;
        }

        .user-answer.correct {
            color: #28a745;
        }

        .user-answer.incorrect {
            color: #dc3545;
        }

        .feedback-text {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            line-height: 1.5;
            color: #333;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .preview-quiz-container {
                padding: 1rem;
                margin: 3rem auto;
            }

            .content-container {
                padding: 1rem;
            }

            .dashboard-container {
                padding: 1rem;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
                margin-right: 0;
                text-align: center;
            }

            .quiz-info {
                padding: 1rem;
            }

            .question-item {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div id="particles-js"></div>
    <div class="dashboard-container">
        <div class="content-container">
            <div class="dashboard-header">
                <h1><?php echo $attempt_id ? 'Review Attempt' : 'Preview Quiz'; ?></h1>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php else: ?>
                <div class="quiz-info">
                    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                </div>

                <?php if ($attempt_id !== null): ?>
                    <div class="quiz-score">
                        <?php 
                            $correctCount = 0;
                            foreach ($userAnswers as $answer) {
                                if ($answer['is_correct']) {
                                    $correctCount++;
                                }
                            }
                        ?>
                        <h3>Your Score: <span class="score-number"><?php echo $correctCount; ?></span></h3>
                    </div>

                    <?php if ($attempt_id && isset($feedbackData) && !empty($feedbackData['feedback'])): ?>
                        <div class="question-item">
                            <div class="question-header">Teacher's Feedback</div>
                            <div class="feedback-text">
                                <?php echo nl2br(htmlspecialchars($feedbackData['feedback'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="questions-list">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-item">
                            <div class="question-header">Question <?php echo $index + 1; ?></div>
                            <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            <?php if ($question['image_path']): ?>
                                <div class="question-image">
                                    <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="Question image">
                                </div>
                            <?php endif; ?>
                            <div class="option-list">
                                <?php foreach ($question['options'] as $option): 
                                    $isSelected = isset($userAnswers[$question['question_id']]) && 
                                                $userAnswers[$question['question_id']]['selected_answer'] === $option['option_text'];
                                    $optionClass = 'option-item';
                                    if ($option['is_correct']) {
                                        $optionClass .= ' correct';
                                    }
                                    if ($attempt_id !== null && $isSelected && !$option['is_correct']) {
                                        $optionClass .= ' incorrect';
                                    }
                                ?>
                                    <div class="<?php echo $optionClass; ?>">
                                        <span class="option-text"><?php echo htmlspecialchars($option['option_text']); ?></span>
                                        <?php if ($attempt_id !== null): ?>
                                            <span class="result-indicator <?php echo $option['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                                <?php echo $option['is_correct'] ? '✓' : '✗'; ?>
                                            </span>
                                        <?php elseif ($option['is_correct']): ?>
                                            <span class="tick-mark">✓</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize particles
            particlesJS('particles-js', {
                "particles": {
                    "number": {
                        "value": 150,
                        "density": {
                            "enable": true,
                            "value_area": 800
                        }
                    },
                    "color": {
                        "value": "#ffdcdc"
                    },
                    "shape": {
                        "type": "circle",
                        "stroke": {
                            "width": 0,
                            "color": "#000000"
                        }
                    },
                    "opacity": {
                        "value": 0.8,
                        "random": true,
                        "anim": {
                            "enable": true,
                            "speed": 1,
                            "opacity_min": 0.1,
                            "sync": false
                        }
                    },
                    "size": {
                        "value": 3,
                        "random": true
                    },
                    "line_linked": {
                        "enable": false
                    },
                    "move": {
                        "enable": true,
                        "speed": 2,
                        "direction": "bottom",
                        "random": true,
                        "straight": false,
                        "out_mode": "out",
                        "bounce": false
                    }
                },
                "interactivity": {
                    "detect_on": "canvas",
                    "events": {
                        "onhover": {
                            "enable": true,
                            "mode": "repulse"
                        },
                        "onclick": {
                            "enable": true,
                            "mode": "push"
                        },
                        "resize": true
                    }
                },
                "retina_detect": true
            });
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
