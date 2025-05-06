<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['quiz_id'])) {
    header('Location: dashboard.php');
    exit();
}

try {
    $quiz_id = $_GET['quiz_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get quiz details and attempt information
    $stmt = $pdo->prepare("
        SELECT 
            q.quiz_id,
            q.title,
            q.description,
            q.image_path,
            u.name as teacher_name,
            qa.score,
            qa.attempted_at,
            COUNT(DISTINCT que.question_id) as total_questions,
            (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? AND completed_at IS NOT NULL AND score IS NOT NULL) as total_attempts
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.user_id
        LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id 
            AND qa.user_id = ?
            AND qa.score IS NOT NULL 
            AND qa.completed_at IS NOT NULL
        LEFT JOIN questions que ON q.quiz_id = que.quiz_id
        WHERE q.quiz_id = ?
        GROUP BY q.quiz_id, qa.attempt_id, q.title, q.description, q.image_path, u.name, qa.score, qa.attempted_at
        ORDER BY qa.attempted_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $user_id, $quiz_id]);
    $quiz_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz_data) {
        throw new Exception('Quiz not found or no completed attempts');
    }

    // Get the most recent attempt for this quiz
    $stmt = $pdo->prepare("
        SELECT attempt_id 
        FROM quiz_attempts 
        WHERE user_id = ? AND quiz_id = ? AND score IS NOT NULL AND completed_at IS NOT NULL
        ORDER BY attempted_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $recent_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$recent_attempt) {
        throw new Exception('No completed attempts found for this quiz');
    }

    // Calculate score based on actual correct answers
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_correct
        FROM quiz_answers
        WHERE attempt_id = ? AND is_correct = 1
    ");
    $stmt->execute([$recent_attempt['attempt_id']]);
    $score_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $quiz_data['points_earned'] = $score_data['total_correct'] ?? 0;

    // Get detailed question and answer information
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            q.question_id,
            q.question_text,
            q.points,
            q.image_path as question_photo,
            q.question_order,
            qa.selected_option_id,
            qa.is_correct as answer_correct,
            qo.is_correct as option_correct,
            qo.option_text,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(
                        qo2.option_id, ':', 
                        qo2.option_text, ':', 
                        qo2.is_correct
                    ) 
                    ORDER BY qo2.option_id
                )
                FROM question_options qo2 
                WHERE qo2.question_id = q.question_id
            ) as options_data
        FROM questions q
        JOIN quiz_attempts qa_attempt ON qa_attempt.quiz_id = q.quiz_id AND qa_attempt.user_id = ?
        LEFT JOIN quiz_answers qa ON qa.attempt_id = qa_attempt.attempt_id 
            AND qa.question_id = q.question_id
        LEFT JOIN question_options qo ON qo.option_id = qa.selected_option_id 
            AND qo.question_id = q.question_id
        WHERE q.quiz_id = ? AND qa_attempt.attempt_id = ?
        ORDER BY q.question_order ASC, q.question_id ASC
    ");
    $stmt->execute([$user_id, $quiz_id, $recent_attempt['attempt_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total possible points
    $stmt = $pdo->prepare("
        SELECT SUM(points) as total_possible_points
        FROM questions
        WHERE quiz_id = ?
    ");
    $stmt->execute([$quiz_id]);
    $total_possible_points = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug information
    error_log("Recent Attempt ID: " . $recent_attempt['attempt_id']);
    error_log("Questions data: " . print_r($questions, true));

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
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
            min-height: 100vh;
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

        .container {
            flex: 1;
            padding: 2rem;
            position: relative;
            z-index: 1;
            margin-top: 2rem;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .quiz-info {
            text-align: center;
            margin: 2rem 0 3rem 0;
            position: relative;
        }

        .quiz-info h1 {
            color: #333;
            margin-bottom: 1.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            font-size: 2.2rem;
            position: relative;
            display: inline-block;
        }

        .quiz-info h1:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 3px;
            background: #ffdcdc;
            border-radius: 2px;
        }

        .quiz-info p {
            color: #666;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
        }

        .score {
            font-size: 2.5rem;
            color: #333;
            margin: 2rem 0;
            text-align: center;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .questions {
            margin-top: 3rem;
            display: grid;
            gap: 2rem;
        }

        .question {
            background: #fff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .question-number {
            font-size: 1.2rem;
            color: #666;
        }

        .question-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .status-correct {
            background: #d4edda;
            color: #155724;
        }

        .status-incorrect {
            background: #f8d7da;
            color: #721c24;
        }

        .question-text {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .question-image {
            width: 100%;
            max-width: 500px;
            margin: 1rem auto;
            display: block;
        }

        .options {
            display: grid;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .option {
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.1rem;
            color: #333;
        }

        .option.selected {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .option.correct {
            border-color: #28a745;
            background: #d4edda;
        }

        .option.incorrect {
            border-color: #dc3545;
            background: #f8d7da;
        }

        .option-marker {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: auto;
        }

        .marker-correct {
            background: #28a745;
            color: white;
        }

        .marker-incorrect {
            background: #dc3545;
            color: white;
        }

        .user-answer {
            font-size: 1.1rem;
            color: #666;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .result-card {
                padding: 1.5rem;
            }

            .question {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div id="particles-js"></div>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="result-card">
                <div class="quiz-info">
                    <h1><?php echo htmlspecialchars($quiz_data['title']); ?></h1>
                    <?php if ($quiz_data['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($quiz_data['image_path']); ?>" 
                             alt="Quiz Image" 
                             class="question-image">
                    <?php endif; ?>
                    <p>Created by: <?php echo htmlspecialchars($quiz_data['teacher_name']); ?></p>
                    <?php if ($quiz_data['attempted_at']): ?>
                        <p>Attempted on: <?php echo date('F j, Y, g:i a', strtotime($quiz_data['attempted_at'])); ?></p>
                    <?php endif; ?>
                    <p>Total Attempts: <?php echo $quiz_data['total_attempts']; ?></p>
                </div>

                <?php if ($quiz_data['points_earned'] !== null): ?>
                    <div class="score">
                        Score: <?php echo $quiz_data['points_earned']; ?> out of <?php echo $total_possible_points['total_possible_points']; ?> points
                    </div>

                    <div class="questions">
                        <?php foreach ($questions as $index => $question): 
                            // Parse options data
                            $options = [];
                            foreach (explode(',', $question['options_data']) as $option_data) {
                                list($id, $text, $is_correct) = explode(':', $option_data);
                                $options[$id] = [
                                    'text' => $text,
                                    'is_correct' => $is_correct,
                                    'selected' => $id == $question['selected_option_id']
                                ];
                            }
                        ?>
                            <div class="question">
                                <div class="question-header">
                                    <span class="question-number">Question <?php echo $index + 1; ?></span>
                                    <?php if (isset($question['answer_correct'])): ?>
                                        <span class="question-status <?php echo $question['answer_correct'] ? 'status-correct' : 'status-incorrect'; ?>">
                                            <?php echo $question['answer_correct'] ? 'Correct' : 'Incorrect'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($question['selected_option_id']): ?>
                                    <div class="user-answer">
                                        Your Answer: <?php 
                                            foreach ($options as $opt) {
                                                if ($opt['selected']) {
                                                    echo htmlspecialchars($opt['text']);
                                                    break;
                                                }
                                            }
                                        ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($question['question_photo']): ?>
                                    <img src="<?php echo htmlspecialchars($question['question_photo']); ?>" 
                                         alt="Question <?php echo $index + 1; ?> image"
                                         class="question-image">
                                <?php endif; ?>

                                <div class="options">
                                    <?php foreach ($options as $option_id => $option): 
                                        $classes = ['option'];
                                        if ($option['selected']) {
                                            $classes[] = 'selected';
                                            // Use answer_correct for the selected answer's status
                                            $classes[] = $question['answer_correct'] ? 'correct' : 'incorrect';
                                        } elseif ($option['is_correct']) {
                                            $classes[] = 'correct';
                                        }
                                    ?>
                                        <div class="<?php echo implode(' ', $classes); ?>">
                                            <?php echo htmlspecialchars($option['text']); ?>
                                            <?php if ($option['selected']): ?>
                                                <span class="option-marker <?php echo $question['answer_correct'] ? 'marker-correct' : 'marker-incorrect'; ?>">
                                                    <?php echo $question['answer_correct'] ? '✓' : '✗'; ?>
                                                </span>
                                            <?php elseif ($option['is_correct']): ?>
                                                <span class="option-marker marker-correct">✓</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No attempt recorded for this quiz yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'js/particles.json', function() {
            console.log('particles.js loaded');
        });
    </script>
</body>
</html>
