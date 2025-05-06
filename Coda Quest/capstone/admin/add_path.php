<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Process form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $pathName = trim($_POST['path_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $difficulty = $_POST['difficulty_level'] ?? '';
    $duration = (int)($_POST['estimated_duration'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($pathName)) {
        $error = "Path name is required";
    } elseif (empty($description)) {
        $error = "Description is required";
    } elseif (empty($difficulty)) {
        $error = "Difficulty level is required";
    } elseif ($duration <= 0) {
        $error = "Estimated duration must be greater than 0";
    } else {
        // Get admin information
        $adminId = $_SESSION['admin_id'];
        $adminSql = "SELECT username FROM admins WHERE admin_id = ?";
        $adminResult = executeQuery($adminSql, [$adminId]);
        $adminName = $adminResult[0]['username'] ?? 'Admin';

        // Check if path name already exists
        $checkSql = "SELECT path_id FROM learning_paths WHERE path_name = ?";
        $checkResult = executeQuery($checkSql, [$pathName]);
        
        if ($checkResult && count($checkResult) > 0) {
            $error = "A learning path with this name already exists";
        } else {
            // Insert new path
            $insertSql = "INSERT INTO learning_paths (path_name, description, difficulty_level, estimated_duration, is_active, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $insertResult = executeQuery($insertSql, [$pathName, $description, $difficulty, $duration, $isActive]);
            
            if ($insertResult) {
                $message = "Learning path created successfully";
                // Clear form data after successful submission
                $pathName = $description = $difficulty = '';
                $duration = 0;
                $isActive = 1;
            } else {
                $error = "Error creating learning path";
            }
        }
    }
}

// Set default values
$pathName = $pathName ?? '';
$description = $description ?? '';
$difficulty = $difficulty ?? 'beginner';
$duration = $duration ?? 0;
$isActive = $isActive ?? 1;

// Set page title
$pageTitle = "Add Learning Path - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to add path page */
        .form-container {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--primary-color);
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check-label {
            cursor: pointer;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .required-field::after {
            content: "*";
            color: #e74c3c;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">route</i> Add Learning Path</h1>
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
                        <h2 class="section-title"><i class="material-icons section-icon">add_circle</i> Create New Learning Path</h2>
                        <a href="learning_paths.php" class="view-all">
                            <i class="material-icons">arrow_back</i> Back to Learning Paths
                        </a>
                    </div>

                    <div class="form-container">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="path_name" class="form-label required-field">Path Name</label>
                                <input type="text" id="path_name" name="path_name" class="form-control" value="<?php echo htmlspecialchars($pathName); ?>" required>
                                <div class="form-hint">Enter a unique and descriptive name for this learning path</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label required-field">Description</label>
                                <textarea id="description" name="description" class="form-control" required><?php echo htmlspecialchars($description); ?></textarea>
                                <div class="form-hint">Provide a detailed description of what students will learn in this path</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="difficulty_level" class="form-label required-field">Difficulty Level</label>
                                <select id="difficulty_level" name="difficulty_level" class="form-control" required>
                                    <option value="">Select Difficulty</option>
                                    <option value="beginner" <?php echo $difficulty === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                                    <option value="intermediate" <?php echo $difficulty === 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                                    <option value="advanced" <?php echo $difficulty === 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                                </select>
                                <div class="form-hint">Choose the appropriate difficulty level for this learning path</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="estimated_duration" class="form-label required-field">Estimated Duration (hours)</label>
                                <input type="number" id="estimated_duration" name="estimated_duration" class="form-control" value="<?php echo $duration; ?>" min="1" required>
                                <div class="form-hint">Estimate how many hours it will take to complete this learning path</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" <?php echo $isActive ? 'checked' : ''; ?>>
                                    <label for="is_active" class="form-check-label">Make this learning path active and visible to users</label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="learning_paths.php" class="btn btn-secondary">
                                    <i class="material-icons">cancel</i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">save</i> Create Learning Path
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

