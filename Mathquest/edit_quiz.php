<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['quiz_id'])) {
    header('Location: teacherdashboard.php');
    exit();
}

$quiz_id = $_GET['quiz_id'];
$error = '';
$success = '';
$quiz = null; // Initialize quiz variable

try {
    // Fetch quiz details
    $stmt = $pdo->prepare("
        SELECT *
        FROM quizzes
        WHERE quiz_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error'] = "Quiz not found or you don't have permission to edit it.";
        header('Location: teacherdashboard.php');
        exit();
    }

    // Fetch questions for this quiz
    $stmt = $pdo->prepare("
        SELECT q.*, COUNT(qo.option_id) as option_count
        FROM questions q
        LEFT JOIN question_options qo ON q.question_id = qo.question_id
        WHERE q.quiz_id = ?
        GROUP BY q.question_id
        ORDER BY q.question_id
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['title']) || empty($_POST['description'])) {
            $error = "Title and description are required.";
        } else {
            $pdo->beginTransaction();

            try {
                // Update quiz details
                $stmt = $pdo->prepare("
                    UPDATE quizzes 
                    SET title = ?, description = ?
                    WHERE quiz_id = ? AND teacher_id = ?
                ");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $quiz_id,
                    $_SESSION['user_id']
                ]);

                $pdo->commit();
                $success = "Quiz updated successfully!";
                
                // Refresh quiz details
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM quizzes
                    WHERE quiz_id = ? AND teacher_id = ?
                ");
                $stmt->execute([$quiz_id, $_SESSION['user_id']]);
                $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to update quiz: " . $e->getMessage();
                error_log($error);
            }
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Edit Quiz</title>
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
            z-index: -1;
        }

        .edit-quiz-container {
            max-width: 1200px;
            margin: 6rem auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .content-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            margin: 0;
            color: #333;
            font-size: 2rem;
        }

        .quiz-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 1.1rem;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        textarea:focus {
            border-color: #333;
            outline: none;
        }

        textarea {
            height: 150px;
            resize: vertical;
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

        .questions-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid #eee;
        }

        .questions-section h2 {
            margin-bottom: 1.5rem;
            color: #333;
        }

        .question-list {
            list-style: none;
        }

        .question-item {
            background: #f9f9f9;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 6px;
            border: 2px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease;
        }

        .question-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .question-text {
            flex: 1;
            margin-right: 1rem;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 2px solid #ef9a9a;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 2px solid #a5d6a7;
        }

        @media (max-width: 768px) {
            .edit-quiz-container {
                padding: 1rem;
            }

            .content-container {
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
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div id="particles-js"></div>
    <div class="edit-quiz-container">
        <div class="content-container">
            <div class="dashboard-header">
                <h1>Edit Quiz</h1>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($quiz): ?>
                <form class="quiz-form" method="POST">
                    <div class="form-group">
                        <label for="title">Quiz Title:</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" required><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                    </div>

                    <button type="submit" class="btn">Save Changes</button>
                    <a href="preview_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-secondary">Preview Quiz</a>
                </form>

                <div class="questions-section">
                    <div class="dashboard-header">
                        <h2>Questions</h2>
                        <a href="question.php?quiz_id=<?php echo $quiz_id; ?>&question_id=new" class="btn">Add Question</a>
                    </div>

                    <?php if (!empty($questions)): ?>
                        <ul class="question-list">
                            <?php foreach ($questions as $question): ?>
                                <li class="question-item">
                                    <div class="question-text">
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                    </div>
                                    <div class="question-actions">
                                        <a href="question.php?quiz_id=<?php echo $quiz_id; ?>&question_id=<?php echo $question['question_id']; ?>" class="btn btn-secondary">Edit</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="no-questions">No questions added yet. Click "Add Question" to get started!</p>
                    <?php endif; ?>
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
