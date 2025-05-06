<?php
// Initialize session and check admin access
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=You must be an admin to access this page");
    exit();
}

// Include database connection and admin styles
require_once '../config/db_connect.php';
require_once 'admin_styles.php';

// Get item type and ID from URL
$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Validate parameters
if (empty($type) || $id <= 0) {
    header("Location: managequiz.php?error=Invalid parameters");
    exit();
}

// Initialize variables
$item = null;
$pageTitle = '';
$formAction = '';
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle different item types
        switch ($type) {
            case 'level':
                // Get form data
                $levelName = $_POST['level_name'] ?? '';
                $description = $_POST['description'] ?? '';
                $levelOrder = intval($_POST['level_order'] ?? 1);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $notes = $_POST['notes'] ?? '';
                $notesMediaPath = $item['notes_media'] ?? ''; // Keep existing media path by default
                
                // Handle new media upload if provided
                if (isset($_FILES['new_notes_media']) && $_FILES['new_notes_media']['error'] === UPLOAD_ERR_OK) {
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
                    $fileType = $_FILES['new_notes_media']['type'];
                    $fileSize = $_FILES['new_notes_media']['size'];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception('Invalid file type for notes media. Supported formats: JPG, PNG, GIF, WEBP, MP4, WEBM, OGG');
                    }
                    
                    if ($fileSize > 10 * 1024 * 1024) { // 10MB limit
                        throw new Exception('Notes media must be less than 10MB.');
                    }
                    
                    $ext = pathinfo($_FILES['new_notes_media']['name'], PATHINFO_EXTENSION);
                    $targetDir = '../uploads/quiz_notes_media/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0777, true);
                    }
                    
                    // Generate unique filename
                    $fileName = uniqid('notes_media_') . '.' . $ext;
                    $targetPath = $targetDir . $fileName;
                    
                    // Delete old file if it exists
                    if (!empty($notesMediaPath) && file_exists('../' . $notesMediaPath)) {
                        unlink('../' . $notesMediaPath);
                    }
                    
                    // Upload new file
                    if (!move_uploaded_file($_FILES['new_notes_media']['tmp_name'], $targetPath)) {
                        throw new Exception('Failed to upload notes media.');
                    }
                    
                    // Update path for database
                    $notesMediaPath = 'uploads/quiz_notes_media/' . $fileName;
                }
                
                // Validate input
                if (empty($levelName)) {
                    throw new Exception("Level name is required");
                }
                
                // Update level
                $sql = "UPDATE levels SET level_name = ?, description = ?, level_order = ?, is_active = ?, notes = ?, notes_media = ? WHERE level_id = ?";
                executeQuery($sql, [$levelName, $description, $levelOrder, $isActive, $notes, $notesMediaPath, $id]);
                $success = "Level updated successfully";
                break;
                
            case 'quiz':
                // Get form data
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                $levelId = intval($_POST['level_id'] ?? 0);
                $timeLimit = intval($_POST['time_limit'] ?? 30);
                
                // Validate input
                if (empty($title)) {
                    throw new Exception("Quiz title is required");
                }
                if ($levelId <= 0) {
                    throw new Exception("Please select a valid level");
                }
                
                // Update quiz
                $sql = "UPDATE quizzes SET title = ?, description = ?, level_id = ?, time_limit = ? WHERE quiz_id = ?";
                executeQuery($sql, [$title, $description, $levelId, $timeLimit, $id]);
                $success = "Quiz updated successfully";
                break;
                
            case 'challenge':
                // Get form data
                $challengeName = $_POST['challenge_name'] ?? '';
                $description = $_POST['description'] ?? '';
                $difficultyLevel = $_POST['difficulty_level'] ?? 'Beginner';
                $points = intval($_POST['points'] ?? 10);
                $timeLimit = intval($_POST['time_limit'] ?? 30);
                
                // Validate input
                if (empty($challengeName)) {
                    throw new Exception("Challenge name is required");
                }
                
                // Update challenge
                $sql = "UPDATE challenges SET challenge_name = ?, description = ?, difficulty_level = ?, points = ?, time_limit = ? WHERE challenge_id = ?";
                executeQuery($sql, [$challengeName, $description, $difficultyLevel, $points, $timeLimit, $id]);
                $success = "Challenge updated successfully";
                break;
                
            case 'achievement':
                // Get form data
                $title = $_POST['title'] ?? '';
                $description = $_POST['description'] ?? '';
                $achievementType = $_POST['achievement_type'] ?? 'completion';
                $points = intval($_POST['points'] ?? 10);
                $requirementValue = intval($_POST['requirement_value'] ?? 1);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                // Validate input
                if (empty($title)) {
                    throw new Exception("Achievement title is required");
                }
                
                // Update achievement
                $sql = "UPDATE achievements SET title = ?, description = ?, achievement_type = ?, points = ?, requirement_value = ?, is_active = ? WHERE achievement_id = ?";
                executeQuery($sql, [$title, $description, $achievementType, $points, $requirementValue, $isActive, $id]);
                $success = "Achievement updated successfully";
                break;
                
            default:
                throw new Exception("Invalid item type");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get item details
