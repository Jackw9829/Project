<?php
/**
 * View Item Page
 * 
 * This file handles viewing quizzes and levels in detail.
 * Reused from take_quiz.php but adapted for admin viewing purposes.
 */

// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Get item type and ID from URL parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'quiz';
$item_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no ID provided, redirect to management page
if ($item_id === 0) {
    header("Location: managequiz.php");
    exit();
}

// Variables to store data
$item = null;
$questions = [];
$total_questions = 0;

// Get item details based on type
if ($type === 'quiz') {
    $sql = "SELECT q.*, l.level_name 
            FROM quizzes q
            LEFT JOIN levels l ON q.level_id = l.level_id
            WHERE q.quiz_id = ?";
    $item_result = executeQuery($sql, [$item_id]);
    
    if ($item_result && count($item_result) > 0) {
        $item = $item_result[0];
        
        // Get questions for this quiz
        $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_id ASC";
        $questions_result = executeQuery($sql, [$item_id]);
        
        if ($questions_result && count($questions_result) > 0) {
            $questions = $questions_result;
            $total_questions = count($questions);
        }
    } else {
        // Quiz not found
        header("Location: managequiz.php?error=" . urlencode("Quiz not found"));
        exit();
    }
} elseif ($type === 'level') {
    $sql = "SELECT l.*, COUNT(q.quiz_id) as quiz_count 
            FROM levels l
            LEFT JOIN quizzes q ON l.level_id = q.level_id
            WHERE l.level_id = ?
            GROUP BY l.level_id";
    $item_result = executeQuery($sql, [$item_id]);
    
    if ($item_result && count($item_result) > 0) {
        $item = $item_result[0];
        
        // Get quizzes for this level
        $sql = "SELECT q.*, COUNT(qq.question_id) as question_count 
                FROM quizzes q
                LEFT JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id
                WHERE q.level_id = ?
                GROUP BY q.quiz_id
                ORDER BY q.created_at ASC";
        $quizzes_result = executeQuery($sql, [$item_id]);
        
        if ($quizzes_result && count($quizzes_result) > 0) {
            $questions = $quizzes_result; // Reusing the questions variable to store quizzes
            $total_questions = count($questions);
        }
    } else {
        // Level not found
        header("Location: managequiz.php?error=" . urlencode("Level not found"));
        exit();
    }
} elseif ($type === 'challenge') {
    $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
    $item_result = executeQuery($sql, [$item_id]);
    
    if ($item_result && count($item_result) > 0) {
        $item = $item_result[0];
        
        // Get questions for this challenge
        $sql = "SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_order ASC";
        $questions_result = executeQuery($sql, [$item_id]);
        
        if ($questions_result && count($questions_result) > 0) {
            $questions = $questions_result;
            $total_questions = count($questions);
        }
    } else {
        // Challenge not found
        header("Location: managequiz.php?error=" . urlencode("Challenge not found"));
        exit();
    }
} elseif ($type === 'achievement') {
    $sql = "SELECT * FROM achievements WHERE achievement_id = ?";
    $item_result = executeQuery($sql, [$item_id]);
    
    if ($item_result && count($item_result) > 0) {
        $item = $item_result[0];
    } else {
        // Achievement not found
        header("Location: managequiz.php?error=" . urlencode("Achievement not found"));
        exit();
    }
} else {
    // Invalid type
    header("Location: managequiz.php?error=" . urlencode("Invalid item type"));
    exit();
}

// Page title
$pageTitle = "View " . ucfirst($type) . ": " . htmlspecialchars($item[$type === 'quiz' ? 'title' : ($type === 'level' ? 'level_name' : ($type === 'challenge' ? 'challenge_name' : 'title'))]);

