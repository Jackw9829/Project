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

$error = '';
$success = '';

// Using getLastInsertId() from db_connect.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get database connection
        $pdo = getDbConnection();
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        // Get form data
        $title = trim($_POST['challenge_title']);
        $description = trim($_POST['challenge_description']);
        $difficulty = $_POST['difficulty_level'];
        $points = (int)$_POST['points'];
        
        // Debug form data
        error_log('Form data: ' . print_r($_POST, true));

        // No media upload for challenges
        
        // Validate inputs
        if (empty($title)) {
            throw new Exception('Challenge title is required');
        }

        if ($points <= 0) {
            throw new Exception('Points must be greater than 0');
        }

        // Insert challenge with detailed error handling
        try {
            
            // Insert the challenge - ensure difficulty level matches enum values in database
            // Map the difficulty level to match the enum values in the database
            $difficultyMap = [
                'easy' => 'Beginner',
                'medium' => 'Intermediate',
                'hard' => 'Advanced',
                'expert' => 'Advanced'
            ];
            
            $allowedEnum = ['Beginner', 'Intermediate', 'Advanced'];
            if (in_array($difficulty, $allowedEnum)) {
                $mappedDifficulty = $difficulty;
            } elseif (isset($difficultyMap[$difficulty])) {
                $mappedDifficulty = $difficultyMap[$difficulty];
            } else {
                $mappedDifficulty = 'Intermediate';
            }
            
            // Get database connection
            $pdo = getDbConnection();
            if (!$pdo) {
                throw new Exception('Database connection failed');
            }
            
            // Get time limit from form
            $timeLimit = intval($_POST['time_limit'] ?? 30);
            
            // Prepare and execute the query directly with PDO for better error handling
            try {
                $stmt = $pdo->prepare("INSERT INTO challenges (challenge_name, description, difficulty_level, points, time_limit) 
                                      VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$title, $description, $mappedDifficulty, $points, $timeLimit]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    error_log('SQL Error: ' . json_encode($errorInfo));
                    throw new Exception('Database query failed: ' . $errorInfo[2]);
                }
            } catch (PDOException $pdoEx) {
                error_log('PDO Exception: ' . $pdoEx->getMessage());
                throw new Exception('Database error: ' . $pdoEx->getMessage());
            }
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            throw new Exception('Database error: ' . $e->getMessage());
        }
        
        // Get the last insert ID with error handling
        try {
            // We already have the PDO connection from above
            $challengeId = $pdo->lastInsertId();
            if (!$challengeId) {
                error_log('Failed to get last insert ID for challenge');
                throw new Exception('Could not retrieve challenge ID after creation.');
            }
            
            // Log success for debugging
            error_log('Successfully inserted challenge with ID: ' . $challengeId);
        } catch (Exception $e) {
            error_log('Error getting last insert ID: ' . $e->getMessage());
            throw new Exception('Error retrieving challenge ID: ' . $e->getMessage());
        }
        
        // Insert questions into the challenge_questions table
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $questionNumber = 1;
            
            // Debug questions data
            error_log('Questions data: ' . print_r($_POST['questions'], true));
            
            foreach ($_POST['questions'] as $index => $question) {
                // Skip empty questions
                if (empty(trim($question['text']))) continue;
                
                $questionText = trim($question['text']);
                
                // Process options and correct answer
                if (isset($question['options']) && is_array($question['options'])) {
                    $optionLabels = ['A', 'B', 'C', 'D'];
                    $correctAnswer = isset($question['correct_answer']) ? (int)$question['correct_answer'] : 0;
                    $correctAnswerLetter = ['a', 'b', 'c', 'd'][$correctAnswer];
                    
                    // Get the options
                    $optionA = isset($question['options'][0]) ? trim($question['options'][0]) : '';
                    $optionB = isset($question['options'][1]) ? trim($question['options'][1]) : '';
                    $optionC = isset($question['options'][2]) ? trim($question['options'][2]) : '';
                    $optionD = isset($question['options'][3]) ? trim($question['options'][3]) : '';
                    
                    // Add options to summary
                    foreach ($question['options'] as $optionIndex => $optionText) {
                        if (empty(trim($optionText))) continue;
                        // No summary append
                    }
                    
                    // Debug question data before insertion
                    error_log("Inserting question {$questionNumber}: Text={$questionText}, Options=[{$optionA},{$optionB},{$optionC},{$optionD}], Correct={$correctAnswerLetter}");
                    
                    // Insert question into database
                    try {
                        $questionSql = "INSERT INTO challenge_questions 
                                      (challenge_id, question_text, option_a, option_b, option_c, option_d, correct_answer, question_order, points) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($questionSql);
                        $result = $stmt->execute([
                            $challengeId, 
                            $questionText, 
                            $optionA, 
                            $optionB, 
                            $optionC, 
                            $optionD, 
                            $correctAnswerLetter,
                            $questionNumber,
                            1 // Default points value
                        ]);
                        
                        if (!$result) {
                            $errorInfo = $stmt->errorInfo();
                            error_log('Question insert error: ' . json_encode($errorInfo));
                            // Continue with other questions even if one fails
                        } else {
                            error_log("Successfully inserted question {$questionNumber} for challenge {$challengeId}");
                        }
                    } catch (Exception $qe) {
                        error_log('Error inserting question: ' . $qe->getMessage());
                        // Continue with other questions even if one fails
                    }
                }
                
                $questionNumber++;
            }
            // No appending questions to description or updating challenge description

        }
        
        // Success message
        $success = 'Challenge "' . htmlspecialchars($title) . '" created successfully with ID: ' . $challengeId;
        
        // Log success for debugging
        error_log('Challenge created successfully: ' . $title . ' (ID: ' . $challengeId . ')');
        header("Location: managequiz.php");
        exit();
    } catch (Exception $e) {
        $error = "Error creating challenge: " . $e->getMessage();
        error_log($error);
    }
}