try {
    switch ($type) {
        case 'level':
            $pageTitle = 'Edit Level';
            $formAction = 'edit_item.php?type=level&id=' . $id;
            $sql = "SELECT * FROM levels WHERE level_id = ?";
            $result = executeQuery($sql, [$id]);
            if (!empty($result)) {
                $item = $result[0];
            } else {
                throw new Exception("Level not found");
            }
            break;
            
        case 'quiz':
            $pageTitle = 'Edit Quiz';
            $formAction = 'edit_item.php?type=quiz&id=' . $id;
            $sql = "SELECT q.*, l.level_name FROM quizzes q 
                    LEFT JOIN levels l ON q.level_id = l.level_id 
                    WHERE q.quiz_id = ?";
            $result = executeQuery($sql, [$id]);
            if (!empty($result)) {
                $item = $result[0];
                
                // Get quiz questions
                $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
                $questions = executeQuery($sql, [$id]);
                $item['questions'] = $questions;
            } else {
                throw new Exception("Quiz not found");
            }
            break;
            
        case 'challenge':
            $pageTitle = 'Edit Challenge';
            $formAction = 'edit_item.php?type=challenge&id=' . $id;
            $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
            $result = executeQuery($sql, [$id]);
            if (!empty($result)) {
                $item = $result[0];
                
                // Get challenge questions
                $sql = "SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_order ASC";
                $questions = executeQuery($sql, [$id]);
                $item['questions'] = $questions;
            } else {
                throw new Exception("Challenge not found");
            }
            break;
            
        case 'achievement':
            $pageTitle = 'Edit Achievement';
            $formAction = 'edit_item.php?type=achievement&id=' . $id;
            $sql = "SELECT * FROM achievements WHERE achievement_id = ?";
            $result = executeQuery($sql, [$id]);
            if (!empty($result)) {
                $item = $result[0];
            } else {
                throw new Exception("Achievement not found");
            }
            break;
            
        default:
            throw new Exception("Invalid item type");
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Get all levels for dropdown (if editing a quiz)
$levels = [];
if ($type === 'quiz') {
    $sql = "SELECT level_id, level_name FROM levels WHERE is_active = 1 ORDER BY level_order ASC";
    $levels = executeQuery($sql);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $currentTheme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CodaQuest Admin</title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for edit item page */
        .item-container {
            background-color: var(--card-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-size: 0.8rem;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            background-color: var(--input-bg);
            border: 4px solid var(--border-color);
            color: var(--text-color);
            font-size: 0.8rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 4px solid var(--border-color);
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        .alert-success {
            border-color: var(--success-color);
        }

        .alert-error {
            border-color: var(--error-color);
        }

        .question-container {
            border: 4px solid var(--border-color);
            padding: 15px;
            margin-bottom: 15px;
            background-color: var(--input-bg);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .remove-question {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 1.2rem;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1rem;
            margin: 20px 0;
            padding-bottom: 10px;
            border-bottom: 4px solid var(--border-color);
        }

        .form-note {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title">
                    <i class="material-icons"><?php echo $type === 'level' ? 'school' : ($type === 'quiz' ? 'quiz' : ($type === 'challenge' ? 'fitness_center' : 'emoji_events')); ?></i>
                    <?php echo $pageTitle; ?>
                </h1>
            </div>

            <div class="dashboard-content">
                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="material-icons">error</i> <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="material-icons">check_circle</i> <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if ($item): ?>
                <div class="item-container">
                    <form method="post" action="<?php echo $formAction; ?>" enctype="multipart/form-data">
                        <?php if ($type === 'level'): ?>
                        <!-- Level form fields -->
                        <h2 class="section-title"><i class="material-icons">school</i> Level Information</h2>
                        <div class="form-group">
                            <label for="level_name">Level Name</label>
                            <input type="text" id="level_name" name="level_name" value="<?php echo htmlspecialchars($item['level_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> This description will be shown to students on the level page.</p>
                        </div>

                        <div class="form-group">
                            <label for="level_order">Level Order</label>
                            <input type="number" id="level_order" name="level_order" value="<?php echo intval($item['level_order'] ?? 1); ?>" min="1" required>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> Determines the order in which levels are displayed (1 = first).</p>
                        </div>

                        <div class="form-group">
                            <label>Level Status</label>
                            <label class="checkbox-wrapper">
                                Make this level visible to students
                                <input type="checkbox" id="is_active" name="is_active" <?php echo (isset($item['is_active']) && $item['is_active'] == 1) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>

                        <h2 class="section-title"><i class="material-icons">notes</i> Level Notes</h2>
                        
                        <div class="form-group">
                            <label for="notes">Notes Content</label>
                            <textarea id="notes" name="notes" rows="6" class="form-control"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> Additional notes or instructions for this level.</p>
                        </div>

                        <div class="form-group">
                            <label for="notes_media">Current Notes Media</label>
                            <?php if (!empty($item['notes_media'])): ?>
                                <div class="current-media">
                                    <?php
                                    $mediaPath = '../' . $item['notes_media'];
                                    $mediaType = pathinfo($mediaPath, PATHINFO_EXTENSION);
                                    if (in_array(strtolower($mediaType), ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                        <img src="<?php echo $mediaPath; ?>" alt="Current notes media" style="max-width: 300px; margin: 10px 0;">
                                    <?php elseif (in_array(strtolower($mediaType), ['mp4', 'webm', 'ogg'])): ?>
                                        <video controls style="max-width: 300px; margin: 10px 0;">
                                            <source src="<?php echo $mediaPath; ?>" type="video/<?php echo $mediaType; ?>">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php endif; ?>
                                    <p class="form-note">Current file: <?php echo basename($item['notes_media']); ?></p>
                                </div>
                            <?php else: ?>
                                <p class="form-note">No media currently uploaded</p>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="new_notes_media">Update Notes Media (Image/Video)</label>
                            <input type="file" id="new_notes_media" name="new_notes_media" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg">
                            <p class="form-note">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                                Supported formats: JPG, PNG, GIF, WEBP, MP4, WEBM, OGG (Max size: 10MB)
                            </p>
                        </div>

                        <?php elseif ($type === 'quiz'): ?>
                        <!-- Quiz form fields -->
                        <h2 class="section-title"><i class="material-icons">quiz</i> Quiz Information</h2>
                        <div class="form-group">
                            <label for="title">Quiz Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> Provide a brief overview of what this quiz covers.</p>
                        </div>

                        <div class="form-group">
                            <label for="level_id">Select Level</label>
                            <select id="level_id" name="level_id" required>
                                <option value="">-- Choose a Level --</option>
                                <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level['level_id']; ?>" <?php echo (isset($item['level_id']) && $item['level_id'] == $level['level_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($level['level_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> The level this quiz belongs to.</p>
                        </div>

                        <div class="form-group">
                            <label for="time_limit">Time Limit (minutes)</label>
                            <input type="number" id="time_limit" name="time_limit" value="<?php echo intval($item['time_limit'] ?? 30); ?>" min="1" required>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">timer</i> Set to 0 for no time limit.</p>
                        </div>

                        <h2 class="section-title"><i class="material-icons">help</i> Quiz Questions</h2>
                        <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> Questions are managed in a separate editor for better organization.</p>
                        <a href="edit_questions.php?quiz_id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="material-icons">edit</i> Edit Quiz Questions
                        </a>

                        <?php elseif ($type === 'challenge'): ?>
                        <!-- Challenge form fields -->
                        <div class="form-group">
                            <label for="challenge_name">Challenge Name</label>
                            <input type="text" id="challenge_name" name="challenge_name" value="<?php echo htmlspecialchars($item['challenge_name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="difficulty_level">Difficulty Level</label>
                            <select id="difficulty_level" name="difficulty_level" required>
                                <option value="Beginner" <?php echo (isset($item['difficulty_level']) && $item['difficulty_level'] === 'Beginner') ? 'selected' : ''; ?>>Beginner</option>
                                <option value="Intermediate" <?php echo (isset($item['difficulty_level']) && $item['difficulty_level'] === 'Intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                <option value="Advanced" <?php echo (isset($item['difficulty_level']) && $item['difficulty_level'] === 'Advanced') ? 'selected' : ''; ?>>Advanced</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" value="<?php echo intval($item['points'] ?? 10); ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="time_limit">Time Limit (minutes)</label>
                            <input type="number" id="time_limit" name="time_limit" value="<?php echo intval($item['time_limit'] ?? 30); ?>" min="1" required>
                            <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">timer</i> Set to 0 for no time limit.</p>
                        </div>

                        <h2 class="section-title"><i class="material-icons">code</i> Challenge Questions</h2>
                        <p class="form-note"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i> Questions are managed in a separate editor for better organization.</p>
                        <a href="edit_questions.php?challenge_id=<?php echo $id; ?>" class="btn btn-primary">
                            <i class="material-icons">edit</i> Edit Challenge Questions
                        </a>

                        <?php elseif ($type === 'achievement'): ?>
                        <!-- Achievement form fields -->
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="achievement_type">Achievement Type</label>
                            <select id="achievement_type" name="achievement_type" required>
                                <option value="completion" <?php echo (isset($item['achievement_type']) && $item['achievement_type'] === 'completion') ? 'selected' : ''; ?>>Completion</option>
                                <option value="progress" <?php echo (isset($item['achievement_type']) && $item['achievement_type'] === 'progress') ? 'selected' : ''; ?>>Progress</option>
                                <option value="skill" <?php echo (isset($item['achievement_type']) && $item['achievement_type'] === 'skill') ? 'selected' : ''; ?>>Skill</option>
                                <option value="special" <?php echo (isset($item['achievement_type']) && $item['achievement_type'] === 'special') ? 'selected' : ''; ?>>Special</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="points">Points</label>
                            <input type="number" id="points" name="points" value="<?php echo intval($item['points'] ?? 10); ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="requirement_value">Requirement Value</label>
                            <input type="number" id="requirement_value" name="requirement_value" value="<?php echo intval($item['requirement_value'] ?? 1); ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Achievement Status</label>
                            <label class="checkbox-wrapper">
                                Make this achievement visible to students
                                <input type="checkbox" id="is_active" name="is_active" <?php echo (isset($item['is_active']) && $item['is_active'] == 1) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <?php endif; ?>

                        <div class="action-buttons">
                            <a href="managequiz.php" class="btn btn-secondary">
                                <i class="material-icons">cancel</i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons">save</i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="item-container">
                    <div class="item-content">
                        <p>Item not found or an error occurred. Please go back and try again.</p>
                        <div class="action-buttons">
                            <a href="managequiz.php" class="btn btn-secondary">Back to Management</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Show success message and redirect after a delay
        <?php if (!empty($success)): ?>
        setTimeout(function() {
            window.location.href = 'managequiz.php?success=<?php echo urlencode($success); ?>';
        }, 2000);
        <?php endif; ?>
    </script>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

