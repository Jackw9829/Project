<?php
// Start session
session_start();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if ($isAjax) {
        // Return JSON error for AJAX requests
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    } else {
        // Redirect for normal requests
        header("Location: ../login.php");
    }
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Initialize message variables
$message = '';
$error = '';

// Check for success message in URL
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
}

// Function to get all quizzes
function getAllQuizzes() {
    try {
        $sql = "SELECT q.quiz_id, q.title, q.description, 
                   COUNT(qq.question_id) as question_count, q.time_limit, 
                   q.created_at, l.level_name
            FROM quizzes q
            LEFT JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id
            LEFT JOIN levels l ON q.level_id = l.level_id
            GROUP BY q.quiz_id
            ORDER BY q.created_at DESC";
        
        $result = executeSimpleQuery($sql);
        return $result ?? [];
    } catch (Exception $e) {
        // Log error but return empty array to prevent page breaking
        error_log("Error fetching quizzes: " . $e->getMessage());
        return [];
    }
}

// Function to get all levels
function getAllLevels() {
    try {
        $sql = "SELECT l.level_id, l.level_name, l.description, l.level_order, l.is_active,
                   COUNT(q.quiz_id) as quiz_count
            FROM levels l
            LEFT JOIN quizzes q ON l.level_id = q.level_id
            GROUP BY l.level_id
            ORDER BY l.level_order ASC";
        
        $result = executeSimpleQuery($sql);
        return $result ?? [];
    } catch (Exception $e) {
        // Log error but return empty array to prevent page breaking
        error_log("Error fetching levels: " . $e->getMessage());
        return [];
    }
}

// Function to get all challenges
function getAllChallenges() {
    try {
        $sql = "SELECT challenge_id, challenge_name, description, difficulty_level, points, time_limit, created_at
                FROM challenges
                ORDER BY created_at DESC";
        
        $result = executeSimpleQuery($sql);
        return $result ?? [];
    } catch (Exception $e) {
        // Log error but return empty array to prevent page breaking
        error_log("Error fetching challenges: " . $e->getMessage());
        return [];
    }
}

// Function to get a single level by ID
function getLevelById($levelId) {
    try {
        $sql = "SELECT * FROM levels WHERE level_id = ?";
        $result = executeQuery($sql, [$levelId]);
        return $result[0] ?? null;
    } catch (Exception $e) {
        error_log("Error fetching level: " . $e->getMessage());
        return null;
    }
}

// Function to get a single quiz by ID
function getQuizById($quizId) {
    try {
        $sql = "SELECT q.*, l.level_name FROM quizzes q 
                LEFT JOIN levels l ON q.level_id = l.level_id 
                WHERE q.quiz_id = ?";
        $result = executeQuery($sql, [$quizId]);
        return $result[0] ?? null;
    } catch (Exception $e) {
        error_log("Error fetching quiz: " . $e->getMessage());
        return null;
    }
}

// Function to get a single challenge by ID
function getChallengeById($challengeId) {
    try {
        $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
        $result = executeQuery($sql, [$challengeId]);
        return $result[0] ?? null;
    } catch (Exception $e) {
        error_log("Error fetching challenge: " . $e->getMessage());
        return null;
    }
}

