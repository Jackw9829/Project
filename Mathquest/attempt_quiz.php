<?php
session_start();
require_once 'config/db_connect.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$quiz_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    // First, check if the quiz exists and get attempt info
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            u.name as teacher_name,
            qa.score,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.quiz_id) as total_questions
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.user_id
        LEFT JOIN quiz_attempts qa ON q.quiz_id = qa.quiz_id 
            AND qa.user_id = ?
            AND qa.score = (
                SELECT MAX(score) 
                FROM quiz_attempts 
                WHERE quiz_id = q.quiz_id AND user_id = ?
            )
        WHERE q.quiz_id = ?
    ");
    $stmt->execute([$user_id, $user_id, $quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: dashboard.php');
        exit();
    }

    // If quiz is already completed with a perfect score, redirect to results
    if ($quiz['score'] == $quiz['total_questions']) {
        header("Location: resultpage.php?quiz_id=$quiz_id");
        exit();
    }

    // Retrieve the latest attempt ID for the user and quiz
    $stmt = $pdo->prepare("SELECT attempt_id FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? ORDER BY attempt_id DESC LIMIT 1");
    $stmt->execute([$quiz_id, $user_id]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
    $attempt_id = $attempt ? $attempt['attempt_id'] : null; // Set to null if no attempt found

    // Check if attempt_id is defined before using it
    if ($attempt_id === null) {
        // Handle the case where no attempt exists (e.g., create a new attempt)
        $stmt = $pdo->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, attempted_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user_id, $quiz_id]);
        $attempt_id = $pdo->lastInsertId();
    }

    // Create a new submission if one doesn't exist
    $stmt = $pdo->prepare("
        SELECT submission_id 
        FROM submissions 
        WHERE student_id = ? AND quiz_id = ? AND completed = 0
        ORDER BY submitted_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $quiz_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        // Create submission
        $stmt = $pdo->prepare("
            INSERT INTO submissions (student_id, quiz_id, submitted_at, completed) 
            VALUES (?, ?, NOW(), 0)
        ");
        $stmt->execute([$user_id, $quiz_id]);
        $submission_id = $pdo->lastInsertId();
    } else {
        $submission_id = $submission['submission_id'];
    }

    // Get current question ID from URL or get first unanswered question
    $current_question_id = isset($_GET['question']) ? $_GET['question'] : null;
    $direction = isset($_GET['direction']) ? $_GET['direction'] : null;
    
    if ($current_question_id) {
        // Get the current question's order
        $stmt = $pdo->prepare("
            SELECT question_order 
            FROM questions 
            WHERE question_id = ?
        ");
        $stmt->execute([$current_question_id]);
        $current_order = $stmt->fetchColumn();

        // Get the next or previous question based on direction
        if ($direction === 'next' || $direction === 'prev') {
            $operator = $direction === 'next' ? '>' : '<';
            $orderBy = $direction === 'next' ? 'ASC' : 'DESC';
            
            $stmt = $pdo->prepare("
                SELECT q.*
                FROM questions q
                WHERE q.quiz_id = ? 
                AND q.question_order $operator ?
                ORDER BY q.question_order $orderBy
                LIMIT 1
            ");
            $stmt->execute([$quiz_id, $current_order]);
            $question = $stmt->fetch();

            // If no next/prev question found, keep the current question
            if (!$question) {
                $stmt = $pdo->prepare("
                    SELECT q.*
                    FROM questions q
                    WHERE q.question_id = ?
                ");
                $stmt->execute([$current_question_id]);
                $question = $stmt->fetch();
            }
        } else {
            // Get the specified question
            $stmt = $pdo->prepare("
                SELECT q.*
                FROM questions q
                WHERE q.question_id = ?
            ");
            $stmt->execute([$current_question_id]);
            $question = $stmt->fetch();
        }
    } else {
        // Get the first unanswered question
        $stmt = $pdo->prepare("
            SELECT q.*
            FROM questions q
            LEFT JOIN quiz_answers sa ON q.question_id = sa.question_id 
                AND sa.attempt_id = ?
            WHERE q.quiz_id = ? AND sa.answer_id IS NULL
            ORDER BY q.question_order ASC, q.question_id ASC
            LIMIT 1
        ");
        $stmt->execute([$attempt_id, $quiz_id]);
        $question = $stmt->fetch();

        // If no unanswered questions found, get the first question
        if (!$question) {
            $stmt = $pdo->prepare("
                SELECT q.*
                FROM questions q
                WHERE q.quiz_id = ?
                ORDER BY q.question_order ASC, q.question_id ASC
                LIMIT 1
            ");
            $stmt->execute([$quiz_id]);
            $question = $stmt->fetch();
        }
    }

    // Get total number of questions and number of answered questions
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM questions WHERE quiz_id = ?) as total_questions,
            (SELECT COUNT(DISTINCT sa.question_id) 
             FROM quiz_answers sa 
             JOIN quiz_attempts s ON sa.attempt_id = s.attempt_id 
             WHERE s.quiz_id = ? AND s.user_id = ? AND s.attempt_id = ?) as answered_questions
    ");
    $stmt->execute([$quiz_id, $quiz_id, $user_id, $attempt_id]);
    $progress = $stmt->fetch();

    // Get options for the question
    if ($question) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM question_options
            WHERE question_id = ?
            ORDER BY CASE 
                WHEN option_text = 'A' THEN 1
                WHEN option_text = 'B' THEN 2
                WHEN option_text = 'C' THEN 3
                WHEN option_text = 'D' THEN 4
                WHEN option_text = 'E' THEN 5
                WHEN option_text = 'F' THEN 6
                WHEN option_text = 'G' THEN 7
                WHEN option_text = 'H' THEN 8
                ELSE 9
            END
        ");
        $stmt->execute([$question['question_id']]);
        $options = $stmt->fetchAll();
    }

    // Get all questions for the quiz
    $stmt = $pdo->prepare("
        SELECT DISTINCT q.*
        FROM questions q
        WHERE q.quiz_id = ?
        ORDER BY q.question_order ASC, q.question_id ASC
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error in attempt_quiz.php: " . $e->getMessage());
    header('Location: dashboard.php?error=quiz_error');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Quiz</title>
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

        .dashboard-container {
            max-width: 1200px;
            margin: -40px auto 0;
            padding: 0;
            position: relative;
            z-index: 1;
        }

        .content-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .dashboard-header h1 {
            font-size: 24px;
            color: #333;
            margin: 0 0 10px 0;
        }

        .quiz-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .question-container {
            margin-top: 7rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .question-image {
            margin-bottom: 2rem;
            text-align: center;
        }

        .question-image img {
            max-width: 300px;
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .question-text {
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .question-text h2 {
            font-size: 1.5rem;
            color: #2c3e50;
            line-height: 1.4;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0 1rem;
        }

        .question-counter {
            font-size: 1.1rem;
            color: #2c3e50;
        }

        .timer {
            font-size: 1.1rem;
            color: #2c3e50;
            font-weight: bold;
            background: #d4e0ee;
            padding: 0.4rem 1rem;
            border-radius: 20px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f0f3f6;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .progress-fill {
            height: 100%;
            background: #d4e0ee;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .options-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .option-button {
            padding: 1.5rem;
            border: 2px solid #d4e0ee;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-align: center;
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            word-break: break-word;
            line-height: 1.4;
        }

        .option-button:hover {
            background: #d4e0ee;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .option-button.selected {
            background: #d4e0ee;
            border-color: #a3b8cc;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 600px) {
            .options-container {
                grid-template-columns: 1fr;
            }
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            padding: 0 1rem;
            margin-top: 2rem;
        }

        .nav-button {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            background: #d4e0ee;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
        }

        .nav-button:hover {
            background: #a3b8cc;
        }

        .submit-button {
            background: #d4e0ee;
        }

        .submit-button:hover {
            background: #a3b8cc;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2.5rem;
            gap: 1.5rem;
            padding: 0 1rem;
        }

        .navigation-buttons.submit-only {
            justify-content: flex-end;
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
        
        .bottom-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .question-counter {
            font-size: 1.2rem;
            color: #2c3e50;
        }
        
        .timer {
            font-size: 1.2rem;
            color: #2c3e50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div id="particles-js"></div>
    <div class="dashboard-container">
        <div class="quiz-container">
            <div class="question-container">
                <?php if ($question): ?>
                    <?php if ($question['image_path']): ?>
                        <div class="question-image">
                            <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="Question Image">
                        </div>
                    <?php endif; ?>

                    <div class="question-text">
                        <h2><?php echo htmlspecialchars($question['question_text']); ?></h2>
                    </div>

                    <div class="progress-info">
                        <div class="question-counter">
                            Question <?php echo $question['question_order']; ?> of <?php echo $progress['total_questions']; ?>
                        </div>
                        <div class="timer" id="timer">00:00</div>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($progress['total_questions'] > 0) ? ($progress['answered_questions'] / $progress['total_questions'] * 100) : 0; ?>%"></div>
                    </div>

                    <div class="options-container">
                        <?php foreach ($options as $option): ?>
                            <button 
                                class="option-button <?php echo isset($selected_answer) && $selected_answer == $option['option_text'] ? 'selected' : ''; ?>"
                                onclick="submitAnswer(<?php echo $option['option_id']; ?>, this)"
                                <?php echo isset($selected_answer) ? 'disabled' : ''; ?>
                            >
                                <?php echo htmlspecialchars($option['option_text']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="navigation-buttons">
                        <?php if ($question['question_order'] > 1): ?>
                            <a href="?id=<?php echo $quiz_id; ?>&question=<?php echo $question['question_id']; ?>&direction=prev" class="nav-button prev-button">Previous</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <?php if ($question['question_order'] < $progress['total_questions']): ?>
                            <a href="?id=<?php echo $quiz_id; ?>&question=<?php echo $question['question_id']; ?>&direction=next" class="nav-button next-button">Next</a>
                        <?php else: ?>
                            <button onclick="submitQuiz()" class="nav-button submit-button">Submit Quiz</button>
                        <?php endif; ?>
                    </div>

                    <script>
                        function submitQuiz() {
                            if (!selectedAnswer) {
                                alert('Please select an answer before submitting the quiz.');
                                return;
                            }

                            const formData = new FormData();
                            formData.append('quiz_id', <?php echo $quiz_id; ?>);
                            formData.append('question_id', <?php echo $question ? $question['question_id'] : 'null'; ?>);
                            formData.append('answer', selectedAnswer);
                            formData.append('final', 'true');

                            fetch('submit_answer.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    window.location.href = `resultpage.php?quiz_id=<?php echo $quiz_id; ?>`;
                                } else {
                                    alert(data.error || 'Failed to submit quiz');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('There was a problem submitting your quiz. Please try again.');
                            });
                        }
                    </script>
                <?php else: ?>
                    <p>No questions available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded - callback');
        });
    </script>

    <script>
        // Timer functionality
        let startTime = new Date().getTime();
        
        function updateTimer() {
            const currentTime = new Date().getTime();
            const timeDiff = currentTime - startTime;
            const minutes = Math.floor(timeDiff / (1000 * 60));
            const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
            
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        // Update timer every second
        setInterval(updateTimer, 1000);
        updateTimer(); // Initial call

        // Keep track of the selected answer
        let selectedAnswer = null;

        function submitAnswer(optionId, buttonElement) {
            console.log('Submit answer function called');
            console.log('Option ID:', optionId);
            
            const quizId = <?php echo $quiz_id; ?>;
            const questionId = <?php echo $question ? $question['question_id'] : 'null'; ?>;
            
            // Remove selected class from all options
            document.querySelectorAll('.option-button').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Add selected class to clicked button
            buttonElement.classList.add('selected');
            selectedAnswer = optionId;
            
            const formData = new FormData();
            formData.append('quiz_id', quizId);
            formData.append('question_id', questionId);
            formData.append('answer', optionId);

            console.log('Sending answer to server');
            
            fetch('submit_answer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    // Enable the next/submit button after answering
                    const actionButton = document.querySelector('.nav-button.next-button');
                    if (actionButton) {
                        actionButton.href = `?id=${quizId}&question=${questionId}&direction=next`;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('There was a problem saving your answer. Please try again.');
            });
        }
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
