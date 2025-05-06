<?php
session_start();
require_once 'config/db_connect.php';
$page_title = 'Activity History';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's quiz history
try {
    $stmt = $pdo->prepare("
        SELECT qa.*, q.title as quiz_title 
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.quiz_id
        WHERE qa.user_id = ?
        ORDER BY qa.attempt_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $quiz_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching quiz history: " . $e->getMessage());
    $quiz_history = [];
}

include 'includes/header.php';
?>

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
        padding-top: 80px;
    }

    main {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 1;
        padding: 40px 20px;
    }

    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .content-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .activity-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .activity-header h1 {
        color: #FF69B4;
        font-size: 2.5em;
        margin-bottom: 15px;
    }

    .quiz-history {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background-color: #FFDCDC;
        border-radius: 10px;
        overflow: hidden;
    }

    .quiz-history th,
    .quiz-history td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        color: #333;
    }

    .quiz-history th {
        background-color: #FFDCDC;
        color: #333;
        font-weight: bold;
        border-bottom: 2px solid rgba(255, 255, 255, 0.5);
    }

    .quiz-history tr:hover {
        background-color: rgba(255, 255, 255, 0.2);
    }

    .quiz-history .score {
        font-weight: bold;
        color: #333;
    }

    .no-history {
        text-align: center;
        padding: 40px;
        color: #666;
        font-size: 1.2em;
    }

    .start-quiz-btn {
        display: inline-block;
        padding: 12px 24px;
        background: #FFDCDC;
        color: #333;
        text-decoration: none;
        border-radius: 5px;
        transition: background 0.3s ease;
        margin-top: 20px;
    }

    .start-quiz-btn:hover {
        background: #ffdcdc;
    }

    @media (max-width: 768px) {
        .activity-header h1 {
            font-size: 2em;
        }

        .quiz-history th,
        .quiz-history td {
            padding: 10px;
            font-size: 0.9em;
        }
    }
</style>

<main>
    <div id="particles-js" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;"></div>
    <div class="container">
        <div class="content-container">
            <div class="activity-header">
                <h1>Quiz History</h1>
                <p>View your previous quiz attempts and scores</p>
            </div>
            <?php if (empty($quiz_history)): ?>
                <div class="no-history">
                    <p>You haven't taken any quizzes yet.</p>
                    <a href="quiz.php" class="start-quiz-btn">Start Your First Quiz</a>
                </div>
            <?php else: ?>
                <table class="quiz-history">
                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Score</th>
                            <th>Date Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quiz_history as $attempt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                <td class="score"><?php echo htmlspecialchars($attempt['score']); ?></td>
                                <td><?php echo date('F j, Y, g:i a', strtotime($attempt['attempt_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="quiz.php" class="start-quiz-btn">Take Another Quiz</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
<script>
    particlesJS.load('particles-js', 'particles.json', function() {
        console.log('particles.js loaded');
    });
</script>

<?php include 'includes/footer.php'; ?>