// Function to reset AUTO_INCREMENT value for a table
function resetAutoIncrement($tableName) {
    try {
        // Get the next available ID
        $sql = "SELECT MAX(CASE 
                   WHEN INSTR(COLUMN_NAME, '_id') > 0 THEN 
                     CONCAT('SELECT COALESCE(MAX(', COLUMN_NAME, '), 0) + 1 FROM ', TABLE_NAME) 
                   END) as reset_query
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'codaquest' 
                AND TABLE_NAME = ?
                AND COLUMN_KEY = 'PRI'";
        
        $result = executeQuery($sql, [$tableName]);
        
        if (!empty($result) && isset($result[0]['reset_query']) && !empty($result[0]['reset_query'])) {
            $resetQuery = $result[0]['reset_query'];
            $nextId = executeSimpleQuery($resetQuery);
            
            if (is_array($nextId) && !empty($nextId)) {
                $nextIdValue = reset($nextId[0]); // Get first value of first row
                
                // Reset the AUTO_INCREMENT value
                $alterSql = "ALTER TABLE $tableName AUTO_INCREMENT = $nextIdValue";
                executeSimpleQuery($alterSql);
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error resetting AUTO_INCREMENT for $tableName: " . $e->getMessage());
        return false;
    }
}

// Handle AJAX requests for view/edit functionality
if ($isAjax && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    
    // View item details
    if ($action === 'view') {
        $type = $_GET['type'] ?? '';
        $id = intval($_GET['id'] ?? 0);
        
        if ($id > 0) {
            $data = null;
            switch ($type) {
                case 'level':
                    $data = getLevelById($id);
                    break;
                case 'quiz':
                    $data = getQuizById($id);
                    break;
                case 'challenge':
                    $data = getChallengeById($id);
                    break;
            }
            
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        exit();
    }
    
    // Update item
    if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $type = $_POST['type'] ?? '';
        $id = intval($_POST['id'] ?? 0);
        
        if ($id > 0) {
            $success = false;
            $message = '';
            
            try {
                switch ($type) {
                    case 'level':
                        $levelName = trim($_POST['level_name'] ?? '');
                        $description = trim($_POST['description'] ?? '');
                        $levelOrder = intval($_POST['level_order'] ?? 0);
                        $isActive = isset($_POST['is_active']) ? 1 : 0;
                        
                        if (empty($levelName)) {
                            throw new Exception('Level name is required');
                        }
                        
                        $sql = "UPDATE levels SET level_name = ?, description = ?, level_order = ?, is_active = ? WHERE level_id = ?";
                        executeQuery($sql, [$levelName, $description, $levelOrder, $isActive, $id]);
                        $success = true;
                        $message = 'Level updated successfully';
                        break;
                        
                    case 'quiz':
                        $title = trim($_POST['title'] ?? '');
                        $description = trim($_POST['description'] ?? '');
                        $levelId = intval($_POST['level_id'] ?? 0);
                        $timeLimit = intval($_POST['time_limit'] ?? 0);
                        
                        if (empty($title)) {
                            throw new Exception('Quiz title is required');
                        }
                        
                        $sql = "UPDATE quizzes SET title = ?, description = ?, level_id = ?, time_limit = ? WHERE quiz_id = ?";
                        executeQuery($sql, [$title, $description, $levelId, $timeLimit, $id]);
                        $success = true;
                        $message = 'Quiz updated successfully';
                        break;
                        
                    case 'challenge':
                        $name = trim($_POST['challenge_name'] ?? '');
                        $description = trim($_POST['description'] ?? '');
                        $difficulty = trim($_POST['difficulty_level'] ?? '');
                        $points = intval($_POST['points'] ?? 0);
                        $timeLimit = intval($_POST['time_limit'] ?? 30);
                        
                        if (empty($name)) {
                            throw new Exception('Challenge name is required');
                        }
                        
                        $sql = "UPDATE challenges SET challenge_name = ?, description = ?, difficulty_level = ?, points = ?, time_limit = ? WHERE challenge_id = ?";
                        executeQuery($sql, [$name, $description, $difficulty, $points, $timeLimit, $id]);
                        $success = true;
                        $message = 'Challenge updated successfully';
                        break;
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
            }
            
            echo json_encode(['success' => $success, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        }
        exit();
    }
}

// Handle quiz deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_id'])) {
    $quizId = intval($_POST['quiz_id']);
    
    try {
        // First delete associated questions
        $deleteQuestionsSql = "DELETE FROM quiz_questions WHERE quiz_id = ?";
        executeQuery($deleteQuestionsSql, [$quizId]);
        
        // Delete attempts
        $deleteAttemptsSql = "DELETE FROM quiz_attempts WHERE quiz_id = ?";
        executeQuery($deleteAttemptsSql, [$quizId]);
        
        // Finally delete the quiz
        $deleteQuizSql = "DELETE FROM quizzes WHERE quiz_id = ?";
        $deleteResult = executeQuery($deleteQuizSql, [$quizId]);
        
        // Reset AUTO_INCREMENT values
        resetAutoIncrement('quiz_questions');
        resetAutoIncrement('quizzes');
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Quiz deleted and IDs reset successfully']);
            exit();
        } else {
            header("Location: managequiz.php");
            exit();
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting quiz: ' . $e->getMessage()]);
            exit();
        } else {
            // Set error message and redirect
            $_SESSION['error_message'] = 'Error deleting quiz: ' . $e->getMessage();
            header("Location: managequiz.php");
            exit();
        }
    }
}

// Process level deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['level_id'])) {
    $levelId = intval($_POST['level_id']);
    
    try {
        // Delete associated quizzes first (cascade deletion will remove related quiz questions)
        $deleteQuizzesSql = "DELETE FROM quizzes WHERE level_id = ?";
        executeQuery($deleteQuizzesSql, [$levelId]);
        
        // Delete level
        $deleteSql = "DELETE FROM levels WHERE level_id = ?";
        $deleteResult = executeQuery($deleteSql, [$levelId]);
        
        // Reset AUTO_INCREMENT values
        resetAutoIncrement('quizzes');
        resetAutoIncrement('quiz_questions');
        resetAutoIncrement('levels');
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Level deleted and IDs reset successfully']);
            exit();
        } else {
            header("Location: managequiz.php");
            exit();
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting level: ' . $e->getMessage()]);
            exit();
        } else {
            $_SESSION['error_message'] = 'Error deleting level: ' . $e->getMessage();
            header("Location: managequiz.php");
            exit();
        }
    }
}

