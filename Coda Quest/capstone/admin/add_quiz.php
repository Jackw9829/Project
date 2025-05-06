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

// Determine if we're adding a level or a quiz
$type = isset($_GET['type']) && $_GET['type'] === 'quiz' ? 'quiz' : 'level';
$pageTitle = $type === 'quiz' ? 'Add New Quiz' : 'Add New Level';

// Get all levels for the dropdown when creating a quiz
$levels = [];
if ($type === 'quiz') {
    $levelsSql = "SELECT level_id, level_name FROM levels WHERE is_active = 1 ORDER BY level_order ASC";
    $levelsResult = executeSimpleQuery($levelsSql);
    if ($levelsResult && is_array($levelsResult)) {
        $levels = $levelsResult;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $notesMediaPath = '';
        
        if ($type === 'level') {
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
        } else {
            // Create a new quiz
            $title = trim($_POST['quiz_title']);
            $description = trim($_POST['quiz_description']);
            $levelId = (int)$_POST['level_id'];
            $points = (int)$_POST['points'];
            $timeLimit = (int)$_POST['time_limit'];
            
            // Validate quiz inputs
            if (empty($title)) {
                throw new Exception('Quiz title is required');
            }
            
            if ($levelId <= 0) {
                throw new Exception('Please select a valid level');
            }
        }
        
        // For quiz type, check if level exists
        if ($type === 'quiz') {
            $levelCheckSql = "SELECT level_id FROM levels WHERE level_id = ?";
            $levelExists = executeQuery($levelCheckSql, [$levelId]);
            if (empty($levelExists)) {
                throw new Exception('Selected level does not exist');
            }
        }

        // For level type, create the level
        if ($type === 'level') {
            // Insert the level
            $levelSql = "INSERT INTO levels (level_name, description, level_order, notes, notes_media) VALUES (?, ?, ?, ?, ?)";
            $levelResult = executeQuery($levelSql, [$levelName, $levelDescription, $levelOrder, $notes, $notesMediaPath]);
            if (!$levelResult) {
                throw new Exception('Failed to create new level.');
            }
            $success = "Level created successfully!";
        } else {
            // Insert quiz
            $quizSql = "INSERT INTO quizzes (level_id, title, description, time_limit, created_at) VALUES (?, ?, ?, ?, NOW())";
            try {
                $quizId = executeInsert($quizSql, [$levelId, $title, $description, $timeLimit]);
                if (!$quizId) {
                    throw new Exception('Failed to create quiz. Database did not return an ID.');
                }
                
                // Store questions
                if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                    foreach ($_POST['questions'] as $index => $question) {
                        // Skip empty questions
                        if (empty(trim($question['text']))) continue;
                        
                        // Insert the question
                        $questionSql = "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $questionOptions = $question['options'] ?? [];
                        $optionA = isset($questionOptions[0]) ? trim($questionOptions[0]) : '';
                        $optionB = isset($questionOptions[1]) ? trim($questionOptions[1]) : '';
                        $optionC = isset($questionOptions[2]) ? trim($questionOptions[2]) : '';
                        $optionD = isset($questionOptions[3]) ? trim($questionOptions[3]) : '';
                        
                        $correctAnswer = isset($question['correct_answer']) ? (int)$question['correct_answer'] : 0;
                        $correctAnswerLetter = ['a', 'b', 'c', 'd'][$correctAnswer];
                        
                        // Each question is worth 5 points by default
                        $questionPoints = 5;
                        
                        $questionResult = executeQuery($questionSql, [
                            $quizId, 
                            trim($question['text']), 
                            $optionA, 
                            $optionB, 
                            $optionC, 
                            $optionD, 
                            $correctAnswerLetter,
                            $questionPoints
                        ]);
                        
                        if ($questionResult === false) {
                            throw new Exception('Failed to save question #' . ($index + 1));
                        }
                    }
                    
                    $success = "Quiz and questions created successfully!";
                } else {
                    $success = "Quiz created successfully! You can now add questions to it.";
                }
            } catch (Exception $e) {
                $error = "Error creating quiz: " . $e->getMessage();
                error_log($error);
            }
        }
    } catch (Exception $e) {
        $error = "Error creating " . ($type === 'level' ? 'level' : 'quiz') . ": " . $e->getMessage();
        error_log($error);
    }
}

