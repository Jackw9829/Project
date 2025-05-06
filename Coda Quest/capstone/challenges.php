<?php
// Include authentication check to redirect non-logged-in users
require_once 'includes/auth_check.php';

// Include database connection
require_once 'config/db_connect.php';

// Set page title
$pageTitle = "CodaQuest - Challenges";

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);

// Fetch challenges from database if user is logged in
$challenges = [];
if ($isLoggedIn && isset($_SESSION['student_id'])) {
    $studentId = $_SESSION['student_id'];
    
    // Get all challenges with attempt count
    $sql = "SELECT c.*, 
            CASE WHEN MAX(ca.is_completed) = 1 THEN 1 ELSE 0 END AS completed,
            COUNT(ca.attempt_id) AS attempt_count
            FROM challenges c
            LEFT JOIN challenge_attempts ca ON c.challenge_id = ca.challenge_id AND ca.student_id = ?
            GROUP BY c.challenge_id
            ORDER BY c.difficulty_level, c.challenge_name";
    
    $result = executeQuery($sql, [$studentId]);
    if (is_array($result)) {
        $challenges = $result;
    }
} else {
    // For non-logged in users or admins, just show challenges without completion status
    $sql = "SELECT c.*, 0 AS completed
            FROM challenges c
            ORDER BY c.difficulty_level, c.challenge_name";
    
    $result = executeQuery($sql, []);
    if (is_array($result)) {
        $challenges = $result;
    }
}

// Handle challenge completion
if ($isLoggedIn && isset($_SESSION['student_id']) && isset($_POST['complete_challenge'])) {
    $challengeId = intval($_POST['challenge_id']);
    $studentId = $_SESSION['student_id'];
    
    // Check if already completed - more thorough check to prevent duplicates
    $checkSql = "SELECT * FROM challenge_attempts WHERE student_id = ? AND challenge_id = ?";
    $existing = executeQuery($checkSql, [$studentId, $challengeId]);
    
    // Don't create a new attempt if there's already one
    if (!$existing || count($existing) === 0) {
        // No attempt exists at all, or there's an incomplete attempt
        // Get challenge points
        $pointsSql = "SELECT points FROM challenges WHERE challenge_id = ?";
        $pointsResult = executeQuery($pointsSql, [$challengeId]);
        $points = $pointsResult[0]['points'] ?? 0;
        
        // Mark challenge as completed
        $completeSql = "INSERT INTO challenge_attempts (student_id, challenge_id, start_time, end_time, score, is_completed) VALUES (?, ?, NOW(), NOW(), ?, 1)";
        executeQuery($completeSql, [$studentId, $challengeId, $points]);
        
        // Add points to user's total
        $updatePointsSql = "UPDATE students SET total_points = total_points + ? WHERE student_id = ?";
        executeQuery($updatePointsSql, [$points, $studentId]);
        
        // Log activity
        $logSql = "INSERT INTO activity_log (student_id, activity_type, content_id, details) VALUES (?, 'challenge_completed', ?, ?)";
        executeQuery($logSql, [$studentId, $challengeId, "Completed challenge $challengeId"]);
    }
}

// Additional styles for challenges page
$additionalStyles = <<<EOT
<style>
    /* Matrix Background */
    #matrix-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.1;
    }
        
    body {
        padding: 0;
        margin: 0;
    }
    
    .container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        background-color: var(--card-bg);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        text-align: center;
        border: 4px solid var(--border-color);
    }
    
    .page-title {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        text-transform: uppercase;
        text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
    }
    
    .page-description {
        color: var(--text-color);
        max-width: 800px;
        margin: 0 auto;
    }
    
    .challenges-grid {
        padding: 20px 0;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        margin: 0 auto;
        max-width: 1400px;
    }
    
    @media (max-width: 1200px) {
        .challenges-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .challenges-grid {
            grid-template-columns: 1fr;
        }
        
        .challenge-card {
            min-height: auto;
        }
    }
    
    .challenge-card {
        background-color: var(--card-bg);
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid var(--border-color);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        min-height: 320px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
    }
    
    .challenge-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        border-color: var(--primary-color);
    }
    
    .challenge-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 5px;
        background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
    }
    
    .challenge-header {
        margin-bottom: 10px;
    }
    
    .challenge-difficulty {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.9rem;
        padding: 4px 12px;
        border-radius: 16px;
        background-color: var(--card-bg);
        border: 1px solid var(--primary-color);
        color: var(--text-color);
        margin-bottom: 12px;
    }
    
    .challenge-content {
        padding: 0;
        display: flex;
        flex-direction: column;
    }
    
    .challenge-info {
        padding: 20px;
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    .challenge-info::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: 0;
        width: 30px;
        height: 30px;
        background: linear-gradient(135deg, transparent 50%, var(--border-color) 50%);
        z-index: -1;
    }
    
    .quiz-title-row {
        margin-bottom: 8px;
    }
    
    .quiz-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-color);
        margin: 0;
        line-height: 1.4;
    }
    
    .challenge-description {
        color: var(--text-color);
        font-size: 0.95rem;
        line-height: 1.5;
        margin: 0;
        opacity: 0.9;
        flex-grow: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }
    
    .quiz-meta {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-top: auto;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }
    
    .meta-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 8px;
        border-radius: 8px;
        background-color: rgba(var(--primary-color-rgb), 0.1);
    }
    
    .meta-label {
        font-size: 0.8rem;
        color: var(--text-color);
        opacity: 0.8;
    }
    
    .meta-value {
        font-size: 0.9rem;
        color: var(--text-color);
        font-weight: 500;
    }
    
    .quiz-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
        gap: 12px;
    }
    
    .challenge-completed {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #4CAF50;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .view-btn, .edit-btn {
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: none;
        letter-spacing: normal;
        transition: all 0.2s ease;
    }
    
    .view-btn {
        background-color: transparent;
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
    }
    
    .edit-btn {
        background-color: var(--primary-color);
        border: 1px solid var(--primary-color);
        color: white;
    }
    
    .view-btn:hover, .edit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .difficulty-filter {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 20px;
        border-radius: 20px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-color);
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .filter-btn:hover:not(.active) {
        border-color: var(--primary-color);
        background-color: rgba(var(--primary-color-rgb), 0.1);
    }