// Process challenge deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_id'])) {
    $challengeId = intval($_POST['challenge_id']);
    
    try {
        // Delete challenge questions first
        $deleteQuestionsSql = "DELETE FROM challenge_questions WHERE challenge_id = ?";
        executeQuery($deleteQuestionsSql, [$challengeId]);
        
        // Delete challenge attempts
        $deleteAttemptsSql = "DELETE FROM challenge_attempts WHERE challenge_id = ?";
        executeQuery($deleteAttemptsSql, [$challengeId]);
        
        // Delete challenge
        $deleteSql = "DELETE FROM challenges WHERE challenge_id = ?";
        $deleteResult = executeQuery($deleteSql, [$challengeId]);
        
        // Reset AUTO_INCREMENT values
        resetAutoIncrement('challenge_questions');
        resetAutoIncrement('challenge_attempts');
        resetAutoIncrement('challenges');
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Challenge deleted and IDs reset successfully']);
            exit();
        } else {
            header("Location: managequiz.php");
            exit();
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting challenge: ' . $e->getMessage()]);
            exit();
        } else {
            $_SESSION['error_message'] = 'Error deleting challenge: ' . $e->getMessage();
            header("Location: managequiz.php");
            exit();
        }
    }
}

// Get all quizzes
$quizzes = getAllQuizzes();

// Get all levels
$levels = getAllLevels();

// Get all challenges
$challenges = getAllChallenges();

// Ensure $quizzes is always an array
if (!is_array($quizzes)) {
    $quizzes = [];
}

// Ensure $levels is always an array
if (!is_array($levels)) {
    $levels = [];
}

// Ensure $challenges is always an array
if (!is_array($challenges)) {
    $challenges = [];
}

// Set page title
$pageTitle = "Content Management - CodaQuest Admin";

