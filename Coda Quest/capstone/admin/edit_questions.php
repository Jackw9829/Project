<?php
// Initialize session and check admin access
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php?error=You must be an admin to access this page");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';

// Initialize database connection
$pdo = getDbConnection();
if (!$pdo) {
    die("Failed to connect to the database. Please check your database configuration.");
}

// Get item ID and type from URL
$quizId = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
$challengeId = isset($_GET['challenge_id']) ? intval($_GET['challenge_id']) : 0;

// Determine item type
$itemType = '';
$itemId = 0;

if ($quizId > 0) {
    $itemType = 'quiz';
    $itemId = $quizId;
} elseif ($challengeId > 0) {
    $itemType = 'challenge';
    $itemId = $challengeId;
}

// Validate parameters
if ($itemId <= 0) {
    header("Location: managequiz.php?error=Invalid item ID");
    exit();
}

// Initialize variables
$quiz = null;
$questions = [];
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Process each question
        $questionIds = $_POST['question_id'] ?? [];
        $questionTexts = $_POST['question_text'] ?? [];
        $optionsA = $_POST['option_a'] ?? [];
        $optionsB = $_POST['option_b'] ?? [];
        $optionsC = $_POST['option_c'] ?? [];
        $optionsD = $_POST['option_d'] ?? [];
        $correctAnswers = $_POST['correct_answer'] ?? [];
        $points = $_POST['points'] ?? [];
        $questionOrders = $_POST['question_order'] ?? [];
        
        // Update existing questions
        foreach ($questionIds as $index => $questionId) {
            if ($questionId > 0) {
                if ($itemType === 'quiz') {
                    $sql = "UPDATE quiz_questions SET 
                            question_text = ?, 
                            option_a = ?, 
                            option_b = ?, 
                            option_c = ?, 
                            option_d = ?, 
                            correct_answer = ?, 
                            points = ?,
                            question_order = ? 
                            WHERE question_id = ? AND quiz_id = ?";
                    
                    executeQuery($sql, [
                        $questionTexts[$index],
                        $optionsA[$index],
                        $optionsB[$index],
                        $optionsC[$index],
                        $optionsD[$index],
                        $correctAnswers[$index],
                        intval($points[$index]),
                        intval($questionOrders[$index]),
                        $questionId,
                        $itemId
                    ]);
                } elseif ($itemType === 'challenge') {
                    $sql = "UPDATE challenge_questions SET 
                            question_text = ?, 
                            option_a = ?, 
                            option_b = ?, 
                            option_c = ?, 
                            option_d = ?, 
                            correct_answer = ?, 
                            points = ?,
                            question_order = ? 
                            WHERE question_id = ? AND challenge_id = ?";
                    
                    executeQuery($sql, [
                        $questionTexts[$index],
                        $optionsA[$index],
                        $optionsB[$index],
                        $optionsC[$index],
                        $optionsD[$index],
                        $correctAnswers[$index],
                        intval($points[$index]),
                        intval($questionOrders[$index]),
                        $questionId,
                        $itemId
                    ]);
                }
            }
        }
        
        // Add new questions
        $newQuestionTexts = $_POST['new_question_text'] ?? [];
        $newOptionsA = $_POST['new_option_a'] ?? [];
        $newOptionsB = $_POST['new_option_b'] ?? [];
        $newOptionsC = $_POST['new_option_c'] ?? [];
        $newOptionsD = $_POST['new_option_d'] ?? [];
        $newCorrectAnswers = $_POST['new_correct_answer'] ?? [];
        $newPoints = $_POST['new_points'] ?? [];
        $newQuestionOrders = $_POST['new_question_order'] ?? [];
        
        for ($i = 0; $i < count($newQuestionTexts); $i++) {
            if (!empty($newQuestionTexts[$i])) {
                if ($itemType === 'quiz') {
                    $sql = "INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, question_order) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($sql, [
                        $itemId,
                        $newQuestionTexts[$i],
                        $newOptionsA[$i],
                        $newOptionsB[$i],
                        $newOptionsC[$i],
                        $newOptionsD[$i],
                        $newCorrectAnswers[$i],
                        intval($newPoints[$i]),
                        intval($newQuestionOrders[$i])
                    ]);
                } elseif ($itemType === 'challenge') {
                    $sql = "INSERT INTO challenge_questions (challenge_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, question_order) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    executeQuery($sql, [
                        $itemId,
                        $newQuestionTexts[$i],
                        $newOptionsA[$i],
                        $newOptionsB[$i],
                        $newOptionsC[$i],
                        $newOptionsD[$i],
                        $newCorrectAnswers[$i],
                        intval($newPoints[$i]),
                        intval($newQuestionOrders[$i])
                    ]);
                }
            }
        }
        
        // Delete questions if requested
        if (isset($_POST['delete_questions']) && is_array($_POST['delete_questions'])) {
            foreach ($_POST['delete_questions'] as $deleteId) {
                if ($itemType === 'quiz') {
                    $sql = "DELETE FROM quiz_questions WHERE question_id = ? AND quiz_id = ?";
                    executeQuery($sql, [$deleteId, $itemId]);
                } elseif ($itemType === 'challenge') {
                    $sql = "DELETE FROM challenge_questions WHERE question_id = ? AND challenge_id = ?";
                    executeQuery($sql, [$deleteId, $itemId]);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Questions updated successfully";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get item details
try {
    if ($itemType === 'quiz') {
        $sql = "SELECT q.*, l.level_name FROM quizzes q 
                LEFT JOIN levels l ON q.level_id = l.level_id 
                WHERE q.quiz_id = ?";
        $result = executeQuery($sql, [$itemId]);
        
        if (!empty($result)) {
            $quiz = $result[0];
            
            // Get quiz questions
            $sql = "SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC";
            $questions = executeQuery($sql, [$itemId]);
        } else {
            throw new Exception("Quiz not found");
        }
    } elseif ($itemType === 'challenge') {
        $sql = "SELECT * FROM challenges WHERE challenge_id = ?";
        $result = executeQuery($sql, [$itemId]);
        
        if (!empty($result)) {
            $quiz = $result[0]; // Use $quiz variable for consistency in the template
            $quiz['title'] = $quiz['challenge_name']; // Map challenge_name to title for UI consistency
            
            // Get challenge questions
            $sql = "SELECT * FROM challenge_questions WHERE challenge_id = ? ORDER BY question_order ASC";
            $questions = executeQuery($sql, [$itemId]);
        } else {
            throw new Exception("Challenge not found");
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?php echo ucfirst($itemType); ?> Questions - CodaQuest Admin</title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for edit questions page */
        .quiz-info {
            background-color: var(--card-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .quiz-info-item {
            flex: 1;
            min-width: 200px;
        }

        .quiz-info p {
            margin-bottom: 10px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quiz-info strong {
            color: var(--primary-color);
            font-weight: 600;
            min-width: 100px;
        }

        .question-container {
            background-color: var(--card-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .question-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 4px solid var(--border-color);
        }

        .question-number {
            font-size: 1.1rem;
            color: var(--primary-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .question-number i {
            font-size: 1.2rem;
        }

        .remove-question {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            transition: all 0.3s ease;
        }

        .remove-question:hover {
            transform: scale(1.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background-color: var(--input-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .option-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }

        .option-group {
            display: flex;
            flex-direction: column;
            background-color: var(--input-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 15px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .option-group:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .option-group.selected {
            border-color: var(--primary-color);
            background-color: rgba(var(--primary-color-rgb), 0.1);
        }

        .option-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .option-letter {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.1rem;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .option-group:hover .option-letter {
            background-color: var(--primary-color);
            color: white;
        }

        .option-group.selected .option-letter {
            background-color: var(--primary-color);
            color: white;
        }

        .option-input {
            width: 100%;
            padding: 12px;
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .option-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.2);
        }

        .option-input::placeholder {
            color: var(--text-muted);
        }

        .option-radio {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        /* Theme-specific adjustments */
        [data-theme="light"] .option-group {
            background-color: var(--card-bg);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        [data-theme="light"] .option-group:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        [data-theme="light"] .option-group.selected {
            box-shadow: 0 4px 8px rgba(var(--primary-color-rgb), 0.15);
        }

        [data-theme="light"] .option-input {
            background-color: white;
            border-color: var(--border-color);
            color: #222222;
        }

        [data-theme="light"] .option-input:focus {
            background-color: white;
        }
        
        [data-theme="light"] .form-group label, 
        [data-theme="light"] .question-number,
        [data-theme="light"] .option-label,
        [data-theme="light"] .section-title {
            color: #222222;
        }

        .correct-answer-section {
            margin-top: 20px;
            padding: 20px;
            background-color: var(--input-bg);
            border: 4px solid var(--border-color);
            border-radius: var(--border-radius);
        }

        .correct-answer-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .correct-answer-title i {
            font-size: 1.2rem;
        }

        .new-question-container {
            border: 4px dashed var(--border-color);
            padding: 20px;
            margin: 20px 0;
            border-radius: var(--border-radius);
            background-color: rgba(var(--primary-color-rgb), 0.05);
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 4px solid var(--border-color);
        }

        .btn-add {
            margin-bottom: 20px;
            width: 100%;
            padding: 12px;
            font-size: 1rem;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 4px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .option-container {
                grid-template-columns: 1fr;
            }

            .quiz-info {
                flex-direction: column;
            }

            .quiz-info-item {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title">
                    <i class="material-icons">quiz</i>
                    Edit <?php echo ucfirst($itemType); ?> Questions
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

                <?php if ($quiz): ?>
                <div class="quiz-info">
                    <div class="quiz-info-item">
                        <p><strong><i class="material-icons">title</i> Title:</strong> <?php echo htmlspecialchars($quiz['title']); ?></p>
                        <?php if ($itemType === 'quiz'): ?>
                            <p><strong><i class="material-icons">school</i> Level:</strong> <?php echo htmlspecialchars($quiz['level_name'] ?? 'N/A'); ?></p>
                            <p><strong><i class="material-icons">timer</i> Time Limit:</strong> <?php echo intval($quiz['time_limit'] ?? 0); ?> minutes</p>
                        <?php elseif ($itemType === 'challenge'): ?>
                            <p><strong><i class="material-icons">trending_up</i> Difficulty:</strong> <?php echo htmlspecialchars($quiz['difficulty_level'] ?? 'N/A'); ?></p>
                            <p><strong><i class="material-icons">star</i> Points:</strong> <?php echo intval($quiz['points'] ?? 0); ?></p>
                            <p><strong><i class="material-icons">timer</i> Time Limit:</strong> <?php echo intval($quiz['time_limit'] ?? 30); ?> minutes</p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="edit_questions.php?<?php echo $itemType; ?>_id=<?php echo $itemId; ?>">
                    <h2 class="section-title">
                        <i class="material-icons">list</i>
                        Existing Questions
                    </h2>
                    
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $index => $question): ?>
                        <div class="question-container">
                            <div class="question-header">
                                <div class="question-number">
                                    <i class="material-icons">help_outline</i>
                                    Question <?php echo $index + 1; ?>
                                </div>
                                <button type="button" class="remove-question" onclick="markForDeletion(<?php echo $question['question_id']; ?>, this)">
                                    <i class="material-icons">delete</i>
                                </button>
                            </div>
                            
                            <input type="hidden" name="question_id[]" value="<?php echo $question['question_id']; ?>">
                            <input type="hidden" name="delete_questions[]" id="delete_<?php echo $question['question_id']; ?>" value="" disabled>
                            
                            <div class="form-group">
                                <label for="question_text_<?php echo $index; ?>">Question Text</label>
                                <textarea id="question_text_<?php echo $index; ?>" name="question_text[]" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                            </div>
                            
                            <div class="option-container">
                                <div class="option-group <?php echo strtolower($question['correct_answer']) === 'a' ? 'selected' : ''; ?>">
                                    <input type="radio" class="option-radio" name="correct_answer[<?php echo $index; ?>]" value="a" <?php echo strtolower($question['correct_answer']) === 'a' ? 'checked' : ''; ?> required>
                                    <div class="option-label">
                                        <span class="option-letter">A</span>
                                        <label for="option_a_<?php echo $index; ?>">Option A</label>
                                    </div>
                                    <input type="text" id="option_a_<?php echo $index; ?>" name="option_a[]" class="option-input" value="<?php echo htmlspecialchars($question['option_a']); ?>" required>
                                </div>
                                
                                <div class="option-group <?php echo strtolower($question['correct_answer']) === 'b' ? 'selected' : ''; ?>">
                                    <input type="radio" class="option-radio" name="correct_answer[<?php echo $index; ?>]" value="b" <?php echo strtolower($question['correct_answer']) === 'b' ? 'checked' : ''; ?>>
                                    <div class="option-label">
                                        <span class="option-letter">B</span>
                                        <label for="option_b_<?php echo $index; ?>">Option B</label>
                                    </div>
                                    <input type="text" id="option_b_<?php echo $index; ?>" name="option_b[]" class="option-input" value="<?php echo htmlspecialchars($question['option_b']); ?>" required>
                                </div>
                                
                                <div class="option-group <?php echo strtolower($question['correct_answer']) === 'c' ? 'selected' : ''; ?>">
                                    <input type="radio" class="option-radio" name="correct_answer[<?php echo $index; ?>]" value="c" <?php echo strtolower($question['correct_answer']) === 'c' ? 'checked' : ''; ?>>
                                    <div class="option-label">
                                        <span class="option-letter">C</span>
                                        <label for="option_c_<?php echo $index; ?>">Option C</label>
                                    </div>
                                    <input type="text" id="option_c_<?php echo $index; ?>" name="option_c[]" class="option-input" value="<?php echo htmlspecialchars($question['option_c']); ?>" required>
                                </div>
                                
                                <div class="option-group <?php echo strtolower($question['correct_answer']) === 'd' ? 'selected' : ''; ?>">
                                    <input type="radio" class="option-radio" name="correct_answer[<?php echo $index; ?>]" value="d" <?php echo strtolower($question['correct_answer']) === 'd' ? 'checked' : ''; ?>>
                                    <div class="option-label">
                                        <span class="option-letter">D</span>
                                        <label for="option_d_<?php echo $index; ?>">Option D</label>
                                    </div>
                                    <input type="text" id="option_d_<?php echo $index; ?>" name="option_d[]" class="option-input" value="<?php echo htmlspecialchars($question['option_d']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="points_<?php echo $index; ?>">Points</label>
                                <input type="number" id="points_<?php echo $index; ?>" name="points[]" value="<?php echo intval($question['points']); ?>" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="question_order_<?php echo $index; ?>">Question Order</label>
                                <input type="number" id="question_order_<?php echo $index; ?>" name="question_order[]" value="<?php echo intval($question['question_order']); ?>" min="1" required>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No questions found for this <?php echo $itemType; ?>. Add new questions below.</p>
                    <?php endif; ?>
                    
                    <h2 class="section-title">
                        <i class="material-icons">add_circle</i>
                        Add New Questions
                    </h2>
                    <div id="new-questions-container">
                        <!-- New questions will be added here -->
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-add" onclick="addNewQuestion()">
                        <i class="material-icons">add</i> Add New Question
                    </button>
                    
                    <div class="action-buttons">
                        <a href="edit_item.php?type=<?php echo $itemType; ?>&id=<?php echo $itemId; ?>" class="btn btn-secondary">
                            <i class="material-icons">cancel</i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons">save</i> Save All Questions
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-error">
                    <i class="material-icons">error</i> <?php echo ucfirst($itemType); ?> not found or an error occurred. Please go back and try again.
                </div>
                <div class="action-buttons">
                    <a href="managequiz.php" class="btn btn-secondary">
                        <i class="material-icons">arrow_back</i> Back to Management
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Counter for new questions
        let newQuestionCount = 0;
        
        // Add new question form
        function addNewQuestion() {
            const container = document.getElementById('new-questions-container');
            const newQuestionDiv = document.createElement('div');
            newQuestionDiv.className = 'question-container new-question-container';
            newQuestionDiv.innerHTML = `
                <div class="question-header">
                    <div class="question-number">
                        <i class="material-icons">help_outline</i>
                        New Question
                    </div>
                    <button type="button" class="remove-question" onclick="removeNewQuestion(this)">
                        <i class="material-icons">delete</i>
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="new_question_text_${newQuestionCount}">Question Text</label>
                    <textarea id="new_question_text_${newQuestionCount}" name="new_question_text[]" required></textarea>
                </div>
                
                <div class="option-container">
                    <div class="option-group">
                        <input type="radio" class="option-radio" name="new_correct_answer[${newQuestionCount}]" value="a" required checked>
                        <div class="option-label">
                            <span class="option-letter">A</span>
                            <label for="new_option_a_${newQuestionCount}">Option A</label>
                        </div>
                        <input type="text" id="new_option_a_${newQuestionCount}" name="new_option_a[]" class="option-input" required>
                    </div>
                    
                    <div class="option-group">
                        <input type="radio" class="option-radio" name="new_correct_answer[${newQuestionCount}]" value="b">
                        <div class="option-label">
                            <span class="option-letter">B</span>
                            <label for="new_option_b_${newQuestionCount}">Option B</label>
                        </div>
                        <input type="text" id="new_option_b_${newQuestionCount}" name="new_option_b[]" class="option-input" required>
                    </div>
                    
                    <div class="option-group">
                        <input type="radio" class="option-radio" name="new_correct_answer[${newQuestionCount}]" value="c">
                        <div class="option-label">
                            <span class="option-letter">C</span>
                            <label for="new_option_c_${newQuestionCount}">Option C</label>
                        </div>
                        <input type="text" id="new_option_c_${newQuestionCount}" name="new_option_c[]" class="option-input" required>
                    </div>
                    
                    <div class="option-group">
                        <input type="radio" class="option-radio" name="new_correct_answer[${newQuestionCount}]" value="d">
                        <div class="option-label">
                            <span class="option-letter">D</span>
                            <label for="new_option_d_${newQuestionCount}">Option D</label>
                        </div>
                        <input type="text" id="new_option_d_${newQuestionCount}" name="new_option_d[]" class="option-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="new_points_${newQuestionCount}">Points</label>
                    <input type="number" id="new_points_${newQuestionCount}" name="new_points[]" value="10" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="new_question_order_${newQuestionCount}">Question Order</label>
                    <input type="number" id="new_question_order_${newQuestionCount}" name="new_question_order[]" value="${getNextQuestionOrder()}" min="1" required>
                </div>
            `;
            
            container.appendChild(newQuestionDiv);
            newQuestionCount++;
            
            // Initialize handlers for the newly added options
            initOptionGroupSelectionHandlers();
        }
        
        // Remove new question
        function removeNewQuestion(button) {
            const questionDiv = button.closest('.question-container');
            questionDiv.remove();
        }
        
        // Mark existing question for deletion
        function markForDeletion(questionId, button) {
            const questionDiv = button.closest('.question-container');
            const deleteInput = document.getElementById('delete_' + questionId);
            
            if (questionDiv.classList.contains('marked-for-deletion')) {
                // Unmark for deletion
                questionDiv.classList.remove('marked-for-deletion');
                questionDiv.style.opacity = '1';
                deleteInput.value = '';
                deleteInput.disabled = true;
                button.innerHTML = '<i class="material-icons">delete</i>';
            } else {
                // Mark for deletion
                questionDiv.classList.add('marked-for-deletion');
                questionDiv.style.opacity = '0.5';
                deleteInput.value = questionId;
                deleteInput.disabled = false;
                button.innerHTML = '<i class="material-icons">restore</i>';
            }
        }
        
        // Get next question order
        function getNextQuestionOrder() {
            // Count existing questions
            const existingQuestions = document.querySelectorAll('.question-container:not(.new-question-container)').length;
            // Count new questions
            const newQuestions = document.querySelectorAll('.new-question-container').length;
            
            return existingQuestions + newQuestions + 1;
        }

        // Add option group selection effect
        document.addEventListener('DOMContentLoaded', function() {
            initOptionGroupSelectionHandlers();
        });

        // Function to initialize the selection handlers for option groups
        function initOptionGroupSelectionHandlers() {
            const optionGroups = document.querySelectorAll('.option-group');

            optionGroups.forEach(group => {
                group.addEventListener('click', function(e) {
                    // Don't trigger if clicking the input field
                    if (e.target.tagName === 'INPUT' && e.target.type === 'text') {
                        return;
                    }
                    
                    const radioInput = this.querySelector('input[type="radio"]');
                    if (radioInput) {
                        radioInput.checked = true;
                        updateSelectedStates(radioInput);
                    }
                });
            });
        }

        function updateSelectedStates(radioInput) {
            const questionContainer = radioInput.closest('.question-container');
            const optionGroups = questionContainer.querySelectorAll('.option-group');

            // Update option groups
            optionGroups.forEach(group => {
                group.classList.remove('selected');
            });
            radioInput.closest('.option-group').classList.add('selected');
        }
        
        // Show success message and redirect after a delay
        <?php if (!empty($success)): ?>
        setTimeout(function() {
            window.location.href = 'edit_item.php?type=<?php echo $itemType; ?>&id=<?php echo $itemId; ?>&success=<?php echo urlencode($success); ?>';
        }, 2000);
        <?php endif; ?>
    </script>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