// Set page title
$pageTitle = "Add New " . ($type === 'quiz' ? 'Quiz' : 'Level');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['admin_theme']) ? $_SESSION['admin_theme'] : 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for quiz creation */
        .quiz-form {
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
        /* Custom style for quiz level dropdown */
        select#level_id,
        select#points,
        select#time_limit {
            background-color: var(--card-bg);
            color: var(--text-color, #222222);
            border: 2px solid var(--border-color, #555);
            border-radius: 4px;
            font-family: 'Press Start 2P', 'Courier New', Courier, monospace;
            font-size: 1rem;
            padding: 10px 12px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            box-shadow: none;
        }
        select#level_id:focus,
        select#points:focus,
        select#time_limit:focus {
            outline: none;
            border-color: var(--primary-color, #e91e63);
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
        
        /* Question styles */
        .questions-container {
            margin-top: 20px;
        }
        
        .question-block {
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        .question-header {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .question-content {
            padding: 15px;
        }
        
        .options-container {
            margin-top: 15px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            background-color: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 4px;
        }
        
        .option-item input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }
        
        .question-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-add-question,
        .btn-remove-question {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-add-question {
            background-color: #2ecc71;
            color: white;
        }
        
        .btn-remove-question {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-add-question:hover,
        .btn-remove-question:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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
        <!-- Sidebar is included from admin_styles.php -->
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
                    
                    <form action="" method="post" enctype="multipart/form-data" class="quiz-form">
                        <div class="form-section">
                            <div class="section-title">
                                <i class="material-icons"><?php echo $type === 'quiz' ? 'quiz' : 'layers'; ?></i> <?php echo $type === 'quiz' ? 'Quiz' : 'Level'; ?> Details
                            </div>
                            
                            <div class="form-grid">
                                <?php if ($type === 'level'): ?>
                                <!-- Level Form Fields -->
                                <div class="form-group">
                                    <label for="level_name" class="form-label">Level Name</label>
                                    <input type="text" id="level_name" name="level_name" class="form-control" placeholder="Enter level name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="level_order" class="form-label">Level Order</label>
                                    <input type="number" id="level_order" name="level_order" class="form-control" min="1" value="1" placeholder="Enter level order" required>
                                    <small style="color: #aaa;">The order in which this level appears (1 = first)</small>
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
                                    <small style="color: #aaa;">If checked, this level will be visible to students</small>
                                </div>
                                <?php else: ?>
                                <!-- Quiz Form Fields -->
                                <div class="form-group">
                                    <label for="quiz_title" class="form-label">Quiz Title</label>
                                    <input type="text" id="quiz_title" name="quiz_title" class="form-control" placeholder="Enter quiz title" required>
                                </div>
                            
                            <div class="form-group full-width">
                                <label for="quiz_description" class="form-label">Quiz Description</label>
                                <textarea id="quiz_description" name="quiz_description" class="form-control" rows="4" placeholder="Enter quiz description" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="level_id" class="form-label">Select Level</label>
                                <select id="level_id" name="level_id" class="form-control" required>
                                    <option value="">-- Select Level --</option>
                                    <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['level_id']; ?>"><?php echo htmlspecialchars($level['level_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small style="color: #aaa;">The level this quiz belongs to</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="points" class="form-label">Points</label>
                                <select id="points" name="points" class="form-control" required>
                                    <option value="5">5 points</option>
                                    <option value="10" selected>10 points</option>
                                    <option value="15">15 points</option>
                                    <option value="20">20 points</option>
                                    <option value="25">25 points</option>
                                    <option value="30">30 points</option>
                                    <option value="40">40 points</option>
                                    <option value="50">50 points</option>
                                </select>
                                <small style="color: #aaa;">Points awarded for completing this quiz</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_limit" class="form-label">Time Limit</label>
                                <select id="time_limit" name="time_limit" class="form-control">
                                    <option value="0">No Time Limit</option>
                                    <option value="5">5 minutes</option>
                                    <option value="10">10 minutes</option>
                                    <option value="15" selected>15 minutes</option>
                                    <option value="20">20 minutes</option>
                                    <option value="30">30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">60 minutes</option>
                                </select>
                                <small style="color: #aaa;">Time allowed to complete this quiz</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title"><i class="material-icons">quiz</i> Quiz Questions</div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <p>Add multiple-choice questions for this quiz. Each question must have exactly 4 options with one correct answer.</p>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="questions-container" id="questionsContainer">
                            <div class="question-block">
                                        <div class="question-header">
                                            <i class="material-icons">quiz</i> Question 1
                                        </div>
                                        <div class="question-content">
                                            <div class="form-group">
                                                <label class="form-label">Question Text</label>
                                                <textarea name="questions[0][text]" class="form-control" placeholder="Enter your question here" required></textarea>
                                            </div>
                                            
                                            <div class="options-container">
                                                <div class="option-item">
                                                    <input type="radio" name="questions[0][correct_answer]" value="0" checked required>
                                                    <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option 1" required>
                                                </div>
                                                <div class="option-item">
                                                    <input type="radio" name="questions[0][correct_answer]" value="1">
                                                    <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option 2" required>
                                                </div>
                                                <div class="option-item">
                                                    <input type="radio" name="questions[0][correct_answer]" value="2">
                                                    <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option 3" required>
                                                </div>
                                                <div class="option-item">
                                                    <input type="radio" name="questions[0][correct_answer]" value="3">
                                                    <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option 4" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <div class="question-actions">
                                    <button type="button" id="addQuestionBtn" class="btn-add-question">
                                        <i class="material-icons">add</i> Add Question
                                    </button>
                                    <button type="button" id="removeQuestionBtn" class="btn-remove-question" style="display: none;">
                                        <i class="material-icons">remove</i> Remove Last Question
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="managequiz.php" class="btn-cancel">
                            <i class="material-icons">cancel</i> Cancel
                        </a>
                        <button type="submit" class="btn-save">
                            <i class="material-icons">save</i> Create <?php echo ucfirst($type); ?>
                        </button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const questionsContainer = document.getElementById('questionsContainer');
            const addQuestionBtn = document.getElementById('addQuestionBtn');
            const removeQuestionBtn = document.getElementById('removeQuestionBtn');
            let questionCount = 1;
            
            // Update question numbers and toggle remove button visibility
            function updateQuestionNumbers() {
                const questions = questionsContainer.querySelectorAll('.question-block');
                questions.forEach((question, index) => {
                    const header = question.querySelector('.question-header');
                    header.innerHTML = `<i class="material-icons">quiz</i> Question ${index + 1}`;
                });
                
                // Show/hide remove button based on question count
                if (questions.length > 1) {
                    removeQuestionBtn.style.display = 'block';
                } else {
                    removeQuestionBtn.style.display = 'none';
                }
            }
            
            // Add a new question
            addQuestionBtn.addEventListener('click', function() {
                questionCount++;
                
                const questionBlock = document.createElement('div');
                questionBlock.className = 'question-block';
                questionBlock.innerHTML = `
                    <div class="question-header">
                        <i class="material-icons">quiz</i> Question ${questionCount}
                    </div>
                    <div class="question-content">
                        <div class="form-group">
                            <label class="form-label">Question Text</label>
                            <textarea name="questions[${questionCount-1}][text]" class="form-control" placeholder="Enter your question here" required></textarea>
                        </div>
                        
                        <div class="options-container">
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="0" checked required>
                                <input type="text" name="questions[${questionCount-1}][options][]" class="form-control" placeholder="Option 1" required>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="1">
                                <input type="text" name="questions[${questionCount-1}][options][]" class="form-control" placeholder="Option 2" required>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="2">
                                <input type="text" name="questions[${questionCount-1}][options][]" class="form-control" placeholder="Option 3" required>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="3">
                                <input type="text" name="questions[${questionCount-1}][options][]" class="form-control" placeholder="Option 4" required>
                            </div>
                        </div>
                    </div>
                `;
                
                questionsContainer.appendChild(questionBlock);
                questionBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                updateQuestionNumbers();
            });
            
            // Remove the last question
            removeQuestionBtn.addEventListener('click', function() {
                const questions = questionsContainer.querySelectorAll('.question-block');
                if (questions.length > 1) {
                    questions[questions.length - 1].remove();
                    updateQuestionNumbers();
                }
            });
            
            // Form validation
            document.querySelector('form').addEventListener('submit', function(e) {
                const questions = questionsContainer.querySelectorAll('.question-block');
                let valid = true;
                
                questions.forEach((question, index) => {
                    const radioButtons = question.querySelectorAll('input[type="radio"]:checked');
                    if (radioButtons.length === 0) {
                        alert(`Please select a correct answer for Question ${index + 1}`);
                        valid = false;
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                }
            });
        });
    </script>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>
