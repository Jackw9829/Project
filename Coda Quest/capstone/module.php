<?php
// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if module_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$moduleId = $_GET['id'];
$userId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
$isLoggedIn = isset($_SESSION['student_id']);

// Get module details
function getModuleDetails($moduleId) {
    $sql = "SELECT m.module_id, m.module_name, m.description, m.module_order, 
            m.estimated_duration, p.path_id, p.path_name
            FROM learning_modules m
            JOIN learning_paths p ON m.path_id = p.path_id
            WHERE m.module_id = ? AND m.is_active = TRUE";
    
    $result = executeQuery($sql, [$moduleId]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

// Get module content
function getModuleContent($moduleId) {
    $sql = "SELECT content_id, content_title, content_type, content_body, 
            content_order, estimated_duration, points_reward, difficulty_level
            FROM learning_content
            WHERE module_id = ? AND is_active = TRUE
            ORDER BY content_order ASC";
    
    $result = executeQuery($sql, [$moduleId]);
    $content = [];
    
    if ($result && count($result) > 0) {
        $content = $result;
    }
    
    return $content;
}

// Get user content progress
function getUserContentProgress($userId, $contentIds) {
    if (!$userId || empty($contentIds)) return [];
    
    $placeholders = implode(',', array_fill(0, count($contentIds), '?'));
    $params = array_merge([$userId], $contentIds);
    
    $sql = "SELECT item_id as content_id, is_completed, last_accessed, score
            FROM user_progress
            WHERE student_id = ? AND item_id IN ($placeholders) AND item_type = 'content'"; 
    
    $result = executeQuery($sql, $params);
    $progress = [];
    
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $progress[$row['content_id']] = $row;
        }
    }
    
    return $progress;
}

// Check if user has access to this module
function checkModuleAccess($moduleId, $userId) {
    if (!$userId) return false;
    
    // Get path ID for this module
    $sql = "SELECT path_id FROM learning_modules WHERE module_id = ?";
    $result = executeQuery($sql, [$moduleId]);
    
    if (!$result || count($result) === 0) return false;
    
    $pathId = $result[0]['path_id'];
    
    // Check if user is enrolled in this path
    $sql = "SELECT student_id FROM user_progress WHERE student_id = ? AND item_id = ? AND item_type = 'path'";
    $result = executeQuery($sql, [$userId, $pathId]);
    
    return ($result && count($result) > 0);
}

// Get module details
$module = getModuleDetails($moduleId);

// Redirect if module not found
if (!$module) {
    header("Location: dashboard.php");
    exit();
}

// Check access
$hasAccess = $isLoggedIn && checkModuleAccess($moduleId, $userId);

// Get content if user has access
$moduleContent = [];
$contentProgress = [];

if ($hasAccess) {
    $moduleContent = getModuleContent($moduleId);
    
    if ($moduleContent) {
        $contentIds = array_column($moduleContent, 'content_id');
        $contentProgress = getUserContentProgress($userId, $contentIds);
    }
}

