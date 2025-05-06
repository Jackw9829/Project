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

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Set page title
$pageTitle = 'Create New Level';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $notesMediaPath = '';
        
        // Create a new level
        $levelName = trim($_POST['level_name']);
        $levelDescription = trim($_POST['level_description']);
        $levelOrder = (int)$_POST['level_order'];
        $levelActive = isset($_POST['level_active']) ? 1 : 0;
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : ''; // Notes for the level
        
        // Handle file upload for level notes media
        if (isset($_FILES['notes_media']) && $_FILES['notes_media']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm', 'video/ogg'];
            $fileType = $_FILES['notes_media']['type'];
            $fileSize = $_FILES['notes_media']['size'];
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception('Invalid file type for notes image/video.');
            }
            if ($fileSize > 10 * 1024 * 1024) {
                throw new Exception('Notes image/video must be less than 10MB.');
            }
            $ext = pathinfo($_FILES['notes_media']['name'], PATHINFO_EXTENSION);
            $targetDir = '../uploads/quiz_notes_media/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $fileName = uniqid('notes_media_') . '.' . $ext;
            $targetPath = $targetDir . $fileName;
            if (!move_uploaded_file($_FILES['notes_media']['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload notes image/video.');
            }
            // Save relative path for DB
            $notesMediaPath = 'uploads/quiz_notes_media/' . $fileName;
        }
        
        // Validate level inputs
        if (empty($levelName)) {
            throw new Exception('Level name is required');
        }

        // Insert the level
        $levelSql = "INSERT INTO levels (level_name, description, level_order, is_active, notes, notes_media) VALUES (?, ?, ?, ?, ?, ?)";
        $levelResult = executeQuery($levelSql, [$levelName, $levelDescription, $levelOrder, $levelActive, $notes, $notesMediaPath]);
        if (!$levelResult) {
            throw new Exception('Failed to create new level.');
        }
        $success = "Level created successfully!";
        
        // Redirect to manage quiz page
        header("Location: managequiz.php?success=" . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        $error = "Error creating level: " . $e->getMessage();
        error_log($error);
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['admin_theme']) ? $_SESSION['admin_theme'] : 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for level creation */
        .level-form {
            margin-top: 20px;
        }
        
        .form-section {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            border: 2px solid var(--primary-color);
            overflow: hidden;
        }
        
        .section-title {
            padding: 15px 20px;
            background-color: var(--primary-color);
            color: white;
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        p {
            color: var(--text-color, #222222);
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 20px;
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-group.full-width {
                grid-column: span 2;
            }
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color, #222222);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background-color: var(--input-bg);
            color: var(--text-color, #222222);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        /* Checkbox styling */
        .checkbox-container {
            display: block;
            position: relative;
            padding-left: 35px;
            margin-bottom: 12px;
            cursor: pointer;
            font-size: 0.9rem;
            user-select: none;
            color: var(--text-color, #222222);
        }
        
        .checkbox-container input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: var(--input-bg);
            border: 2px solid var(--border-color);
            border-radius: 4px;
        }
        
        .checkbox-container:hover input ~ .checkmark {
            border-color: var(--primary-color);
        }
        
        .checkbox-container input:checked ~ .checkmark {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        
        .checkbox-container .checkmark:after {
            left: 9px;
            top: 5px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .btn-cancel,
        .btn-save {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            border: none;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-save {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-save:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 12px;
            color: white;
        }
        
        .alert-danger {
            background-color: #e74c3c;
        }
        
        .alert-success {
            background-color: #2ecc71;
        }

        small {
            color: var(--text-muted, #666666) !important;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">add_circle</i> <?php echo $pageTitle; ?></h1>
                <div class="user-info">
                    <!-- User avatar removed -->
                </div>
            </div>
            <div class="dashboard-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form action="" method="post" enctype="multipart/form-data" class="level-form">
                    <div class="form-section">
                        <div class="section-title">
                            <i class="material-icons">layers</i> Level Details
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="level_name" class="form-label">Level Name</label>
                                <input type="text" id="level_name" name="level_name" class="form-control" placeholder="Enter level name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="level_order" class="form-label">Level Order</label>
                                <input type="number" id="level_order" name="level_order" class="form-control" min="1" value="1" placeholder="Enter level order" required>
                                <small>The order in which this level appears (1 = first)</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="level_description" class="form-label">Description</label>
                                <textarea id="level_description" name="level_description" class="form-control" rows="4" placeholder="Enter level description" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-container">Active
                                    <input type="checkbox" name="level_active" checked>
                                    <span class="checkmark"></span>
                                </label>
                                <small>If checked, this level will be visible to students</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="notes" class="form-label">Level Notes (Optional)</label>
                                <textarea id="notes" name="notes" class="form-control" rows="4" placeholder="Enter additional notes or instructions for this level"></textarea>
                                <small>These notes will be shown to students when they access this level</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="notes_media" class="form-label">Notes Media (Optional)</label>
                                <input type="file" id="notes_media" name="notes_media" class="form-control">
                                <small>You can upload an image or video to include with your level notes (Max: 10MB)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="managequiz.php" class="btn-cancel">
                            <i class="material-icons">cancel</i> Cancel
                        </a>
                        <button type="submit" class="btn-save">
                            <i class="material-icons">save</i> Create Level
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

