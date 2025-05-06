<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Get quiz details
        $title = trim($_POST['quiz_title']);
        $description = trim($_POST['quiz_description']);
        $deadline = $_POST['deadline_date'] . ' ' . $_POST['deadline_time'];
        
        // Validate inputs
        if (empty($title)) {
            throw new Exception('Quiz title is required');
        }

        // Insert quiz
        $stmt = $pdo->prepare("
            INSERT INTO quizzes (created_by, teacher_id, title, description, due_date, created_at, is_active)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $title, $description, $deadline]);
        $quiz_id = $pdo->lastInsertId();

        // Handle quiz cover image upload
        if (isset($_FILES['quiz_image']) && $_FILES['quiz_image']['error'] === UPLOAD_ERR_OK) {
            $image_path = handleImageUpload($_FILES['quiz_image'], $quiz_id);
            $stmt = $pdo->prepare("UPDATE quizzes SET image_path = ? WHERE quiz_id = ?");
            $stmt->execute([$image_path, $quiz_id]);
        }

        // Process questions
        foreach ($_POST['questions'] as $index => $question) {
            // Skip empty questions
            if (empty(trim($question['text']))) continue;

            // Insert question
            $stmt = $pdo->prepare("
                INSERT INTO questions (quiz_id, question_text, points, question_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $quiz_id,
                trim($question['text']),
                1, // Always set points to 1
                $index + 1
            ]);
            $question_id = $pdo->lastInsertId();

            // Handle question image if present
            if (isset($_FILES['question_images']['name'][$index]) && 
                $_FILES['question_images']['error'][$index] === UPLOAD_ERR_OK) {
                $image_path = handleImageUpload([
                    'name' => $_FILES['question_images']['name'][$index],
                    'type' => $_FILES['question_images']['type'][$index],
                    'tmp_name' => $_FILES['question_images']['tmp_name'][$index],
                    'error' => $_FILES['question_images']['error'][$index],
                    'size' => $_FILES['question_images']['size'][$index]
                ], $quiz_id, $question_id);
                
                $stmt = $pdo->prepare("UPDATE questions SET image_path = ? WHERE question_id = ?");
                $stmt->execute([$image_path, $question_id]);
            }

            // Add options
            if (isset($question['options']) && is_array($question['options'])) {
                foreach ($question['options'] as $optionIndex => $optionText) {
                    if (empty(trim($optionText))) continue;
                    
                    $isCorrect = isset($question['correct_answer']) && 
                                $question['correct_answer'] == $optionIndex;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO question_options (question_id, option_text, is_correct)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$question_id, trim($optionText), $isCorrect]);
                }
            }
        }

        $pdo->commit();
        $success = "Quiz created successfully!";
        header("Location: preview_quiz.php?quiz_id=" . $quiz_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating quiz: " . $e->getMessage();
        error_log($error);
    }
}

