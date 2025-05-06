<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/quiz-card.php';

$page_title = "Teacher Dashboard";

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

try {
    // Get teacher's information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_type = 'teacher'");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // Get total number of quizzes created by the teacher
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quizzes WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $quizCount = $stmt->fetchColumn();

    // Get all quizzes created by this teacher with submission counts and statistics
    $stmt = $pdo->prepare("
        WITH QuizStats AS (
            SELECT 
                quiz_id,
                COUNT(DISTINCT user_id) as unique_attempts,
                COUNT(*) as total_attempts,
                AVG(score) as average_score,
                MAX(attempt_id) as latest_attempt_id
            FROM quiz_attempts
            GROUP BY quiz_id
        )
        SELECT 
            q.*,
            COALESCE(qs.unique_attempts, 0) as unique_attempts,
            COALESCE(qs.total_attempts, 0) as total_attempts,
            COALESCE(qs.average_score, 0) as average_score,
            qs.latest_attempt_id
        FROM quizzes q 
        LEFT JOIN QuizStats qs ON q.quiz_id = qs.quiz_id
        WHERE q.teacher_id = ?
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $quizzes = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Teacher Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <link href="css/quiz-card-new.css" rel="stylesheet">
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
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            margin-bottom: 2rem;
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-title-container {
            text-align: left;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .action-btn {
            padding: 1rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            backdrop-filter: blur(5px);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
            text-decoration: none;
            color: #2c3e50;
        }

        .dashboard-title {
            font-size: 2.2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .quizzes-count {
            font-size: 1.1rem;
            color: #666;
            margin: 0.5rem 0;
        }

        .quiz-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .action-buttons {
                justify-content: center;
            }

            .dashboard-container {
                padding: 1rem;
            }
        }

        /* Delete Confirmation Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            font-family: 'Tiny5', sans-serif;
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-body {
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #333;
        }

        .modal-footer {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .modal-button {
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Tiny5', sans-serif;
            font-size: 1em;
            min-width: 80px;
        }

        .cancel-button {
            background-color: #6366f1;
            color: white;
        }

        .delete-button {
            background-color: #ef4444;
            color: white;
        }

        .cancel-button:hover {
            background-color: #4f46e5;
        }

        .delete-button:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div id="particles-js"></div>
        <div class="dashboard-container">
            <div class="content-container">
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="dashboard-header">
                    <div class="dashboard-title-container">
                        <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($teacher['name']); ?>!</h1>
                        <p class="quizzes-count">Total Quizzes: <?php echo $quizCount; ?></p>
                    </div>
                    <div class="action-buttons">
                        <a href="quizcreation.php" class="action-btn">Create New Quiz</a>
                    </div>
                </div>
                
                <div class="quiz-container">
                    <?php if (empty($quizzes)): ?>
                        <p class="no-quizzes">You haven't created any quizzes yet. Click "Create New Quiz" to get started!</p>
                    <?php else: ?>
                        <?php 
                        // Track rendered quiz IDs to prevent duplicates
                        $renderedQuizIds = [];
                        foreach ($quizzes as $quiz): 
                            // Skip if this quiz has already been rendered
                            if (in_array($quiz['quiz_id'], $renderedQuizIds)) {
                                continue;
                            }
                            $renderedQuizIds[] = $quiz['quiz_id'];
                            
                            $buttons = [
                                [
                                    'url' => "preview_quiz.php?quiz_id=" . $quiz['quiz_id'],
                                    'text' => 'View',
                                    'class' => 'view-btn'
                                ],
                                [
                                    'url' => "edit_quiz.php?quiz_id=" . $quiz['quiz_id'],
                                    'text' => 'Edit',
                                    'class' => 'edit-btn'
                                ],
                                [
                                    'url' => "grade_quiz.php?quiz_id=" . $quiz['quiz_id'],
                                    'text' => 'Grade',
                                    'class' => 'grade-btn'
                                ]
                            ];

                            // Add feedback button if there are attempts
                            if ($quiz['total_attempts'] > 0) {
                                $buttons[] = [
                                    'url' => "feedback_quiz.php?quiz_id=" . $quiz['quiz_id'] . "&attempt_id=" . $quiz['latest_attempt_id'],
                                    'text' => 'Feedback (' . $quiz['total_attempts'] . ' attempts)',
                                    'class' => 'feedback-btn'
                                ];
                            }

                            // Add delete button last
                            $buttons[] = [
                                'text' => 'Delete',
                                'class' => 'delete-btn',
                                'onclick' => "confirmDelete(" . $quiz['quiz_id'] . ", '" . htmlspecialchars($quiz['title']) . "')"
                            ];
                            
                            // Call the shared quiz card component
                            renderQuizCard($quiz, 'teacher', [
                                'buttons' => $buttons,
                                'teacher_name' => $teacher['name']
                            ]);
                        endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this quiz?</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST" action="delete_quiz.php" style="display: inline;">
                    <input type="hidden" id="quizIdInput" name="quiz_id" value="">
                    <button type="button" class="modal-button delete-button" onclick="document.getElementById('deleteForm').submit()">Delete</button>
                    <button type="button" class="modal-button cancel-button" onclick="closeDeleteModal()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                        "value": "#ffc0cb"
                    },
                    "shape": {
                        "type": "circle"
                    },
                    "opacity": {
                        "value": 0.5,
                        "random": true
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
                            "mode": "bubble"
                        },
                        "onclick": {
                            "enable": true,
                            "mode": "repulse"
                        },
                        "resize": true
                    }
                },
                "retina_detect": true
            });
        });

        let quizToDelete = null;

        function confirmDelete(quizId, quizTitle) {
            document.getElementById('quizIdInput').value = quizId;
            const modal = document.getElementById('deleteModal');
            const message = document.getElementById('deleteMessage');
            message.textContent = `Are you sure you want to delete this quiz?`;
            modal.style.display = 'block';
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
            document.getElementById('quizIdInput').value = '';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>