// Set page title
$pageTitle = "Add Challenge - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['admin_theme']) ? $_SESSION['admin_theme'] : 'dark'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for challenge creation */
        .challenge-form {
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
        
        p {
            color: var(--text-color, #222222);
            margin: 10px 0;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
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
            padding: 10px 15px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background-color: var(--input-bg);
            color: white;
            font-family: 'Press Start 2P', 'Courier New', monospace;
            font-size: 12px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 107, 142, 0.25);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
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
            border: none;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-save {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-cancel:hover,
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
        
        .difficulty-select,
        .points-select {
            background-color: var(--input-bg);
            color: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px 15px;
            width: 100%;
            font-family: 'Press Start 2P', 'Courier New', monospace;
            font-size: 12px;
        }
        
        .difficulty-select option,
        .points-select option {
            background-color: var(--card-bg);
            color: white;
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
            color: var(--text-color, #222222);
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
        
        /* Light theme adjustments */
        [data-theme="light"] .form-group label,
        [data-theme="light"] p,
        [data-theme="light"] .question-content {
            color: #222222;
        }
        
        [data-theme="light"] .section-title {
            color: white;
        }
        
        [data-theme="light"] .form-control,
        [data-theme="light"] .difficulty-select,
        [data-theme="light"] .points-select {
            color: #222222;
            background-color: white;
        }
        
        [data-theme="light"] option {
            color: #222222;
            background-color: white;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">extension</i> Create New Challenge</h1>
            </div>
            
            <div class="dashboard-content">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form class="challenge-form" method="POST" enctype="multipart/form-data">
                    <div class="form-section">
                        <div class="section-title"><i class="material-icons">info</i> Challenge Information</div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="challenge_title" class="form-label">Challenge Title</label>
                                <input type="text" id="challenge_title" name="challenge_title" class="form-control" placeholder="Enter challenge title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                <select id="difficulty_level" name="difficulty_level" class="difficulty-select" required>
                                    <option value="Beginner">Beginner</option>
                                    <option value="Intermediate">Intermediate</option>
                                    <option value="Advanced">Advanced</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="challenge_description" class="form-label">Challenge Description</label>
                                <textarea id="challenge_description" name="challenge_description" class="form-control" placeholder="Enter challenge description" rows="4" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="points" class="form-label">Points</label>
                                <select id="points" name="points" class="points-select" required>
                                    <option value="10">10 Points</option>
                                    <option value="20">20 Points</option>
                                    <option value="30">30 Points</option>
                                    <option value="40">40 Points</option>
                                    <option value="50">50 Points</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                <select id="time_limit" name="time_limit" class="points-select" required>
                                    <option value="15">15 Minutes</option>
                                    <option value="30" selected>30 Minutes</option>
                                    <option value="45">45 Minutes</option>
                                    <option value="60">60 Minutes</option>
                                </select>
                            </div>
                            
                            <!-- Media upload removed as requested -->
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="section-title"><i class="material-icons">quiz</i> Challenge Questions</div>
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <p>Add multiple-choice questions for this challenge. Each question must have exactly 4 options with one correct answer.</p>
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
                            <i class="material-icons">save</i> Create Challenge
                        </button>
                    </div>
                </form>
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
                            <label>Question Text</label>
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