// Helper function for image upload
function handleImageUpload($file, $quiz_id, $question_id = null) {
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.');
    }

    $maxFileSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File size too large. Maximum file size is 5MB.');
    }

    $fileName = ($question_id ? 
        "quiz_{$quiz_id}_question_{$question_id}" : 
        "quiz_{$quiz_id}_cover") . ".{$fileExtension}";
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to upload file.');
    }

    return $targetPath;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Quiz - MathQuest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tiny5', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
            padding-top: 60px; /* Add space for fixed header */
        }

        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
        }

        .quiz-creation-container {
            padding: 2rem;
            position: relative;
            z-index: 1;
            margin-top: 1.5rem;
        }

        .content-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            text-align: left;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2.2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .form-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .section-title {
            background: #ffdcdc;
            color: #2c3e50;
            padding: 1.2rem 2rem;
            font-size: 1.3rem;
            font-weight: 500;
        }

        .form-grid {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.8rem;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 500;
        }

        input[type="text"],
        input[type="date"],
        input[type="time"],
        textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            color: #495057;
            transition: all 0.2s ease;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #ffdcdc;
            box-shadow: 0 0 0 3px rgba(255, 220, 220, 0.2);
        }

        .deadline-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .file-label {
            display: block;
            padding: 1.2rem;
            border: 2px dashed #ffdcdc;
            border-radius: 6px;
            color: #2c3e50;
            font-size: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            margin-top: 0.5rem;
        }

        .file-label:hover {
            background: rgba(255, 220, 220, 0.1);
            border-color: #ffcece;
        }

        .file-label.has-file {
            background: #ffdcdc;
            border-style: solid;
        }

        .questions-container {
            padding: 2rem;
        }

        .question-block {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .question-header {
            background-color: #ffdcdc;
            color: #2c3e50;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            border-radius: 6px 6px 0 0;
        }

        .remove-question-btn {
            background-color: #ff6961;
            color: white;
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 6px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .remove-question-btn:hover {
            background-color: #ffcece;
            transform: translateY(-2px);
        }

        .question-content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .options-grid {
            display: grid;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.8rem;
            position: relative;
            padding: 0.5rem;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .option-item:hover {
            background-color: #fff5f5;
        }

        .option-item input[type="text"] {
            flex: 1;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Tiny5', sans-serif;
            transition: border-color 0.2s;
        }

        .option-item input[type="text"]:focus {
            border-color: #ffdcdc;
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 220, 220, 0.3);
        }

        .option-item input[type="radio"] {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            width: 1.4rem;
            height: 1.4rem;
            border: 2px solid #ddd;
            border-radius: 50%;
            outline: none;
            cursor: pointer;
            position: relative;
            margin: 0;
            padding: 0;
            transition: all 0.2s ease;
        }

        .option-item input[type="radio"]:checked {
            border-color: #ffdcdc;
            background-color: #ffdcdc;
        }

        .option-item input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 0.5rem;
            height: 0.5rem;
            background-color: white;
            border-radius: 50%;
        }

        .option-item input[type="radio"]:hover {
            border-color: #ffdcdc;
        }

        .question-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 1rem;
        }

        .add-question-btn,
        .remove-question-btn {
            padding: 0.8rem 2rem;
            border-radius: 6px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
        }

        .add-question-btn {
            background-color: #ffdcdc;
            color: #2c3e50;
        }

        .add-question-btn:hover {
            background-color: #ffcece;
        }

        .remove-question-btn {
            background-color: #ff6961;
            color: white;
        }

        .remove-question-btn:hover {
            background-color: #ffcece;
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .save-btn,
        .cancel-btn {
            padding: 0.8rem 2rem;
            border-radius: 6px;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .save-btn {
            background: #333;
            color: white;
            border: none;
        }

        .save-btn:hover {
            background: #444;
            transform: translateY(-2px);
        }

        .cancel-btn {
            background: #666;
            color: white;
            border: none;
        }

        .cancel-btn:hover {
            background: #777;
            transform: translateY(-2px);
        }

        .error-message,
        .success-message {
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .error-message {
            background: #ffe6e6;
            color: #d63031;
            border: 1px solid #ffcccc;
        }

        .success-message {
            background: #e6ffe6;
            color: #27ae60;
            border: 1px solid #ccffcc;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #ffdcdc;
            outline: none;
        }

        input[type="number"].form-control {
            width: 100px;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 60px;
        }

        .options-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .option-item input[type="radio"] {
            width: 20px;
            height: 20px;
            margin: 0;
        }

        .option-item input[type="text"] {
            flex: 1;
        }

        select.form-control[name*="points"] {
            font-family: 'Tiny5', sans-serif;
            font-size: 1.1rem;
            padding: 0.6rem;
            background-color: #fff;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }

        select.form-control[name*="points"]:focus {
            border-color: #ffdcdc;
            outline: none;
            box-shadow: 0 0 0 2px rgba(255, 220, 220, 0.3);
        }

        select.form-control[name*="points"] option {
            font-family: 'Tiny5', sans-serif;
            padding: 0.5rem;
        }

        @media (max-width: 768px) {
            .quiz-creation-container {
                padding: 1rem;
                margin-top: 0.5rem;
            }

            .content-container {
                padding: 1.5rem;
            }

            .form-grid,
            .question-content {
                padding: 1.5rem;
            }

            .options-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>

    <div class="quiz-creation-container">
        <div class="content-container">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="page-title">
                <h1>Create Quiz</h1>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" class="quiz-form">
                <div class="form-section">
                    <div class="section-title">Quiz Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="quiz_title">Quiz Title</label>
                            <input type="text" id="quiz_title" name="quiz_title" placeholder="Enter quiz title" required>
                        </div>

                        <div class="form-group">
                            <label for="quiz_description">Description</label>
                            <textarea id="quiz_description" name="quiz_description" rows="3" placeholder="Enter quiz description"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Deadline</label>
                            <div class="deadline-inputs">
                                <input type="date" name="deadline_date" required>
                                <input type="time" name="deadline_time" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Quiz Image</label>
                            <input type="file" name="quiz_image" id="quiz_image" accept="image/*" style="display: none;">
                            <label for="quiz_image" class="file-label">Choose an image for your quiz</label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">Questions</div>
                    <div class="questions-container">
                        <div class="question-block">
                            <div class="question-header">Question 1</div>
                            <div class="question-content">
                                <div class="form-group">
                                    <label>Question Text</label>
                                    <textarea name="questions[0][text]" class="form-control" rows="3" required></textarea>
                                </div>

                                <input type="hidden" name="questions[0][points]" value="1">

                                <div class="form-group">
                                    <label>Question Image</label>
                                    <input type="file" name="question_images[]" id="question_image_0" class="file-input" accept="image/*" style="display: none;">
                                    <label for="question_image_0" class="file-label">Choose an image for this question</label>
                                </div>

                                <div class="options-container">
                                    <div class="option-item">
                                        <input type="radio" name="questions[0][correct_answer]" value="0" required>
                                        <input type="text" name="questions[0][options][]" placeholder="Option 1" required>
                                    </div>
                                    <div class="option-item">
                                        <input type="radio" name="questions[0][correct_answer]" value="1">
                                        <input type="text" name="questions[0][options][]" placeholder="Option 2" required>
                                    </div>
                                    <div class="option-item">
                                        <input type="radio" name="questions[0][correct_answer]" value="2">
                                        <input type="text" name="questions[0][options][]" placeholder="Option 3">
                                    </div>
                                    <div class="option-item">
                                        <input type="radio" name="questions[0][correct_answer]" value="3">
                                        <input type="text" name="questions[0][options][]" placeholder="Option 4">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="question-actions">
                        <button type="button" class="add-question-btn">Add Question</button>
                        <button type="button" class="remove-question-btn" style="display: none;">Remove Question</button>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="teacherdashboard.php" class="cancel-btn">Cancel</a>
                    <button type="submit" class="save-btn">Create Quiz</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded');
        });

        document.addEventListener('DOMContentLoaded', function() {
            const addQuestionBtn = document.querySelector('.add-question-btn');
            const removeQuestionBtn = document.querySelector('.remove-question-btn');
            const questionsContainer = document.querySelector('.questions-container');
            let questionCount = 1;

            // Function to update question numbers
            function updateQuestionNumbers() {
                const questions = questionsContainer.querySelectorAll('.question-block');
                questions.forEach((question, index) => {
                    const header = question.querySelector('.question-header');
                    header.textContent = `Question ${index + 1}`;
                    
                    // Update input names to maintain proper indexing
                    const questionText = question.querySelector('textarea[name^="questions"][name$="[text]"]');
                    const questionPoints = question.querySelector('input[name^="questions"][name$="[points]"]');
                    const questionImage = question.querySelector('input[name^="question_images"]');
                    const options = question.querySelectorAll('input[name^="questions"][name$="[options][]"]');
                    const radioButtons = question.querySelectorAll('input[name^="questions"][name$="[correct_answer]"]');
                    
                    questionText.name = `questions[${index}][text]`;
                    questionPoints.name = `questions[${index}][points]`;
                    if (questionImage) {
                        questionImage.name = `question_images[${index}]`;
                        questionImage.id = `question_image_${index}`;
                        const label = questionImage.nextElementSibling;
                        if (label) {
                            label.setAttribute('for', `question_image_${index}`);
                        }
                    }
                    
                    options.forEach(option => {
                        option.name = `questions[${index}][options][]`;
                    });
                    
                    radioButtons.forEach(radio => {
                        radio.name = `questions[${index}][correct_answer]`;
                    });
                });
                
                questionCount = questions.length;
                
                // Show/hide remove button based on question count
                if (questionCount > 1) {
                    removeQuestionBtn.style.display = 'block';
                } else {
                    removeQuestionBtn.style.display = 'none';
                }
            }

            // Handle removing questions
            removeQuestionBtn.addEventListener('click', function() {
                const questions = questionsContainer.querySelectorAll('.question-block');
                if (questions.length > 1) {
                    questions[questions.length - 1].remove();
                    updateQuestionNumbers();
                }
            });

            addQuestionBtn.addEventListener('click', function() {
                questionCount++;
                
                const questionBlock = document.createElement('div');
                questionBlock.className = 'question-block';
                questionBlock.innerHTML = `
                    <div class="question-header">Question ${questionCount}</div>
                    <div class="question-content">
                        <div class="form-group">
                            <label>Question Text</label>
                            <textarea name="questions[${questionCount-1}][text]" class="form-control" rows="3" required></textarea>
                        </div>

                        <input type="hidden" name="questions[${questionCount-1}][points]" value="1">
                        
                        <div class="form-group">
                            <label>Question Image</label>
                            <input type="file" name="question_images[]" id="question_image_${questionCount-1}" class="file-input" accept="image/*" style="display: none;">
                            <label for="question_image_${questionCount-1}" class="file-label">Choose an image for this question</label>
                        </div>

                        <div class="options-container">
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="0" required>
                                <input type="text" name="questions[${questionCount-1}][options][]" placeholder="Option 1" required>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="1">
                                <input type="text" name="questions[${questionCount-1}][options][]" placeholder="Option 2" required>
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="2">
                                <input type="text" name="questions[${questionCount-1}][options][]" placeholder="Option 3">
                            </div>
                            <div class="option-item">
                                <input type="radio" name="questions[${questionCount-1}][correct_answer]" value="3">
                                <input type="text" name="questions[${questionCount-1}][options][]" placeholder="Option 4">
                            </div>
                        </div>
                    </div>
                `;

                questionsContainer.appendChild(questionBlock);
                questionBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                updateQuestionNumbers();
            });

            document.addEventListener('change', function(e) {
                if (e.target.type === 'file') {
                    const file = e.target.files[0];
                    if (file) {
                        const label = e.target.nextElementSibling;
                        const originalLabelText = label.getAttribute('data-original-text') || 'Choose an image';
                        label.textContent = file.name;
                        label.classList.add('has-file');
                    }
                }
            });

            // Store original label text for all file inputs
            document.querySelectorAll('.file-label').forEach(label => {
                label.setAttribute('data-original-text', label.textContent);
            });

            let formChanged = false;

            // Function to check if any form field has content
            function checkFormContent() {
                const titleInput = document.querySelector('input[name="quiz_title"]');
                const descriptionInput = document.querySelector('textarea[name="quiz_description"]');
                const fileInputs = document.querySelectorAll('input[type="file"]');
                const textInputs = document.querySelectorAll('input[type="text"]');
                
                if (titleInput.value || descriptionInput.value) return true;
                
                for (const input of fileInputs) {
                    if (input.files.length > 0) return true;
                }
                
                for (const input of textInputs) {
                    if (input.value.trim() !== '') return true;
                }
                
                return false;
            }

            // Track form changes
            document.querySelector('form').addEventListener('input', function(e) {
                formChanged = true;
            });

            // Track file input changes
            document.addEventListener('change', function(e) {
                if (e.target.type === 'file') {
                    formChanged = true;
                }
            });

            // Form submission should clear the changed flag
            document.querySelector('form').addEventListener('submit', function() {
                formChanged = false;
            });

            // Show warning before leaving page
            window.addEventListener('beforeunload', function(e) {
                if (formChanged && checkFormContent()) {
                    e.preventDefault();
                    // Most browsers will show their own message
                    return e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
        });
    </script>
</body>
</html>