// Additional styles for view page
$additionalStyles = '
<style>
    .item-container {
        max-width: 1200px;
        margin: 0 auto;
        background-color: var(--card-bg);
        border-radius: 8px;
        box-shadow: var(--card-shadow);
        border: 2px solid var(--primary-color);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .item-header {
        background-color: var(--primary-color);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .item-header h2 {
        margin: 0;
        font-size: 1.3rem;
        font-weight: 600;
        font-family: "Press Start 2P", monospace;
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.2);
    }
    
    .item-content {
        padding: 20px;
    }
    
    .item-info {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 15px;
    }
    
    .info-item {
        flex: 1;
        min-width: 180px;
        background-color: rgba(0, 0, 0, 0.2);
        padding: 12px;
        border-radius: 4px;
        border-left: 3px solid var(--primary-color);
    }
    
    .info-label {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 1rem;
        color: white;
        font-weight: 600;
    }
    
    .item-description {
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        background-color: rgba(0, 0, 0, 0.2);
        padding: 15px;
        border-radius: 4px;
    }
    
    .description-label {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .description-content {
        font-size: 1rem;
        color: white;
        line-height: 1.6;
    }
    
    .questions-container {
        margin-top: 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    @media (min-width: 768px) {
        .questions-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    .question-block {
        background-color: var(--bg-dark);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: var(--card-shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .question-block:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
    }
    
    .question-header {
        background-color: var(--accent-color);
        color: white;
        padding: 10px 15px;
        font-size: 0.9rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: "Press Start 2P", monospace;
        font-size: 0.8rem;
    }
    
    .question-content {
        padding: 15px;
    }
    
    .question-text {
        font-size: 1rem;
        color: white;
        margin-bottom: 15px;
        line-height: 1.5;
    }
    
    .options-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .option-item {
        display: flex;
        align-items: center;
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 4px;
        padding: 8px 12px;
        transition: background-color 0.2s ease;
    }
    
    .option-item:hover {
        background-color: rgba(0, 0, 0, 0.3);
    }
    
    .option-prefix {
        width: 24px;
        height: 24px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        margin-right: 12px;
        font-size: 0.8rem;
    }
    
    .option-text {
        color: white;
        font-size: 0.9rem;
    }
    
    .correct-option {
        background-color: rgba(46, 204, 113, 0.2);
        border-left: 3px solid #2ecc71;
    }
    
    .correct-option .option-prefix {
        background-color: #2ecc71;
    }
    
    .notes-container {
        margin-top: 20px;
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        padding: 15px;
        border-left: 3px solid var(--accent-color);
    }
    
    .notes-header {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .notes-content {
        font-size: 1rem;
        color: white;
        line-height: 1.6;
    }
    
    .notes-media {
        margin-top: 15px;
        max-width: 100%;
        border-radius: 8px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        border: 2px solid var(--border-color);
    }
    
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 30px;
    }
    
    .btn-back, .btn-edit {
        padding: 10px 18px;
        border-radius: 4px;
        font-size: 0.9rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
    }
    
    .btn-back {
        background-color: var(--accent-color);
        color: white;
    }
    
    .btn-edit {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-back:hover, .btn-edit:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }
    
    .badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    
    .badge-active {
        background-color: #2ecc71;
        color: white;
    }
    
    .badge-inactive {
        background-color: #e74c3c;
        color: white;
    }
    
    .badge-beginner {
        background-color: #2ecc71;
        color: white;
    }
    
    .badge-intermediate {
        background-color: #f39c12;
        color: white;
    }
    
    .badge-advanced {
        background-color: #e74c3c;
        color: white;
    }
    
    .badge-completion, .badge-progress, .badge-skill, .badge-special {
        background-color: var(--primary-color);
        color: white;
    }
    
    .section-title {
        color: var(--primary-color);
        font-size: 1.2rem;
        margin-bottom: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        text-shadow: 2px 2px 0px rgba(0, 0, 0, 0.1);
        padding-bottom: 8px;
        border-bottom: 2px solid var(--primary-color);
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        border: 2px dashed var(--border-color);
    }
    
    .empty-state-title {
        color: var(--primary-color);
        font-size: 1.2rem;
        margin-bottom: 10px;
    }
    
    .empty-state-desc {
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 20px;
    }
</style>
';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <?php echo $additionalStyles; ?>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title">
                    <i class="material-icons"><?php echo $type === 'quiz' ? 'quiz' : ($type === 'level' ? 'school' : ($type === 'challenge' ? 'fitness_center' : 'emoji_events')); ?></i>
                    View <?php echo ucfirst($type); ?>
                </h1>
                <div class="user-info">
                </div>
            </div>
            
            <div class="dashboard-content">
                <div class="item-container">
                    <div class="item-header">
                        <h2><?php echo htmlspecialchars($item[$type === 'quiz' ? 'title' : ($type === 'level' ? 'level_name' : ($type === 'challenge' ? 'challenge_name' : 'title'))]); ?></h2>
                    </div>
                    
                    <div class="item-content">
                        <?php if ($type === 'quiz'): ?>
                            <div class="item-info">
                                <div class="info-item">
                                    <div class="info-label">Level</div>
                                    <div class="info-value"><?php echo htmlspecialchars($item['level_name'] ?? 'No Level'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time Limit</div>
                                    <div class="info-value"><?php echo $item['time_limit']; ?> minutes</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Questions</div>
                                    <div class="info-value"><?php echo $total_questions; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($item['description'])): ?>
                                <div class="item-description">
                                    <div class="description-label">Description</div>
                                    <div class="description-content"><?php echo nl2br(htmlspecialchars(strip_tags($item['description']))); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($total_questions > 0): ?>
                                <h3 class="section-title"><i class="material-icons">quiz</i> Questions</h3>
                                <div class="questions-container">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="question-block">
                                            <div class="question-header">
                                                <i class="material-icons">help</i> Question <?php echo $index + 1; ?>
                                            </div>
                                            <div class="question-content">
                                                <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                                
                                                <div class="options-container">
                                                    <div class="option-item <?php echo $question['correct_answer'] === 'a' ? 'correct-option' : ''; ?>">
                                                        <div class="option-prefix">A</div>
                                                        <div class="option-text"><?php echo htmlspecialchars($question['option_a']); ?></div>
                                                    </div>
                                                    
                                                    <div class="option-item <?php echo $question['correct_answer'] === 'b' ? 'correct-option' : ''; ?>">
                                                        <div class="option-prefix">B</div>
                                                        <div class="option-text"><?php echo htmlspecialchars($question['option_b']); ?></div>
                                                    </div>
                                                    
                                                    <?php if (!empty($question['option_c'])): ?>
                                                        <div class="option-item <?php echo $question['correct_answer'] === 'c' ? 'correct-option' : ''; ?>">
                                                            <div class="option-prefix">C</div>
                                                            <div class="option-text"><?php echo htmlspecialchars($question['option_c']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($question['option_d'])): ?>
                                                        <div class="option-item <?php echo $question['correct_answer'] === 'd' ? 'correct-option' : ''; ?>">
                                                            <div class="option-prefix">D</div>
                                                            <div class="option-text"><?php echo htmlspecialchars($question['option_d']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3 class="empty-state-title">No Questions Found</h3>
                                    <p class="empty-state-desc">This quiz doesn't have any questions yet.</p>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($type === 'level'): ?>
                            <div class="item-info">
                                <div class="info-item">
                                    <div class="info-label">Level Order</div>
                                    <div class="info-value"><?php echo $item['level_order']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="badge <?php echo $item['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Quizzes</div>
                                    <div class="info-value"><?php echo $item['quiz_count']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($item['description'])): ?>
                                <div class="item-description">
                                    <div class="description-label">Description</div>
                                    <div class="description-content"><?php echo nl2br(htmlspecialchars(strip_tags($item['description']))); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['notes'])): ?>
                                <div class="notes-container">
                                    <div class="notes-header">Level Notes</div>
                                    <div class="notes-content"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></div>
                                    
                                    <?php if (!empty($item['notes_media'])): ?>
                                        <?php 
                                        $extension = pathinfo($item['notes_media'], PATHINFO_EXTENSION);
                                        $isVideo = in_array(strtolower($extension), ['mp4', 'webm', 'ogg']);
                                        ?>
                                        
                                        <?php if ($isVideo): ?>
                                            <video class="notes-media" controls>
                                                <source src="../<?php echo $item['notes_media']; ?>" type="video/<?php echo $extension; ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        <?php else: ?>
                                            <img class="notes-media" src="../<?php echo $item['notes_media']; ?>" alt="Level Notes Media">
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            

                        <?php elseif ($type === 'challenge'): ?>
                            <div class="item-info">
                                <div class="info-item">
                                    <div class="info-label">Difficulty</div>
                                    <div class="info-value">
                                        <span class="badge badge-<?php echo strtolower($item['difficulty_level']); ?>">
                                            <?php echo htmlspecialchars($item['difficulty_level']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Points</div>
                                    <div class="info-value"><?php echo $item['points']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time Limit</div>
                                    <div class="info-value"><?php echo intval($item['time_limit'] ?? 30); ?> minutes</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($item['description'])): ?>
                                <div class="item-description">
                                    <div class="description-label">Description</div>
                                    <div class="description-content"><?php echo nl2br(htmlspecialchars(strip_tags($item['description']))); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($total_questions > 0): ?>
                                <h3 class="section-title"><i class="material-icons">code</i> Challenge Questions</h3>
                                <div class="questions-container">
                                    <?php foreach ($questions as $index => $question): ?>
                                        <div class="question-block">
                                            <div class="question-header">
                                                <i class="material-icons">help</i> Question <?php echo $index + 1; ?>
                                            </div>
                                            <div class="question-content">
                                                <?php if (!empty($question['question_text'])): ?>
                                                    <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                                                <?php endif; ?>
                                                <div class="options-container">
                                                    <div class="option-item <?php echo $question['correct_answer'] === 'a' ? 'correct-option' : ''; ?>">
                                                        <div class="option-prefix">A</div>
                                                        <div class="option-text"><?php echo htmlspecialchars($question['option_a']); ?></div>
                                                    </div>
                                                    <div class="option-item <?php echo $question['correct_answer'] === 'b' ? 'correct-option' : ''; ?>">
                                                        <div class="option-prefix">B</div>
                                                        <div class="option-text"><?php echo htmlspecialchars($question['option_b']); ?></div>
                                                    </div>
                                                    <?php if (!empty($question['option_c'])): ?>
                                                        <div class="option-item <?php echo $question['correct_answer'] === 'c' ? 'correct-option' : ''; ?>">
                                                            <div class="option-prefix">C</div>
                                                            <div class="option-text"><?php echo htmlspecialchars($question['option_c']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($question['option_d'])): ?>
                                                        <div class="option-item <?php echo $question['correct_answer'] === 'd' ? 'correct-option' : ''; ?>">
                                                            <div class="option-prefix">D</div>
                                                            <div class="option-text"><?php echo htmlspecialchars($question['option_d']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3 class="empty-state-title">No Questions Found</h3>
                                    <p class="empty-state-desc">This challenge doesn't have any questions yet.</p>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($type === 'achievement'): ?>
                            <div class="item-info">
                                <div class="info-item">
                                    <div class="info-label">Type</div>
                                    <div class="info-value">
                                        <span class="badge badge-<?php echo $item['achievement_type']; ?>">
                                            <?php echo ucfirst($item['achievement_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Points</div>
                                    <div class="info-value"><?php echo $item['points']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Requirement Value</div>
                                    <div class="info-value"><?php echo $item['requirement_value']; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="badge <?php echo $item['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($item['description'])): ?>
                                <div class="item-description">
                                    <div class="description-label">Description</div>
                                    <div class="description-content"><?php echo nl2br(htmlspecialchars(strip_tags($item['description']))); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['icon'])): ?>
                                <div class="notes-container">
                                    <div class="notes-header">Achievement Icon</div>
                                    <img class="notes-media" src="../uploads/achievements/<?php echo $item['icon']; ?>" alt="Achievement Icon">
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="action-buttons">
                            <a href="managequiz.php" class="btn-back">
                                <i class="material-icons">arrow_back</i> Back to Management
                            </a>
                            <a href="edit_item.php?type=<?php echo $type; ?>&id=<?php echo $item_id; ?>" class="btn-edit">
                                <i class="material-icons">edit</i> Edit <?php echo ucfirst($type); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

