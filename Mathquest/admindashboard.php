<?php
session_start();
require_once 'config/db_connect.php';
require_once 'includes/quiz-card.php';

$page_title = "Admin Dashboard";
require_once 'includes/header.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize variables with default values
$totalUsers = 0;
$totalQuizzes = 0;
$totalSubmissions = 0;
$total_attempts = 0;
$quizzes = [];

try {
    // First, update any existing quiz attempts that have scores but no completed_at timestamp
    $stmt = $pdo->prepare("
        UPDATE quiz_attempts 
        SET completed_at = attempted_at 
        WHERE score IS NOT NULL 
        AND completed_at IS NULL
    ");
    $stmt->execute();

    // Get selected teacher if any
    $selectedTeacher = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
    
    // Base query for fetching quizzes with teacher information
    $query = "
        SELECT 
            q.quiz_id,
            q.title,
            q.description,
            q.image_path,
            q.created_at,
            q.due_date,
            q.is_active,
            u.name as teacher_name,
            (
                SELECT COUNT(DISTINCT qa.user_id)
                FROM quiz_attempts qa
                WHERE qa.quiz_id = q.quiz_id
                AND qa.score IS NOT NULL 
                AND qa.completed_at IS NOT NULL
            ) as attempt_count,
            (
                SELECT ROUND(AVG(CAST(qa.score AS FLOAT)), 2)
                FROM quiz_attempts qa
                WHERE qa.quiz_id = q.quiz_id
                AND qa.score IS NOT NULL
                AND qa.completed_at IS NOT NULL
            ) as average_score,
            (
                SELECT COUNT(*)
                FROM questions
                WHERE quiz_id = q.quiz_id
            ) as question_count,
            (
                SELECT COUNT(*)
                FROM quiz_attempts qa
                WHERE qa.quiz_id = q.quiz_id
                AND qa.score IS NOT NULL
                AND qa.completed_at IS NOT NULL
            ) as completed_attempts
        FROM quizzes q
        JOIN users u ON q.teacher_id = u.user_id
    ";
    
    $params = [];
    
    // Add teacher filter if selected
    if ($selectedTeacher) {
        $query .= " WHERE q.teacher_id = ?";
        $params[] = $selectedTeacher;
    }
    
    $query .= " ORDER BY q.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $quizzes = $stmt->fetchAll();

    // Get total number of users (excluding admin)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE user_type != 'admin'
    ");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();

    // Get total number of quizzes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM quizzes
    ");
    $stmt->execute();
    $totalQuizzes = $stmt->fetchColumn();

    // Get total number of completed quiz attempts (unique users)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) 
        FROM quiz_attempts 
        WHERE score IS NOT NULL
    ");
    $stmt->execute();
    $totalSubmissions = $stmt->fetchColumn();

    // Get total number of attempts (unique users)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) 
        FROM quiz_attempts 
        WHERE score IS NOT NULL
    ");
    $stmt->execute();
    $total_attempts = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MathQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <link href="css/quiz-card-new.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Tiny5', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            padding-top: 80px;
        }

        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .page-title-container {
            text-align: center;
            margin: 4rem auto 4rem;
            position: relative;
            z-index: 1;
            padding-top: 2rem;
        }

        .page-title {
            font-size: 3rem;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #ffdcdc, transparent);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-top: 1rem;
            font-weight: normal;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto 2rem;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ffdcdc, #ffecec);
        }

        .stat-card h3 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .stat-card p {
            font-size: 2.5rem;
            color: #2c3e50;
            font-weight: bold;
            margin: 0;
        }

        .controls-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #ffdcdc;
            box-shadow: 0 0 0 3px rgba(255, 220, 220, 0.3);
        }

        .filter-box {
            flex: 1;
            min-width: 250px;
        }

        .filter-box select {
            width: 100%;
            padding: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
            cursor: pointer;
            color: #2c3e50;
        }

        .filter-box select:focus {
            outline: none;
            border-color: #ffdcdc;
            box-shadow: 0 0 0 3px rgba(255, 220, 220, 0.3);
        }

        .filter-box select option {
            background: white;
            color: #2c3e50;
            padding: 10px;
        }

        .filter-box select option:first-child {
            font-weight: 500;
            color: #2c3e50;
        }

        .filter-box select:hover {
            border-color: #ffdcdc;
            background: rgba(255, 220, 220, 0.1);
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .dashboard-container {
                padding: 0 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .controls-container {
                flex-direction: column;
                gap: 1rem;
            }

            .search-box,
            .filter-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>

    <div class="page-title-container">
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle">Manage and monitor system activity</p>
    </div>

    <div class="dashboard-container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Quizzes</h3>
                <p><?php echo $totalQuizzes; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Submissions</h3>
                <p><?php echo $totalSubmissions; ?></p>
            </div>
            <div class="stat-card">
                <h3>Total Attempts</h3>
                <p><?php echo $total_attempts; ?></p>
            </div>
        </div>

        <div class="controls-container">
            <div class="search-box">
                <input type="text" id="searchQuiz" placeholder="Search quizzes..." onkeyup="filterQuizzes()">
            </div>
            <div class="filter-box">
                <select id="filterTeacher" onchange="filterByTeacher(this)">
                    <option value="">All Teachers</option>
                    <?php
                    $stmt = $pdo->query("SELECT DISTINCT u.user_id, u.name FROM users u 
                                       JOIN quizzes q ON u.user_id = q.teacher_id 
                                       WHERE u.user_type = 'teacher' 
                                       ORDER BY u.name");
                    while ($teacher = $stmt->fetch()) {
                        $selected = isset($_GET['teacher_id']) && $_GET['teacher_id'] == $teacher['user_id'] ? 'selected' : '';
                        echo "<option value=\"{$teacher['user_id']}\" {$selected}>{$teacher['name']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="quizzes-grid quiz-container">
            <?php if (empty($quizzes)): ?>
                <p style="text-align: center; grid-column: 1/-1; color: #666;">No quizzes found.</p>
            <?php else: ?>
                <?php foreach ($quizzes as $quiz): ?>
                    <?php
                    $options = [
                        'buttons' => [
                            [
                                'url' => "view_quiz.php?quiz_id=" . $quiz['quiz_id'],
                                'text' => 'View',
                                'class' => 'view-btn'
                            ],
                            [
                                'url' => "grade_quiz.php?quiz_id=" . $quiz['quiz_id'],
                                'text' => 'Grade',
                                'class' => 'grade'
                            ],
                            [
                                'url' => "quiz_feedback.php?quiz_id=" . $quiz['quiz_id'],
                                'text' => 'Feedback',
                                'class' => 'feedback'
                            ]
                        ],
                        'teacher_name' => $quiz['teacher_name']
                    ];
                    renderQuizCard($quiz, 'admin', $options);
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded');
        });

        function filterByTeacher(select) {
            window.location.href = 'admindashboard.php' + (select.value ? '?teacher_id=' + select.value : '');
        }

        function filterQuizzes() {
            const searchText = document.getElementById('searchQuiz').value.toLowerCase();
            const quizCards = document.querySelectorAll('.quiz-card');

            quizCards.forEach(card => {
                const title = card.querySelector('.quiz-title').textContent.toLowerCase();
                const matchesSearch = title.includes(searchText);
                card.style.display = matchesSearch ? 'flex' : 'none';
            });
        }
    </script>
    <style>
        footer {
            position: relative;
            z-index: 2;
        }
        .quiz-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;  
            position: relative;
            transition: transform 0.2s ease;
        }

        .quiz-container {
            display: grid;
            gap: 30px;  
            padding: 20px;
        }
    </style>
</body>
</html>