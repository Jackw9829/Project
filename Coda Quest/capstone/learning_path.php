<?php
// Start session
session_start();

// Include database connection
require_once 'config/db_connect.php';

// Check if path_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$pathId = $_GET['id'];
$userId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null);
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);

// Get learning path details
function getLearningPathDetails($pathId) {
    $sql = "SELECT path_id, path_name, description, difficulty_level, estimated_duration, 
            prerequisites, created_at, updated_at
            FROM learning_paths 
            WHERE path_id = ? AND is_active = TRUE";
    
    $result = executeQuery($sql, [$pathId]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

// Get modules for this learning path
function getPathModules($pathId) {
    $sql = "SELECT module_id, module_name, description, module_order, estimated_duration
            FROM learning_modules
            WHERE path_id = ? AND is_active = TRUE
            ORDER BY module_order ASC";
    
    $result = executeQuery($sql, [$pathId]);
    $modules = [];
    
    if ($result && count($result) > 0) {
        $modules = $result;
    }
    
    return $modules;
}

// Get user progress for this path
function getUserPathProgress($pathId, $userId) {
    if (!$userId) return null;
    
    $sql = "SELECT progress_percentage, completed_modules, total_modules, 
            last_activity_date, current_module_id
            FROM user_progress
            WHERE path_id = ? AND user_id = ?";
    
    $result = executeQuery($sql, [$pathId, $userId]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

// Get user module progress
function getUserModuleProgress($userId, $moduleIds) {
    if (!$userId || empty($moduleIds)) return [];
    
    $placeholders = implode(',', array_fill(0, count($moduleIds), '?'));
    $params = array_merge([$userId], $moduleIds);
    
    $sql = "SELECT module_id, progress_percentage, completed_content, total_content
            FROM user_module_progress
            WHERE user_id = ? AND module_id IN ($placeholders)";
    
    $result = executeQuery($sql, $params);
    $progress = [];
    
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $progress[$row['module_id']] = $row;
        }
    }
    
    return $progress;
}

// Enroll user in learning path
if (isset($_POST['enroll']) && $isLoggedIn) {
    $checkSql = "SELECT user_id FROM user_progress WHERE user_id = ? AND path_id = ?";
    $checkResult = executeQuery($checkSql, [$userId, $pathId]);
    
    if (!$checkResult || count($checkResult) === 0) {
        // Get total modules count
        $modulesSql = "SELECT COUNT(*) as total FROM learning_modules WHERE path_id = ? AND is_active = TRUE";
        $modulesResult = executeQuery($modulesSql, [$pathId]);
        $totalModules = $modulesResult[0]['total'] ?? 0;
        
        // Get first module ID
        $firstModuleSql = "SELECT module_id FROM learning_modules 
                          WHERE path_id = ? AND is_active = TRUE 
                          ORDER BY module_order ASC LIMIT 1";
        $firstModuleResult = executeQuery($firstModuleSql, [$pathId]);
        $firstModuleId = $firstModuleResult[0]['module_id'] ?? null;
        
        // Insert user progress
        $progressData = [
            'user_id' => $userId,
            'path_id' => $pathId,
            'progress_percentage' => 0,
            'completed_modules' => 0,
            'total_modules' => $totalModules,
            'current_module_id' => $firstModuleId,
            'last_activity_date' => date('Y-m-d H:i:s')
        ];
        
        insertData('user_progress', $progressData);
        
        // Log activity
        $activityData = [
            'user_id' => $userId,
            'activity_type' => 'enroll',
            'activity_details' => "Enrolled in learning path: " . $pathId,
            'related_id' => $pathId
        ];
        
        insertData('activity_log', $activityData);
        
        // Redirect to refresh page
        header("Location: learning_path.php?id=$pathId&enrolled=1");
        exit();
    }
}

// Get path details
$path = getLearningPathDetails($pathId);

// Redirect if path not found
if (!$path) {
    header("Location: dashboard.php");
    exit();
}

// Get modules
$modules = getPathModules($pathId);

// Get user progress
$userProgress = null;
$moduleProgress = [];

if ($isLoggedIn) {
    $userProgress = getUserPathProgress($pathId, $userId);
    
    if ($modules) {
        $moduleIds = array_column($modules, 'module_id');
        $moduleProgress = getUserModuleProgress($userId, $moduleIds);
    }
}

// Check if user is enrolled
$isEnrolled = $userProgress !== null;

// Set page title
$pageTitle = $path['path_name'] . " - CodaQuest";
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

        .path-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .path-header {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .path-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .path-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .path-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #777;
        }

        .path-meta-item i {
            color: var(--primary-color);
        }

        .path-description {
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .path-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background-color: #f5f5f5;
            color: var(--text-color);
        }

        .btn-secondary:hover {
            background-color: #e9e9e9;
            transform: translateY(-2px);
        }

        .btn-disabled {
            background-color: #e0e0e0;
            color: #9e9e9e;
            cursor: not-allowed;
        }

        .path-progress {
            margin-top: 25px;
        }

        .progress-bar-container {
            height: 10px;
            background-color: #eee;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 5px;
        }

        .progress-stats {
            display: flex;
            justify-content: space-between;
            color: #777;
            font-size: 14px;
        }

        .modules-container {
            margin-bottom: 40px;
        }

        .modules-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modules-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-color);
        }

        .modules-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .module-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 15px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .module-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .module-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }

        .module-meta {
            display: flex;
            gap: 15px;
            color: #777;
            font-size: 14px;
        }

        .module-description {
            color: #555;
            line-height: 1.6;
        }

        .module-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .module-progress {
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 70%;
        }

        .module-progress-bar {
            height: 6px;
            background-color: #eee;
            border-radius: 3px;
            overflow: hidden;
        }

        .module-progress-fill {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 3px;
        }

        .module-progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #777;
        }

        .module-status {
            font-size: 14px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .status-not-started {
            background-color: #f5f5f5;
            color: #757575;
        }

        .status-in-progress {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .module-order {
            position: absolute;
            top: 0;
            left: 0;
            width: 30px;
            height: 30px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border-bottom-right-radius: var(--border-radius);
        }

        .prerequisites {
            background-color: #fff8e1;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
        }

        .prerequisites-title {
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #f57c00;
        }

        .prerequisites-list {
            list-style-type: none;
        }

        .prerequisites-list li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .prerequisites-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #f57c00;
        }

        .notification {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .path-meta {
                flex-direction: column;
                gap: 10px;
            }

            .path-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="path-container">
        <?php if (isset($_GET['enrolled']) && $_GET['enrolled'] == 1): ?>
            <div class="notification">
                <i class="material-icons">check_circle</i>
                <span>You have successfully enrolled in this learning path!</span>
            </div>
        <?php endif; ?>

        <div class="path-header">
            <h1 class="path-title"><?php echo $path['path_name']; ?></h1>
            <div class="path-meta">
                <div class="path-meta-item">
                    <i class="material-icons">signal_cellular_alt</i>
                    <span>Difficulty: <?php echo ucfirst($path['difficulty_level']); ?></span>
                </div>
                <div class="path-meta-item">
                    <i class="material-icons">schedule</i>
                    <span>Duration: <?php echo $path['estimated_duration']; ?> hours</span>
                </div>
                <div class="path-meta-item">
                    <i class="material-icons">view_module</i>
                    <span>Modules: <?php echo count($modules); ?></span>
                </div>
                <div class="path-meta-item">
                    <i class="material-icons">update</i>
                    <span>Last Updated: <?php echo date('M d, Y', strtotime($path['updated_at'])); ?></span>
                </div>
            </div>

            <p class="path-description"><?php echo $path['description']; ?></p>

            <?php if ($isLoggedIn && $isEnrolled): ?>
                <div class="path-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?php echo $userProgress['progress_percentage']; ?>%"></div>
                    </div>
                    <div class="progress-stats">
                        <span>Progress: <?php echo $userProgress['progress_percentage']; ?>%</span>
                        <span>Completed: <?php echo $userProgress['completed_modules']; ?>/<?php echo $userProgress['total_modules']; ?> modules</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="path-actions">
                <?php if ($isLoggedIn): ?>
                    <?php if ($isEnrolled): ?>
                        <a href="module.php?id=<?php echo $userProgress['current_module_id']; ?>" class="btn btn-primary">
                            <i class="material-icons">play_arrow</i> Continue Learning
                        </a>
                    <?php else: ?>
                        <form method="POST" action="">
                            <button type="submit" name="enroll" class="btn btn-primary">
                                <i class="material-icons">school</i> Enroll Now
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="login.php?redirect=learning_path.php?id=<?php echo $pathId; ?>" class="btn btn-primary">
                        <i class="material-icons">login</i> Login to Enroll
                    </a>
                <?php endif; ?>
                
                <a href="#modules" class="btn btn-secondary">
                    <i class="material-icons">list</i> View Modules
                </a>
            </div>
        </div>

        <?php if (!empty($path['prerequisites'])): ?>
            <div class="prerequisites">
                <div class="prerequisites-title">
                    <i class="material-icons">info</i>
                    <span>Prerequisites</span>
                </div>
                <ul class="prerequisites-list">
                    <?php 
                    $prereqs = explode(',', $path['prerequisites']);
                    foreach ($prereqs as $prereq): 
                    ?>
                        <li><?php echo trim($prereq); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div id="modules" class="modules-container">
            <div class="modules-header">
                <h2 class="modules-title">Learning Modules</h2>
            </div>

            <div class="modules-list">
                <?php foreach ($modules as $index => $module): ?>
                    <?php
                    $moduleId = $module['module_id'];
                    $moduleStatus = 'not-started';
                    $progressPercentage = 0;
                    $completedContent = 0;
                    $totalContent = 0;
                    
                    if (isset($moduleProgress[$moduleId])) {
                        $progressPercentage = $moduleProgress[$moduleId]['progress_percentage'];
                        $completedContent = $moduleProgress[$moduleId]['completed_content'];
                        $totalContent = $moduleProgress[$moduleId]['total_content'];
                        
                        if ($progressPercentage == 100) {
                            $moduleStatus = 'completed';
                        } else if ($progressPercentage > 0) {
                            $moduleStatus = 'in-progress';
                        }
                    }
                    ?>
                    <div class="module-card">
                        <div class="module-order"><?php echo $index + 1; ?></div>
                        <div class="module-header">
                            <div>
                                <h3 class="module-title"><?php echo $module['module_name']; ?></h3>
                                <div class="module-meta">
                                    <span><i class="material-icons">schedule</i> <?php echo $module['estimated_duration']; ?> min</span>
                                </div>
                            </div>
                            <span class="module-status status-<?php echo $moduleStatus; ?>">
                                <?php 
                                switch ($moduleStatus) {
                                    case 'completed':
                                        echo 'Completed';
                                        break;
                                    case 'in-progress':
                                        echo 'In Progress';
                                        break;
                                    default:
                                        echo 'Not Started';
                                }
                                ?>
                            </span>
                        </div>
                        <p class="module-description"><?php echo $module['description']; ?></p>
                        <div class="module-footer">
                            <?php if ($isLoggedIn && $isEnrolled): ?>
                                <div class="module-progress">
                                    <div class="module-progress-bar">
                                        <div class="module-progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
                                    </div>
                                    <div class="module-progress-text">
                                        <span><?php echo $progressPercentage; ?>% complete</span>
                                        <?php if ($totalContent > 0): ?>
                                            <span><?php echo $completedContent; ?>/<?php echo $totalContent; ?> items</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="module.php?id=<?php echo $moduleId; ?>" class="btn btn-secondary">
                                    <?php echo $moduleStatus == 'completed' ? 'Review' : 'Start'; ?>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo $isLoggedIn ? '#' : 'login.php'; ?>" class="btn <?php echo $isLoggedIn ? 'btn-secondary' : 'btn-primary'; ?>">
                                    <?php echo $isLoggedIn ? 'Enroll to Access' : 'Login to Access'; ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
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
    </script>
</body>
</html>
