<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection and admin styles
require_once '../config/db_connect.php';
require_once 'admin_styles.php';

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Initialize variables
$error = '';
$success = '';
$challenge = null;
$questions = [];

// Check if challenge ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $challengeId = (int)$_GET['id'];
    
    try {
        // Get database connection
        $pdo = getDbConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        // Fetch challenge details
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ?");
        $stmt->execute([$challengeId]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$challenge) {
            throw new Exception('Challenge not found');
        }
        
        // Fetch challenge questions
        $qStmt = $pdo->prepare("SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_order ASC");
        $qStmt->execute([$challengeId]);
        $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
// Redirect to view_item.php with the appropriate parameters
if (isset($_GET['id'])) {
    $challengeId = intval($_GET['id']);
    header("Location: view_item.php?type=challenge&id=$challengeId");
    exit();
} else {
    header("Location: managequiz.php");
    exit();
}
?>
        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .challenge-title {
            font-size: 24px;
            color: var(--primary-color);
            font-family: 'Press Start 2P', cursive;
            margin-bottom: 10px;
        }
        
        .challenge-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid var(--primary-color);
        }
        
        .meta-label {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .challenge-description {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            white-space: pre-wrap;
        }
        
        .questions-container {
            margin-top: 30px;
        }
        
        .question-block {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .question-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-family: 'Press Start 2P', cursive;
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .question-text {
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
        }
        
        .option-item.correct {
            background-color: rgba(0, 128, 0, 0.2);
            border: 1px solid #00ff00;
        }
        
        .option-label {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-color);
            color: #000;
            border-radius: 50%;
            margin-right: 10px;
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .delete-form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Navigation -->
        <div class="admin-sidebar">
            <div class="logo-container">
                <a href="dashboard.php" class="logo">
                    <span class="logo-text">CodaQuest</span>
                    <span class="logo-admin">ADMIN</span>
                </a>
            </div>
            
            <nav class="admin-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="material-icons">dashboard</i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="nav-item">
                    <i class="material-icons">people</i>
                    <span>Users</span>
                </a>
                <a href="content.php" class="nav-item">
                    <i class="material-icons">library_books</i>
                    <span>Content</span>
                </a>
                <a href="managequiz.php" class="nav-item">
                    <i class="material-icons">quiz</i>
                    <span>Quizzes</span>
                </a>
                <a href="learning_paths.php" class="nav-item">
                    <i class="material-icons">alt_route</i>
                    <span>Learning Paths</span>
                </a>
                <a href="admin_messages.php" class="nav-item">
                    <i class="material-icons">message</i>
                    <span>Messages</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="material-icons">settings</i>
                    <span>Settings</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <i class="material-icons">logout</i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>
        
        <div class="admin-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="material-icons">quiz</i> View Challenge</h1>
                </div>
                <!-- User avatar removed as per requirements -->
            </div>
            
            <div class="dashboard-content">
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="material-icons">error</i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="material-icons">check_circle</i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($challenge): ?>
                    <div class="challenge-details">
                        <div class="challenge-header">
                            <h2 class="challenge-title"><?php echo htmlspecialchars($challenge['challenge_name']); ?></h2>
                            
                            <div class="action-buttons">
                                <a href="managequiz.php" class="btn btn-secondary">
                                    <i class="material-icons">arrow_back</i> Back to Challenges
                                </a>
                                
                                <form class="delete-form" method="post" onsubmit="return confirm('Are you sure you want to delete this challenge? This action cannot be undone.');">
                                    <input type="hidden" name="challenge_id" value="<?php echo $challenge['challenge_id']; ?>">
                                    <button type="submit" name="delete" class="btn btn-danger">
                                        <i class="material-icons">delete</i> Delete Challenge
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="challenge-meta">
                            <div class="meta-item">
                                <span class="meta-label">ID:</span> #<?php echo $challenge['challenge_id']; ?>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Difficulty:</span> <?php echo htmlspecialchars($challenge['difficulty_level']); ?>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Points:</span> <?php echo $challenge['points']; ?>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Created:</span> <?php echo date('M d, Y', strtotime($challenge['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="section-header">
                            <h2 class="section-title"><i class="material-icons section-icon">description</i> Description</h2>
                        </div>
                        
                        <div class="challenge-description">
                            <?php 
                            // Display description without the appended questions part
                            $descriptionParts = explode('**Challenge Questions:**', $challenge['description']);
                            echo nl2br(htmlspecialchars($descriptionParts[0])); 
                            ?>
                        </div>
                        
                        <div class="section-header">
                            <h2 class="section-title"><i class="material-icons section-icon">quiz</i> Challenge Questions</h2>
                        </div>
                        
                        <div class="questions-container">
                            <?php if (empty($questions)): ?>
                                <p class="no-items">No questions found for this challenge.</p>
                            <?php else: ?>
                                <?php foreach ($questions as $index => $question): ?>
                                    <div class="question-block">
                                        <div class="question-header">
                                            <i class="material-icons">quiz</i> Question <?php echo $index + 1; ?>
                                        </div>
                                        <div class="question-text">
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                        </div>
                                        
                                        <div class="options-container">
                                            <?php 
                                            $options = [
                                                'a' => $question['option_a'],
                                                'b' => $question['option_b'],
                                                'c' => $question['option_c'],
                                                'd' => $question['option_d']
                                            ];
                                            $correctAnswer = $question['correct_answer'];
                                            
                                            foreach ($options as $key => $optionText): 
                                                $isCorrect = ($key === $correctAnswer);
                                                $optionClass = $isCorrect ? 'option-item correct' : 'option-item';
                                            ?>
                                                <div class="<?php echo $optionClass; ?>">
                                                    <div class="option-label"><?php echo strtoupper($key); ?></div>
                                                    <div class="option-text"><?php echo htmlspecialchars($optionText); ?></div>
                                                    <?php if ($isCorrect): ?>
                                                        <i class="material-icons" style="color: #00ff00; margin-left: 10px;">check_circle</i>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="not-found">
                        <i class="material-icons">search_off</i>
                        <p>Challenge not found or invalid ID provided.</p>
                        <a href="managequiz.php" class="btn btn-primary">Back to Challenges</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>