// --- LEVEL & QUIZ NAVIGATION LOGIC ---
if (basename($_SERVER['PHP_SELF']) === 'managequiz.php') {
    // Remove automatic redirect to add_level.php when no levels exist
    // Keep the empty management page visible to admins
    
    // Old redirection logic removed
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to quiz management */
        .section-spacer {
            height: 40px;
            width: 100%;
            margin: 0;
            padding: 0;
            clear: both;
        }
        
        /* Improved table design */
        .quiz-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border: 2px solid var(--border-color);
            font-size: 12px;
        }
        
        .quiz-table th,
        .quiz-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.2);
            color: var(--text-color);
        }
        
        .quiz-table th {
            background-color: rgba(var(--primary-color-rgb), 0.2);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        .quiz-table tr:last-child td {
            border-bottom: none;
        }
        
        .quiz-table tr:hover {
            background-color: rgba(var(--primary-color-rgb), 0.05);
        }
        
        /* Badge styling */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Define theme-adaptive colors */
        :root {
            --easy-color: #4CAF50;
            --medium-color: #FFC107;
            --hard-color: #f44336;
            --active-color: #4CAF50;
            --inactive-color: #9E9E9E;
            
            /* Empty state and modal colors */
            --empty-title-color: #ffffff;
            --empty-desc-color: #cccccc;
            --modal-text-color: #ffffff;
        }
        
        [data-theme="light"] {
            --easy-color: #2e7d32;
            --medium-color: #FF9800;
            --hard-color: #c62828;
            --active-color: #2e7d32;
            --inactive-color: #757575;
            
            /* Empty state and modal colors for light theme */
            --empty-title-color: #333333;
            --empty-desc-color: #666666;
            --modal-text-color: #333333;
        }
        
        .badge-easy {
            background-color: rgba(46, 125, 50, 0.2);
            color: var(--easy-color);
            border: 1px solid var(--easy-color);
        }
        
        .badge-medium {
            background-color: rgba(245, 124, 0, 0.2);
            color: var(--medium-color);
            border: 1px solid var(--medium-color);
        }
        
        .badge-hard {
            background-color: rgba(198, 40, 40, 0.2);
            color: var(--hard-color);
            border: 1px solid var(--hard-color);
        }
        
        .badge-active {
            background-color: rgba(46, 125, 50, 0.2);
            color: var(--active-color);
            border: 1px solid var(--active-color);
        }
        
        .badge-inactive {
            background-color: rgba(158, 158, 158, 0.2);
            color: var(--inactive-color);
            border: 1px solid var(--inactive-color);
        }
        
        /* Section headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .section-title {
            font-size: 16px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section-icon {
            margin-right: 10px;
            font-size: 20px;
        }
        
        /* Action buttons with improved styling */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: rgba(var(--card-bg), 0.8);
            border-radius: 6px;
            padding: 0;
        }
        
        .action-btn i.material-icons {
            font-size: 16px;
            line-height: 1;
            margin: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .view-btn {
            color: var(--easy-color);
            border-color: var(--easy-color);
        }
        
        .view-btn:hover {
            background-color: var(--easy-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .edit-btn {
            color: #2196F3;
            border-color: #2196F3;
        }
        
        .edit-btn:hover {
            background-color: #2196F3;
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .add-btn {
            color: var(--medium-color);
            border-color: var(--medium-color);
            text-decoration: none;
        }
        
        .add-btn:hover {
            background-color: var(--medium-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .delete-btn {
            color: var(--hard-color);
            border-color: var(--hard-color);
        }
        
        .delete-btn:hover {
            background-color: var(--hard-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        /* Improved search box */
        .search-box {
            display: flex;
            margin-bottom: 20px;
            max-width: 400px;
            position: relative;
        }
        
        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-right: none;
            border-radius: 6px 0 0 6px;
            font-family: inherit;
            background-color: var(--input-bg);
            color: var(--text-color);
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            background-color: var(--card-bg);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.3);
        }
        
        .search-btn {
            padding: 0 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background-color: var(--secondary-color);
        }
        
        /* Improved view-all button */
        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 12px;
            text-transform: uppercase;
            border: 2px solid var(--primary-color);
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
        }
        
        .view-all:hover {
            background-color: var(--primary-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
            margin-top: 20px;
        }
        
        .empty-state-icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .empty-state-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--empty-title-color) !important;
        }
        
        .empty-state-desc {
            font-size: 14px;
            color: var(--empty-desc-color) !important;
            margin-bottom: 25px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--primary-color);
        }
        
        .modal-content h2,
        .modal-content h3,
        .modal-content p {
            color: var(--modal-text-color) !important;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">quiz</i> Content Management</h1>
                <div class="user-info">
                    <!-- User avatar removed -->
                </div>
            </div>

            <div class="dashboard-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="recent-section">
                    <div class="section-header">
                        <h2 class="section-title"><i class="material-icons section-icon">assignment</i> All Levels</h2>
                        <a href="add_level.php" class="view-all">
                            <i class="material-icons">add</i> Create New Level
                        </a>
                    </div>

                    <div class="search-filters">
                        <form action="" method="GET" class="search-form">
                            <div class="search-box">
                                <input type="text" class="search-input" name="search" placeholder="Search levels...">
                                <button type="submit" class="search-btn">
                                    <i class="material-icons">search</i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (count($levels) > 0): ?>
                        <table class="quiz-table level-table">
                            <thead>
                                <tr>
                                    <th><i class="material-icons">layers</i> Level Name</th>
                                    <th><i class="material-icons">sort</i> Order</th>
                                    <th><i class="material-icons">toggle_on</i> Status</th>
                                    <th><i class="material-icons">quiz</i> Quizzes</th>
                                    <th><i class="material-icons">settings</i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($levels as $level): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($level['level_name']); ?></td>
                                        <td><?php echo $level['level_order']; ?></td>
                                        <td>
                                            <span class="badge <?php echo $level['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $level['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $level['quiz_count']; ?></td>
                                        <td class="actions">
    <div class="action-buttons">
        <button class="action-btn view-btn" onclick="viewItem('level', <?php echo $level['level_id']; ?>)" title="View Level Details">
            <i class="material-icons">visibility</i>
        </button>
        <button class="action-btn edit-btn" onclick="editItem('level', <?php echo $level['level_id']; ?>)" title="Edit Level">
            <i class="material-icons">edit</i>
        </button>
        <button class="action-btn delete-btn" onclick="confirmDelete('level', <?php echo $level['level_id']; ?>)" title="Delete Level">
            <i class="material-icons">delete</i>
        </button>
    </div>
</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons empty-state-icon">layers</i>
                            <h3 class="empty-state-title">No Levels Found</h3>
                            <p class="empty-state-desc">
                                <?php if (isset($e) && $e instanceof Exception): ?>
                                    There was an error loading levels. The levels table might not exist yet.
                                <?php else: ?>
                                    You haven't created any levels yet. Start by creating your first level!
                                <?php endif; ?>
                            </p>
                            <a href="add_level.php" class="btn btn-primary">
                                <i class="material-icons">add</i> Create New Level
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="section-spacer" style="height: 40px;"></div>
                
                <!-- Quizzes Section -->
                <div class="recent-section">
                    <div class="section-header">
                        <h2 class="section-title"><i class="material-icons section-icon">quiz</i> All Quizzes</h2>
                        <a href="add_quiz.php?type=quiz" class="view-all">
                            <i class="material-icons">add</i> Create New Quiz
                        </a>
                    </div>

                    <div class="search-filters">
                        <form action="" method="GET" class="search-form">
                            <div class="search-box">
                                <input type="text" class="search-input" name="quiz_search" placeholder="Search quizzes...">
                                <button type="submit" class="search-btn">
                                    <i class="material-icons">search</i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (count($quizzes) > 0): ?>
                        <table class="quiz-table">
                            <thead>
                                <tr>
                                    <th><i class="material-icons">title</i> Title</th>
                                    <th><i class="material-icons">school</i> Level</th>
                                    <th><i class="material-icons">question_answer</i> Questions</th>
                                    <th><i class="material-icons">timer</i> Time Limit</th>
                                    <th><i class="material-icons">settings</i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['level_name'] ?? 'No Level'); ?></td>
                                        <td><?php echo $quiz['question_count']; ?></td>
                                        <td><?php echo $quiz['time_limit']; ?> min</td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" onclick="viewItem('quiz', <?php echo $quiz['quiz_id']; ?>)" title="View Quiz Details">
                                                    <i class="material-icons">visibility</i>
                                                </button>
                                                <button class="action-btn edit-btn" onclick="editItem('quiz', <?php echo $quiz['quiz_id']; ?>)" title="Edit Quiz">
                                                    <i class="material-icons">edit</i>
                                                </button>
                                                <button class="action-btn delete-btn" onclick="confirmDelete('quiz', <?php echo $quiz['quiz_id']; ?>)" title="Delete Quiz">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons empty-state-icon">quiz</i>
                            <h3 class="empty-state-title">No Quizzes Found</h3>
                            <p class="empty-state-desc">
                                <?php if (isset($e) && $e instanceof Exception): ?>
                                    There was an error loading quizzes. The quizzes table might not exist yet.
                                <?php else: ?>
                                    You haven't created any quizzes yet. Start by creating your first quiz!
                                <?php endif; ?>
                            </p>
                            <a href="add_quiz.php?type=quiz" class="btn btn-primary">
                                <i class="material-icons">add</i> Create New Quiz
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="section-spacer" style="height: 40px;"></div>
                
                <!-- Challenges Section -->
                <div class="recent-section">
                    <div class="section-header">
                        <h2 class="section-title"><i class="material-icons section-icon">extension</i> All Challenges</h2>
                        <a href="add_challenge.php" class="view-all">
                            <i class="material-icons">add</i> Create New Challenge
                        </a>
                    </div>

                    <div class="search-filters">
                        <form action="" method="GET" class="search-form">
                            <div class="search-box">
                                <input type="text" class="search-input" name="challenge_search" placeholder="Search challenges...">
                                <button type="submit" class="search-btn">
                                    <i class="material-icons">search</i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (count($challenges) > 0): ?>
                        <table class="quiz-table challenge-table">
                            <thead>
                                <tr>
                                    <th><i class="material-icons">title</i> Name</th>
                                    <th><i class="material-icons">description</i> Description</th>
                                    <th><i class="material-icons">fitness_center</i> Difficulty</th>
                                    <th><i class="material-icons">stars</i> Points</th>
                                    <th><i class="material-icons">timer</i> Time Limit</th>
                                    <th><i class="material-icons">settings</i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($challenges as $challenge): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($challenge['challenge_name']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($challenge['description'], 0, 50)) . (strlen($challenge['description']) > 50 ? '...' : ''); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($challenge['difficulty_level']); ?>">
                                                <?php echo htmlspecialchars($challenge['difficulty_level']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $challenge['points']; ?></td>
                                        <td><?php echo isset($challenge['time_limit']) ? $challenge['time_limit'] : 30; ?> min</td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <button class="action-btn view-btn" onclick="viewItem('challenge', <?php echo $challenge['challenge_id']; ?>)" title="View Challenge Details">
                                                    <i class="material-icons">visibility</i>
                                                </button>
                                                <button class="action-btn edit-btn" onclick="editItem('challenge', <?php echo $challenge['challenge_id']; ?>)" title="Edit Challenge">
                                                    <i class="material-icons">edit</i>
                                                </button>
                                                <button class="action-btn delete-btn" onclick="confirmDelete('challenge', <?php echo $challenge['challenge_id']; ?>)" title="Delete Challenge">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons empty-state-icon">extension</i>
                            <h3 class="empty-state-title">No Challenges Found</h3>
                            <p class="empty-state-desc">
                                <?php if (isset($e) && $e instanceof Exception): ?>
                                    There was an error loading challenges. The challenges table might not exist yet.
                                <?php else: ?>
                                    You haven't created any challenges yet. Start by creating your first challenge!
                                <?php endif; ?>
                            </p>
                            <a href="add_challenge.php" class="btn btn-primary">
                                <i class="material-icons">add</i> Create New Challenge
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal for Quizzes/Levels -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete the level <span id="deleteQuizName"></span>?</p>
            <div class="modal-actions">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="quiz_id" id="deleteQuizId">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="delete_quiz" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal for Challenges -->
    <div id="deleteChallengeModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete the challenge <span id="deleteChallengeName"></span>?</p>
            <div class="modal-actions">
                <form id="deleteChallengeForm" method="POST">
                    <input type="hidden" name="challenge_id" id="deleteChallengeId">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="delete_challenge" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal for Levels -->
    <div id="deleteLevelModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete the level <span id="deleteLevelName"></span>?</p>
            <p class="warning">This action cannot be undone! All quizzes associated with this level will also be deleted.</p>
            <div class="modal-actions">
                <form id="deleteLevelForm" method="POST">
                    <input type="hidden" name="level_id" id="deleteLevelId">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="delete_level" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal for quizzes
        function confirmDelete(type, id) {
            // Based on the type, use the appropriate modal
            if (type === 'quiz') {
                document.getElementById('deleteQuizId').value = id;
                document.getElementById('deleteQuizName').textContent = "this quiz";
                document.getElementById('deleteModal').style.display = 'flex';
            } else if (type === 'level') {
                document.getElementById('deleteLevelId').value = id;
                document.getElementById('deleteLevelName').textContent = "this level";
                document.getElementById('deleteLevelModal').style.display = 'flex';
            } else if (type === 'challenge') {
                document.getElementById('deleteChallengeId').value = id;
                document.getElementById('deleteChallengeName').textContent = "this challenge";
                document.getElementById('deleteChallengeModal').style.display = 'flex';
            }
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('deleteChallengeModal').style.display = 'none';
            document.getElementById('deleteLevelModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = [
                document.getElementById('deleteModal'),
                document.getElementById('deleteChallengeModal'),
                document.getElementById('deleteLevelModal')
            ];
            
            modals.forEach(modal => {
                if (event.target == modal) {
                    closeModal();
                }
            });
        }
        
        // Handle form submissions via AJAX to prevent page reload
        document.addEventListener('DOMContentLoaded', function() {
            // Quiz delete form
            document.getElementById('deleteForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitDeleteForm(this, 'Quiz deleted successfully!');
            });
            
            // Level delete form
            document.getElementById('deleteLevelForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitDeleteForm(this, 'Level deleted successfully!');
            });
            
            // Challenge delete form
            document.getElementById('deleteChallengeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitDeleteForm(this, 'Challenge deleted successfully!');
            });
            
            function submitDeleteForm(form, successMessage) {
                const formData = new FormData(form);
                
                // Add X-Requested-With header to identify AJAX requests
                const xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            // Log the response for debugging
                            console.log('Response:', xhr.responseText);
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // Show success message
                                const alertDiv = document.createElement('div');
                                alertDiv.className = 'alert alert-success';
                                alertDiv.textContent = response.message || successMessage;
                                
                                const content = document.querySelector('.dashboard-content');
                                content.insertBefore(alertDiv, content.firstChild);
                                
                                // Remove the row from the table
                                const itemId = form.querySelector('input[type="hidden"]').value;
                                const itemType = form.querySelector('input[type="hidden"]').name;
                                
                                // Remove the deleted item's row
                                removeTableRow(itemType === 'quiz_id' ? 'quiz-table' : 
                                               itemType === 'challenge_id' ? 'challenge-table' : 
                                               'level-table', 
                                               itemId);
                                
                                // Close the modal
                                closeModal();
                                
                                // Auto-remove the alert after 3 seconds
                                setTimeout(function() {
                                    alertDiv.style.opacity = '0';
                                    setTimeout(function() {
                                        alertDiv.remove();
                                    }, 300);
                                }, 3000);
                            }
                        } catch (e) {
                            console.error('Error parsing JSON response:', e);
                            console.error('Raw response:', xhr.responseText);
                            
                            // Show error message to user
                            const alertDiv = document.createElement('div');
                            alertDiv.className = 'alert alert-danger';
                            alertDiv.textContent = 'Error processing request. Please try again.';
                            
                            const content = document.querySelector('.dashboard-content');
                            content.insertBefore(alertDiv, content.firstChild);
                            
                            // Close the modal
                            closeModal();
                        }
                    } else {
                        console.error('Request failed with status:', xhr.status);
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Network error occurred');
                };
                
                xhr.send(formData);
            }
            
            function removeTableRow(tableClass, itemId) {
                // For quiz table specifically, use a different selector since it doesn't have a specific class
                if (tableClass === 'quiz-table') {
                    const quizTables = document.querySelectorAll('.quiz-table');
                    if (quizTables.length > 1) {
                        // The second table is the quiz table (first is for levels)
                        const quizTable = quizTables[1];
                        const rows = quizTable.querySelectorAll('tbody tr');
                        
                        rows.forEach(row => {
                            const deleteBtn = row.querySelector('.delete-btn');
                            if (deleteBtn) {
                                const onclickAttr = deleteBtn.getAttribute('onclick');
                                if (onclickAttr && onclickAttr.includes(`'quiz', ${itemId}`)) {
                                    row.style.opacity = '0';
                                    setTimeout(function() {
                                        row.remove();
                                        
                                        // Check if table is now empty
                                        if (quizTable.querySelectorAll('tbody tr').length === 0) {
                                            // Replace with empty state div
                                            const tableParent = quizTable.parentNode;
                                            
                                            // Create empty state
                                            const emptyState = document.createElement('div');
                                            emptyState.className = 'empty-state';
                                            emptyState.innerHTML = `
                                                <i class="material-icons empty-state-icon">quiz</i>
                                                <h3 class="empty-state-title">No Quizzes Found</h3>
                                                <p class="empty-state-desc">
                                                    You haven't created any quizzes yet. Start by creating your first quiz!
                                                </p>
                                                <a href="add_quiz.php?type=quiz" class="btn btn-primary">
                                                    <i class="material-icons">add</i> Create New Quiz
                                                </a>
                                            `;
                                            
                                            // Replace table with empty state
                                            tableParent.replaceChild(emptyState, quizTable);
                                        }
                                    }, 300);
                                }
                            }
                        });
                    }
                } else {
                    // Handle other tables (levels, challenges)
                    const tables = document.getElementsByClassName(tableClass);
                    if (tables.length > 0) {
                        const table = tables[0];
                        const rows = table.querySelectorAll('tbody tr');
                        
                        rows.forEach(row => {
                            const deleteBtn = row.querySelector('.delete-btn');
                            if (deleteBtn) {
                                const onclickAttr = deleteBtn.getAttribute('onclick');
                                if (onclickAttr && onclickAttr.includes(itemId)) {
                                    row.style.opacity = '0';
                                    setTimeout(function() {
                                        row.remove();
                                        
                                        // Check if table is now empty
                                        if (table.querySelectorAll('tbody tr').length === 0) {
                                            // Create empty state based on table type
                                            let emptyStateHTML = '';
                                            if (tableClass === 'level-table') {
                                                emptyStateHTML = `
                                                    <i class="material-icons empty-state-icon">layers</i>
                                                    <h3 class="empty-state-title">No Levels Found</h3>
                                                    <p class="empty-state-desc">
                                                        You haven't created any levels yet. Start by creating your first level!
                                                    </p>
                                                    <a href="add_level.php" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Create New Level
                                                    </a>
                                                `;
                                            } else if (tableClass === 'challenge-table') {
                                                emptyStateHTML = `
                                                    <i class="material-icons empty-state-icon">extension</i>
                                                    <h3 class="empty-state-title">No Challenges Found</h3>
                                                    <p class="empty-state-desc">
                                                        You haven't created any challenges yet. Start by creating your first challenge!
                                                    </p>
                                                    <a href="add_challenge.php" class="btn btn-primary">
                                                        <i class="material-icons">add</i> Create New Challenge
                                                    </a>
                                                `;
                                            }
                                            
                                            if (emptyStateHTML) {
                                                const tableParent = table.parentNode;
                                                const emptyState = document.createElement('div');
                                                emptyState.className = 'empty-state';
                                                emptyState.innerHTML = emptyStateHTML;
                                                tableParent.replaceChild(emptyState, table);
                                            }
                                        }
                                    }, 300);
                                }
                            }
                        });
                    }
                }
            }
        });
    </script>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editModalTitle">Edit Item</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="editModalBody">
                <!-- Content will be dynamically loaded here -->
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="viewModalTitle">View Item</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be dynamically loaded here -->
            </div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Edit item function - redirect to edit_item.php
        function editItem(type, id) {
            window.location.href = `edit_item.php?type=${type}&id=${id}`;
        }
        
        // View item details - redirect to view_item.php
        function viewItem(type, id) {
            window.location.href = `view_item.php?type=${type}&id=${id}`;
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('deleteModal').style.display = 'none';
            document.getElementById('deleteChallengeModal').style.display = 'none';
            document.getElementById('deleteLevelModal').style.display = 'none';
        }
        
        // Close modal when clicking on X or outside the modal
        document.querySelectorAll('.modal .close').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        });
        // Also close when pressing Escape
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeModal();
        });
        
        // Add event listeners for Cancel and Delete buttons in all delete modals after DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            ['#deleteModal', '#deleteChallengeModal', '#deleteLevelModal'].forEach(function(modalId) {
                document.querySelectorAll(modalId + ' .btn.btn-secondary, ' + modalId + ' .btn.btn-danger').forEach(function(btn) {
                    btn.addEventListener('click', closeModal);
                });
            });
        });
        
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        });
    </script>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