</style>
EOT;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <?php include_once 'common_styles.php'; ?>
    <?php echo $additionalStyles; ?>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="material-icons">code</i> Coding Challenges</h1>
            <p class="page-description">Test your Python skills with these challenges. Complete them to earn points and badges.</p>
        </div>
        <div class="challenges-container">
            
            <div class="difficulty-filter">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="Beginner">Beginner</button>
                <button class="filter-btn" data-filter="Intermediate">Intermediate</button>
                <button class="filter-btn" data-filter="Advanced">Advanced</button>
            </div>
            
            <div class="challenges-grid">
                <?php if (is_array($challenges) && !empty($challenges)): ?>
                <?php foreach ($challenges as $challenge): ?>
                    <div class="challenge-card" data-difficulty="<?php echo htmlspecialchars($challenge['difficulty_level']); ?>">
                        <div class="challenge-content">
                            <div class="challenge-info">
                                <div class="challenge-header">
                                    <div class="quiz-title-row">
                                        <h3 class="quiz-title"><?php echo htmlspecialchars($challenge['challenge_name']); ?></h3>
                                    </div>
                                    <div class="challenge-difficulty">
                                        <i class="material-icons" style="font-size: 16px;"><?php echo $challenge['difficulty_level'] === 'Beginner' ? 'brightness_low' : ($challenge['difficulty_level'] === 'Intermediate' ? 'brightness_medium' : 'brightness_high'); ?></i>
                                        <span>Difficulty: <?php echo ucfirst($challenge['difficulty_level']); ?></span>
                                    </div>
                                </div>
                                
                                <p class="challenge-description"><?php echo htmlspecialchars($challenge['description']); ?></p>
                                
                                <div class="quiz-meta">
                                    <div class="meta-item">
                                        <div class="meta-label">Points</div>
                                        <div class="meta-value"><?php echo htmlspecialchars($challenge['points']); ?></div>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <div class="meta-label">Time Limit</div>
                                        <div class="meta-value"><?php echo intval($challenge['time_limit'] ?? 30); ?> min</div>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <div class="meta-label">Status</div>
                                        <div class="meta-value"><?php echo $challenge['completed'] ? 'Completed' : 'Not Started'; ?></div>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <div class="meta-label">Attempts</div>
                                        <div class="meta-value"><?php echo isset($challenge['attempt_count']) ? intval($challenge['attempt_count']) : 0; ?></div>
                                    </div>
                                </div>
                                
                                <div class="quiz-actions">
                                    <?php if ($challenge['completed']): ?>
                                        <span class="challenge-completed">
                                            <i class="material-icons">check_circle</i>
                                            Completed
                                        </span>
                                    <?php else: ?>
                                        <a href="take_challenge.php?id=<?php echo $challenge['challenge_id']; ?>" class="btn edit-btn">Start Challenge</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="no-challenges">
                    <p>No challenges available at this time. Please check back later.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script>
        // Filter challenges by difficulty
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const challengeCards = document.querySelectorAll('.challenge-card');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    // Show/hide challenge cards based on filter
                    challengeCards.forEach(card => {
                        if (filter === 'all' || card.getAttribute('data-difficulty') === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
