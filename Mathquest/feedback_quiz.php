<?php
session_start();
require_once 'config/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add debugging
error_log("Received parameters: " . print_r($_GET, true));

// Check if user is logged in and is a teacher or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['teacher', 'admin'])) {
    error_log("User not logged in or not teacher/admin");
    header('Location: login.php');
    exit();
}

// Check if quiz_id and attempt_id are provided
if (!isset($_GET['quiz_id']) || !isset($_GET['attempt_id']) || !isset($_GET['student_id'])) {
    error_log("Missing parameters. GET data: " . print_r($_GET, true));
    $_SESSION['error_message'] = "Missing required parameters.";
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}

$quiz_id = $_GET['quiz_id'];
$attempt_id = $_GET['attempt_id'];
$student_id = $_GET['student_id'];
$error = '';
$success = '';

// Fetch the attempt details first
try {
    error_log("Attempting to fetch attempt details for quiz_id: $quiz_id, attempt_id: $attempt_id, student_id: $student_id");
    $stmt = $pdo->prepare("
        SELECT 
            qa.*,
            q.title as quiz_title,
            u.name as student_name
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        JOIN users u ON qa.user_id = u.user_id
        WHERE qa.attempt_id = ? AND qa.quiz_id = ? AND qa.user_id = ?
    ");
    $stmt->execute([$attempt_id, $quiz_id, $student_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
        error_log("No attempt found for quiz_id: $quiz_id, attempt_id: $attempt_id, student_id: $student_id");
        $_SESSION['error_message'] = "Attempt not found.";
        header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
        exit();
    }

    // Get user's answers for this attempt
    $stmt = $pdo->prepare("
        SELECT 
            qa.*,
            q.question_text,
            qo.option_text as selected_option_text
        FROM quiz_answers qa
        JOIN questions q ON qa.question_id = q.question_id
        LEFT JOIN question_options qo ON qa.selected_answer = qo.option_id
        WHERE qa.attempt_id = ?
    ");
    $stmt->execute([$attempt_id]);
    $userAnswers = [];
    while ($answer = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userAnswers[$answer['question_id']] = [
            'selected_answer' => $answer['selected_option_text'] ?? $answer['selected_answer'],
            'is_correct' => $answer['is_correct'],
            'answer_id' => $answer['answer_id']
        ];
        error_log("Fetched answer for question {$answer['question_id']}: " . print_r($userAnswers[$answer['question_id']], true));
    }

    // Get all questions for this quiz
    $stmt = $pdo->prepare("
        SELECT question_id, question_text, image_path, question_order, points
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
        
        // Add user's answer if it exists
        if (isset($userAnswers[$question['question_id']])) {
            $question['user_answer'] = $userAnswers[$question['question_id']];
        }
        
        $questions[] = $question;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while fetching the attempt details.";
    header('Location: ' . ($_SESSION['user_type'] === 'admin' ? 'admindashboard.php' : 'teacherdashboard.php'));
    exit();
}

// Handle saving feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_feedback'])) {
    try {
        error_log("POST data received: " . print_r($_POST, true));
        error_log("Attempt ID: " . $attempt_id);
        
        $pdo->beginTransaction();
        
        // Get the first answer_id for this attempt
        $stmt = $pdo->prepare("
            SELECT answer_id 
            FROM quiz_answers 
            WHERE attempt_id = ? 
            ORDER BY answer_id ASC 
            LIMIT 1
        ");
        $stmt->execute([$attempt_id]);
        $answer_id = $stmt->fetchColumn();
        
        error_log("Retrieved answer_id: " . ($answer_id ?? 'null'));
        
        // Get the feedback data
        $feedback_text = trim($_POST['quiz_feedback'] ?? '');
        
        if (!$answer_id) {
            throw new Exception("No answer ID found for attempt ID: " . $attempt_id);
        }

        // Check if feedback already exists
        $stmt = $pdo->prepare("SELECT feedback_id FROM question_feedback WHERE answer_id = ?");
        $stmt->execute([$answer_id]);
        $existing_feedback = $stmt->fetch();
        
        error_log("Existing feedback: " . ($existing_feedback ? 'yes' : 'no'));
        
        if ($existing_feedback && !empty($feedback_text)) {
            // Update existing feedback
            $stmt = $pdo->prepare("UPDATE question_feedback SET feedback = ?, updated_at = CURRENT_TIMESTAMP WHERE answer_id = ?");
            $stmt->execute([$feedback_text, $answer_id]);
            error_log("Updated existing feedback");
        } elseif (!$existing_feedback && !empty($feedback_text)) {
            // Insert new feedback
            $stmt = $pdo->prepare("INSERT INTO question_feedback (answer_id, feedback) VALUES (?, ?)");
            $stmt->execute([$answer_id, $feedback_text]);
            error_log("Inserted new feedback");
        } elseif ($existing_feedback && empty($feedback_text)) {
            // Remove feedback if text is empty
            $stmt = $pdo->prepare("DELETE FROM question_feedback WHERE answer_id = ?");
            $stmt->execute([$answer_id]);
            error_log("Deleted existing feedback");
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Feedback saved successfully!";
        // Redirect back to the same page to prevent form resubmission
        header('Location: feedback_quiz.php?quiz_id=' . urlencode($quiz_id) . '&attempt_id=' . urlencode($attempt_id) . '&student_id=' . urlencode($student_id));
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving feedback: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $error = "An error occurred while saving the feedback. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Feedback - <?php echo htmlspecialchars($attempt['quiz_title'] ?? 'Quiz'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            position: relative;
            background: #ffffff;
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

        .feedback-quiz-container {
            max-width: 1200px;
            margin: 5rem auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .question-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 6px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .quiz-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .quiz-title {
            font-size: 1.8rem;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .student-info {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .question-text {
            font-size: 1.1em;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .options-container {
            margin-bottom: 15px;
        }
        .option {
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 4px;
            background: #f3f4f6;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .option.option-correct {
            background: #dcfce7;
            border-left-color: #22c55e;
        }
        .option.option-wrong {
            background: #fee2e2;
            border-left-color: #ef4444;
        }
        .answer-status {
            font-style: italic;
            margin-left: 10px;
        }
        .answer-status.correct {
            color: #22c55e;
        }
        .answer-status.wrong {
            color: #ef4444;
        }
        .correct-answer-text {
            color: #22c55e;
            font-style: italic;
            margin-left: 5px;
        }

        .answer {
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .answer.correct {
            border-left: 4px solid #059669;
        }

        .answer.incorrect {
            border-left: 4px solid #dc2626;
        }

        .feedback-section {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .feedback-section label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            color: #374151;
        }
        .feedback-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        .feedback-section textarea:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }
        .feedback-readonly {
            background: #f9fafb;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            min-height: 100px;
            white-space: pre-wrap;
            line-height: 1.5;
        }
        .feedback-readonly em {
            color: #6b7280;
            font-style: italic;
        }

        .feedback-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-top: 0.5rem;
            font-size: 0.95rem;
            resize: vertical;
        }

        .back-btn, .submit-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #ffdcdc;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-family: 'Tiny5', sans-serif;
            font-size: 16px;
            line-height: 1.5;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .back-btn:hover, .submit-btn:hover {
            background-color: #ffbdbd;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .button-group {
            margin-bottom: 20px;
        }

        .option-correct {
            color: #22c55e;
            font-weight: bold;
        }
        .option-wrong {
            color: #ef4444;
            font-weight: bold;
        }
        .points {
            font-weight: bold;
            margin-left: 10px;
        }
        .points.correct {
            color: #22c55e;
        }
        .points.wrong {
            color: #ef4444;
        }

        .quiz-preview {
            margin-bottom: 30px;
        }

        .question-container {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .question-text {
            font-size: 1.1em;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .points {
            font-size: 0.9em;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .points.correct {
            background-color: #dff0d8;
            color: #3c763d;
        }

        .points.wrong {
            background-color: #f2dede;
            color: #a94442;
        }

        .options-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .option {
            padding: 10px 15px;
            border-radius: 4px;
            background: white;
            border: 1px solid #ddd;
            position: relative;
        }

        .option.correct {
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }

        .option.incorrect {
            background-color: #f2dede;
            border-color: #ebccd1;
        }

        .selected-marker, .correct-marker {
            font-size: 0.9em;
            margin-left: 10px;
            font-style: italic;
        }

        .selected-marker {
            color: #666;
        }

        .correct-marker {
            color: #3c763d;
        }

        .feedback-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .feedback-section h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .feedback-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }

        .question-image {
            margin: 15px 0;
            text-align: center;
        }

        .question-image img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .feedback-quiz-container {
                padding: 1rem;
                margin: 1rem;
            }
        }

        .feedback-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            line-height: 1.5;
        }

        .feedback-display.no-feedback {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>
    
    <div class="feedback-quiz-container">
        <div class="button-group">
            <a href="teacher_grade_quiz.php?quiz_id=<?php echo htmlspecialchars($quiz_id); ?>" class="back-btn">
                Back to Grades
            </a>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error"><?php 
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
            ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
            ?></div>
        <?php endif; ?>

        <div class="quiz-header">
            <h1 class="quiz-title"><?php echo htmlspecialchars($attempt['quiz_title'] ?? 'Quiz Review'); ?></h1>
            <div class="student-info">
                <p>Student: <?php echo htmlspecialchars($attempt['student_name'] ?? 'Unknown Student'); ?></p>
                <p>Score: <?php echo htmlspecialchars($attempt['score']); ?></p>
                <p>Attempt Date: <?php echo isset($attempt['attempted_at']) ? date('M j, Y g:i A', strtotime($attempt['attempted_at'])) : 'Unknown'; ?></p>
            </div>
        </div>

        <?php if (!empty($questions)): ?>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET)); ?>">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="quiz-preview">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-container">
                        <h3>Question <?php echo $index + 1; ?></h3>
                        <p class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                            <span class="points <?php echo $question['user_answer']['is_correct'] ? 'correct' : 'wrong'; ?>">
                                <?php echo $question['user_answer']['is_correct'] ? '1/1' : '0/1'; ?> points
                            </span>
                        </p>

                        <?php if (!empty($question['image_path'])): ?>
                            <div class="question-image">
                                <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="Question Image">
                            </div>
                        <?php endif; ?>
                        
                        <div class="options-list">
                            <?php foreach ($question['options'] as $option): ?>
                                <div class="option <?php 
                                    if (isset($question['user_answer'])) {
                                        if ($question['user_answer']['selected_answer'] == $option['option_text']) {
                                            echo $option['is_correct'] ? 'correct' : 'incorrect';
                                        } elseif ($option['is_correct']) {
                                            echo 'correct';
                                        }
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($option['option_text']); ?>
                                    <?php if ($option['is_correct']): ?>
                                        <span class="correct-marker">(Correct Answer)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($_SESSION['user_type'] === 'teacher'): ?>
            <div class="feedback-section">
                <h3>Feedback</h3>
                <?php
                    // Get the first answer for this attempt
                    $stmt = $pdo->prepare("
                        SELECT qa.answer_id, qf.feedback 
                        FROM quiz_answers qa 
                        LEFT JOIN question_feedback qf ON qa.answer_id = qf.answer_id
                        WHERE qa.attempt_id = ? 
                        ORDER BY qa.answer_id ASC
                        LIMIT 1
                    ");
                    $stmt->execute([$attempt_id]);
                    $answer_data = $stmt->fetch();
                    
                    error_log("Feedback form - Retrieved answer data: " . print_r($answer_data, true));
                ?>
                <textarea 
                    name="quiz_feedback" 
                    class="feedback-input"
                    placeholder="Provide feedback for this quiz attempt..."
                    rows="4"
                ><?php echo htmlspecialchars($answer_data['feedback'] ?? ''); ?></textarea>
                
                <div class="button-group">
                    <button type="submit" name="save_feedback" class="submit-btn">Save Feedback</button>
                </div>
            </div>
            <?php else: ?>
                <?php
                    // Get the first answer for this attempt
                    $stmt = $pdo->prepare("
                        SELECT qa.answer_id, qf.feedback 
                        FROM quiz_answers qa 
                        LEFT JOIN question_feedback qf ON qa.answer_id = qf.answer_id
                        WHERE qa.attempt_id = ? 
                        ORDER BY qa.answer_id ASC
                        LIMIT 1
                    ");
                    $stmt->execute([$attempt_id]);
                    $answer_data = $stmt->fetch();
                    
                    error_log("Feedback form - Retrieved answer data: " . print_r($answer_data, true));
                ?>
                <div class="feedback-section">
                    <h3>Feedback</h3>
                    <?php if (!empty($answer_data['feedback'])): ?>
                        <div class="feedback-display">
                            <?php echo nl2br(htmlspecialchars($answer_data['feedback'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="feedback-display no-feedback">
                            No feedback provided yet.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
    <?php include 'includes/footer.php'; ?>
    <script>
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
                    },
                },
                "opacity": {
                    "value": 0.5,
                    "random": true,
                },
                "size": {
                    "value": 5,
                    "random": true,
                },
                "line_linked": {
                    "enable": false
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "bottom",
                    "straight": false,
                    "out_mode": "out"
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
                }
            }
        });
    </script>
</body>
</html>
