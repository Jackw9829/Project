<?php
session_start();
require_once 'config/db_connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$error = null;
$quizzes = [];  // Initialize quizzes array
$user_id = $_SESSION['user_id'];

try {
    // Get user's name
    $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userName = $user['name'] ?? 'Student';

    // Get all available quizzes
    $stmt = $pdo->prepare("
        SELECT 
            q.*,
            u.name as teacher_name,
            CASE WHEN q.due_date < NOW() OR q.is_active = 0 THEN 0 ELSE 1 END as is_active,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.quiz_id) as question_count,
            (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? AND score IS NOT NULL) as highest_score,
            (SELECT attempted_at FROM quiz_attempts 
             WHERE quiz_id = q.quiz_id AND user_id = ? AND score IS NOT NULL 
             ORDER BY score DESC, attempted_at DESC LIMIT 1) as submission_date,
            (SELECT attempt_id FROM quiz_attempts 
             WHERE quiz_id = q.quiz_id AND user_id = ? AND score IS NOT NULL 
             ORDER BY score DESC, attempted_at DESC LIMIT 1) as latest_attempt_id,
            CASE 
                WHEN q.is_active = 1 
                AND (q.due_date IS NULL OR q.due_date >= NOW())
                AND NOT EXISTS (SELECT 1 FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? AND score IS NOT NULL)
                THEN 1
                ELSE 0
            END as can_attempt,
            CASE 
                WHEN EXISTS (SELECT 1 FROM quiz_attempts WHERE quiz_id = q.quiz_id AND user_id = ? AND score IS NOT NULL) 
                THEN 1
                ELSE 0
            END as can_review
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.user_id
        WHERE q.is_active = 1
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    echo "<!-- Debug: Found " . count($quizzes) . " quizzes -->\n";
    foreach ($quizzes as $quiz) {
        echo "<!-- Quiz ID: " . $quiz['quiz_id'] . " Title: " . $quiz['title'] . " -->\n";
    }

} catch (PDOException $e) {
    error_log("PDO Error in dashboard.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("General Error in dashboard.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error = "An error occurred: " . $e->getMessage();
}

// Include the quiz card renderer
require_once 'includes/quiz-card.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Student Dashboard</title>
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
            margin-top: 1rem;
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

        .quiz-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            padding: 1rem 0;
        }

        .error-message {
            background-color: #ffe6e6;
            color: #d63031;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div id="particles-js"></div>
        <div class="dashboard-container">
            <div class="content-container">
                <div class="dashboard-header">
                    <div class="dashboard-title-container">
                        <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
                        <p class="quizzes-count"><?php echo count($quizzes); ?> Active <?php echo count($quizzes) === 1 ? 'Quiz' : 'Quizzes'; ?> Available</p>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="quiz-grid">
                    <?php 
                    // Debug output
                    echo "<!-- Starting to render quizzes -->\n";
                    
                    // Track rendered quiz IDs to prevent duplicates
                    $renderedQuizIds = [];
                    foreach ($quizzes as $quiz): 
                        echo "<!-- Processing quiz ID: " . $quiz['quiz_id'] . " -->\n";
                        
                        // Skip if this quiz has already been rendered
                        if (in_array($quiz['quiz_id'], $renderedQuizIds)) {
                            echo "<!-- Skipping duplicate quiz ID: " . $quiz['quiz_id'] . " -->\n";
                            continue;
                        }
                        $renderedQuizIds[] = $quiz['quiz_id'];
                        
                        // Render the quiz card
                        echo renderQuizCard($quiz, 'student'); 
                    endforeach; 
                    
                    if (empty($quizzes)) {
                        echo "<p>No quizzes available at this time.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <!-- Particles.js Scripts -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded - callback');
        });
    </script>
</body>
</html>