// Set page title
$pageTitle = $module['module_name'] . " - CodaQuest";
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
    <link rel="stylesheet" href="common.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Tiny5", sans-serif;
            font-weight: 400;
            font-style: normal;
        }

        .module-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }

        .sidebar {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        .sidebar-header {
            margin-bottom: 20px;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .sidebar-subtitle {
            font-size: 14px;
            color: #777;
        }

        .content-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .content-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .content-item:hover {
            background-color: #f5f5f5;
        }

        .content-item.active {
            background-color: var(--primary-color);
            color: var(--text-color);
        }

        .content-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #555;
        }

        .content-item.active .content-icon {
            background-color: white;
        }

        .content-item.completed .content-icon {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .content-details {
            flex: 1;
        }

        .content-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .content-meta {
            font-size: 12px;
            color: #777;
        }

        .content-item.active .content-meta {
            color: rgba(0, 0, 0, 0.7);
        }

        .module-content {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .module-header {
            margin-bottom: 30px;
        }

        .module-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .module-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .module-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #777;
        }

        .module-meta-item i {
            color: var(--primary-color);
        }

        .module-description {
            line-height: 1.6;
            color: #555;
        }

        .content-container {
            margin-top: 30px;
        }

        .content-header {
            margin-bottom: 20px;
        }

        .content-header-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .content-header-meta {
            display: flex;
            gap: 15px;
            color: #777;
            font-size: 14px;
        }

        .content-body {
            line-height: 1.8;
        }

        .content-body p {
            margin-bottom: 20px;
        }

        .content-body h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 30px 0 15px;
            color: var(--text-color);
        }

        .content-body h4 {
            font-size: 18px;
            font-weight: 700;
            margin: 25px 0 15px;
            color: var(--text-color);
        }

        .content-body ul, .content-body ol {
            margin-bottom: 20px;
            padding-left: 25px;
        }

        .content-body li {
            margin-bottom: 10px;
        }

        .content-body code {
            background-color: #f5f5f5;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }

        .code-block {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .code-block pre {
            font-family: monospace;
            line-height: 1.5;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .nav-button {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            background-color: #f5f5f5;
            color: var(--text-color);
        }

        .nav-button:hover {
            background-color: #e9e9e9;
            transform: translateY(-2px);
        }

        .nav-button.primary {
            background-color: var(--primary-color);
            color: var(--text-color);
        }

        .nav-button.primary:hover {
            background-color: var(--secondary-color);
        }

        .access-message {
            text-align: center;
            padding: 50px 20px;
        }

        .access-message h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-color);
        }

        .access-message p {
            margin-bottom: 25px;
            color: #555;
        }

        .access-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .module-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="module-container">
        <?php if ($hasAccess): ?>
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Module Content</h2>
                    <p class="sidebar-subtitle"><?php echo $module['module_name']; ?></p>
                </div>
                
                <div class="content-list">
                    <?php foreach ($moduleContent as $index => $content): ?>
                        <?php
                        $contentId = $content['content_id'];
                        $isCompleted = isset($contentProgress[$contentId]) && $contentProgress[$contentId]['is_completed'];
                        $isActive = $index === 0; // Set first item as active by default
                        
                        $iconMap = [
                            'text' => 'article',
                            'video' => 'play_circle',
                            'interactive' => 'touch_app',
                            'quiz' => 'quiz',
                            'coding_exercise' => 'code'
                        ];
                        
                        $icon = $iconMap[$content['content_type']] ?? 'article';
                        ?>
                        <div class="content-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCompleted ? 'completed' : ''; ?>" data-content-id="<?php echo $contentId; ?>">
                            <div class="content-icon">
                                <i class="material-icons"><?php echo $isCompleted ? 'check_circle' : $icon; ?></i>
                            </div>
                            <div class="content-details">
                                <div class="content-title"><?php echo $content['content_title']; ?></div>
                                <div class="content-meta">
                                    <?php echo ucfirst($content['content_type']); ?> • <?php echo $content['estimated_duration']; ?> min
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="module-content">
                <div class="module-header">
                    <h1 class="module-title"><?php echo $module['module_name']; ?></h1>
                    <div class="module-meta">
                        <div class="module-meta-item">
                            <i class="material-icons">schedule</i>
                            <span>Duration: <?php echo $module['estimated_duration']; ?> min</span>
                        </div>
                        <div class="module-meta-item">
                            <i class="material-icons">view_list</i>
                            <span>Content Items: <?php echo count($moduleContent); ?></span>
                        </div>
                        <div class="module-meta-item">
                            <i class="material-icons">school</i>
                            <span>Path: <?php echo $module['path_name']; ?></span>
                        </div>
                    </div>
                    <p class="module-description"><?php echo $module['description']; ?></p>
                </div>
                
                <?php if (count($moduleContent) > 0): ?>
                    <div class="content-container" id="content-display">
                        <?php
                        $firstContent = $moduleContent[0];
                        $contentType = $firstContent['content_type'];
                        $contentTitle = $firstContent['content_title'];
                        $contentBody = $firstContent['content_body'];
                        $contentDuration = $firstContent['estimated_duration'];
                        $contentDifficulty = $firstContent['difficulty_level'] ?? 'medium';
                        ?>
                        
                        <div class="content-header">
                            <h2 class="content-header-title"><?php echo $contentTitle; ?></h2>
                            <div class="content-header-meta">
                                <span><i class="material-icons">schedule</i> <?php echo $contentDuration; ?> min</span>
                                <span><i class="material-icons">signal_cellular_alt</i> <?php echo ucfirst($contentDifficulty); ?></span>
                                <span><i class="material-icons"><?php echo $iconMap[$contentType] ?? 'article'; ?></i> <?php echo ucfirst($contentType); ?></span>
                            </div>
                        </div>
                        
                        <div class="content-body">
                            <?php echo $contentBody; ?>
                        </div>
                        
                        <div class="navigation-buttons">
                            <button class="nav-button" disabled>
                                <i class="material-icons">arrow_back</i> Previous
                            </button>
                            
                            <button class="nav-button primary" id="mark-complete">
                                Mark as Complete <i class="material-icons">check</i>
                            </button>
                            
                            <button class="nav-button">
                                Next <i class="material-icons">arrow_forward</i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="access-message">
                        <h2>No content available</h2>
                        <p>This module doesn't have any content items yet. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="access-message" style="grid-column: 1 / -1;">
                <h2>Access Required</h2>
                <?php if ($isLoggedIn): ?>
                    <p>You need to enroll in the learning path to access this module.</p>
                    <div class="access-buttons">
                        <a href="learning_path.php?id=<?php echo $module['path_id']; ?>" class="nav-button primary">
                            Go to Learning Path
                        </a>
                    </div>
                <?php else: ?>
                    <p>Please log in to access this module content.</p>
                    <div class="access-buttons">
                        <a href="login.php?redirect=module.php?id=<?php echo $moduleId; ?>" class="nav-button primary">
                            Log In
                        </a>
                        <a href="signup.php" class="nav-button">
                            Sign Up
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Matrix Background Effect
        const canvas = document.getElementById('matrix-bg');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%";
        const matrixChars = matrix.split("");

        const fontSize = 14;
        const columns = canvas.width / fontSize;

        const drops = [];
        for (let i = 0; i < columns; i++) {
            drops[i] = 1;
        }

        function draw() {
            ctx.fillStyle = "rgba(255, 255, 255, 0.05)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = "#b2defd";
            ctx.font = fontSize + "px Tiny5";

            for (let i = 0; i < drops.length; i++) {
                const text = matrixChars[Math.floor(Math.random() * matrixChars.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }

                drops[i]++;
            }
        }

        setInterval(draw, 35);

        // Resize canvas on window resize
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        // Content navigation
        document.addEventListener('DOMContentLoaded', function() {
            const contentItems = document.querySelectorAll('.content-item');
            const contentDisplay = document.getElementById('content-display');
            
            contentItems.forEach(item => {
                item.addEventListener('click', function() {
                    // Remove active class from all items
                    contentItems.forEach(i => i.classList.remove('active'));
                    
                    // Add active class to clicked item
                    this.classList.add('active');
                    
                    // In a real implementation, this would load the content via AJAX
                    // For this demo, we'll just show a message
                    const contentId = this.getAttribute('data-content-id');
                    
                    // This would be replaced with actual content loading
                    console.log('Loading content ID: ' + contentId);
                });
            });
            
            // Mark as complete button
            const markCompleteBtn = document.getElementById('mark-complete');
            if (markCompleteBtn) {
                markCompleteBtn.addEventListener('click', function() {
                    const activeItem = document.querySelector('.content-item.active');
                    if (activeItem) {
                        activeItem.classList.add('completed');
                        this.textContent = 'Completed';
                        this.disabled = true;
                        
                        // In a real implementation, this would send an AJAX request to update progress
                        const contentId = activeItem.getAttribute('data-content-id');
                        console.log('Marking content ID ' + contentId + ' as complete');
                    }
                });
            }
        });
    </script>
</body>
</html>
