<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

// Check if quiz_id is provided
if (!isset($_GET['quiz_id'])) {
    header('Location: teacherdashboard.php');
    exit();
}

$quiz_id = $_GET['quiz_id'];
$question_id = isset($_GET['question_id']) ? $_GET['question_id'] : 'new';
$error = '';
$success = '';

try {
    // Verify quiz belongs to teacher
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND teacher_id = ?");
    $stmt->execute([$quiz_id, $_SESSION['user_id']]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        $_SESSION['error'] = "Quiz not found or you don't have permission to edit it.";
        header('Location: teacherdashboard.php');
        exit();
    }

    // If editing existing question, fetch its details
    $question = null;
    $options = [];
    if ($question_id !== 'new') {
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE question_id = ? AND quiz_id = ?");
        $stmt->execute([$question_id, $quiz_id]);
        $question = $stmt->fetch();

        if ($question) {
            $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ?");
            $stmt->execute([$question_id]);
            $options = $stmt->fetchAll();
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->beginTransaction();

        try {
            $question_text = $_POST['question_text'];
            $options_text = $_POST['options'] ?? [];
            $correct_option = $_POST['correct_option'] ?? null;
            $current_image = isset($question) ? $question['image_path'] : '';
            $image_path = $current_image;

            // Handle image upload
            if (isset($_FILES['question_image']) && $_FILES['question_image']['size'] > 0) {
                $target_dir = "uploads/questions/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_extension = strtolower(pathinfo($_FILES["question_image"]["name"], PATHINFO_EXTENSION));
                $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
                
                if (!in_array($file_extension, $allowed_types)) {
                    throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed.");
                }

                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;

                if (move_uploaded_file($_FILES["question_image"]["tmp_name"], $target_file)) {
                    // Delete old image if exists
                    if (!empty($current_image) && file_exists($current_image)) {
                        unlink($current_image);
                    }
                    $image_path = $target_file;
                } else {
                    throw new Exception("Sorry, there was an error uploading your file.");
                }
            } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == 1) {
                // Remove existing image if requested
                if (!empty($current_image) && file_exists($current_image)) {
                    unlink($current_image);
                }
                $image_path = '';
            }

            // Debug output
            error_log("Processing question submission:");
            error_log("Question Text: " . $question_text);
            error_log("Options: " . print_r($options_text, true));
            error_log("Correct Option: " . $correct_option);

            if (empty($question_text)) {
                throw new Exception("Question text is required.");
            }

            if (count($options_text) < 2) {
                throw new Exception("At least two options are required.");
            }

            if ($correct_option === null || !isset($options_text[$correct_option])) {
                throw new Exception("Please select the correct answer.");
            }

            // Insert or update question
            if ($question_id === 'new') {
                // Get the next question order number
                $stmt = $pdo->prepare("
                    SELECT COALESCE(MAX(question_order), 0) + 1 as next_order 
                    FROM questions 
                    WHERE quiz_id = ?
                ");
                $stmt->execute([$quiz_id]);
                $next_order = $stmt->fetch()['next_order'];

                $stmt = $pdo->prepare("
                    INSERT INTO questions (quiz_id, question_text, points, image_path, question_order) 
                    VALUES (?, ?, 1, ?, ?)
                ");
                $stmt->execute([$quiz_id, $question_text, $image_path, $next_order]);
                $question_id = $pdo->lastInsertId();
                error_log("New question created with ID: " . $question_id);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE questions 
                    SET question_text = ?, image_path = ?
                    WHERE question_id = ? AND quiz_id = ?
                ");
                $stmt->execute([$question_text, $image_path, $question_id, $quiz_id]);
                error_log("Updated existing question ID: " . $question_id);
            }

            // Delete existing options if updating
            if ($question_id !== 'new') {
                $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                $stmt->execute([$question_id]);
                error_log("Deleted existing options for question ID: " . $question_id);
            }

            // Insert options
            $stmt = $pdo->prepare("
                INSERT INTO question_options (question_id, option_text, is_correct)
                VALUES (?, ?, ?)
            ");

            foreach ($options_text as $index => $option_text) {
                if (!empty($option_text)) {
                    $is_correct = ($index == $correct_option) ? 1 : 0;
                    $stmt->execute([$question_id, $option_text, $is_correct]);
                    error_log("Inserted option for question $question_id: $option_text (Correct: $is_correct)");
                }
            }

            $pdo->commit();
            error_log("Question saved successfully!");
            $success = "Question saved successfully!";
            
            // Verify the question was saved
            $verify_stmt = $pdo->prepare("
                SELECT q.*, qo.option_text, qo.is_correct
                FROM questions q
                LEFT JOIN question_options qo ON q.question_id = qo.question_id
                WHERE q.question_id = ?
            ");
            $verify_stmt->execute([$question_id]);
            $verification = $verify_stmt->fetchAll();
            error_log("Verification of saved question: " . print_r($verification, true));
            
            // Redirect back to edit quiz page after successful save
            header("Location: edit_quiz.php?quiz_id=" . $quiz_id);
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - <?php echo $question_id === 'new' ? 'Add' : 'Edit'; ?> Question</title>
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
            padding-top: 60px;
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

        .dashboard-container {
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
            margin-bottom: 1.8rem;
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
        }

        .question-content {
            padding: 2rem;
        }

        .options-container {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.8rem;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.8rem;
            transition: all 0.2s ease;
        }

        .option-item:hover {
            background: #f0f2f5;
        }

        .option-item input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
        }

        .option-item input[type="text"] {
            flex: 1;
            padding: 0.8rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 1rem;
            background: white;
        }

        .option-item input[type="text"]:focus {
            outline: none;
            border-color: #ffdcdc;
            box-shadow: 0 0 0 3px rgba(255, 220, 220, 0.2);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: flex-end;
        }

        .save-btn, .cancel-btn {
            padding: 0.8rem 2rem;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 120px;
            font-weight: 500;
            border: none;
            transition: all 0.2s ease;
            font-family: 'Tiny5', sans-serif;
        }

        .save-btn {
            background: #ffdcdc;
            color: #2c3e50;
        }

        .save-btn:hover {
            background: #ffcece;
            transform: translateY(-1px);
        }

        .cancel-btn {
            background: #e9ecef;
            color: #2c3e50;
        }

        .cancel-btn:hover {
            background: #dee2e6;
            transform: translateY(-1px);
        }

        .error-message {
            background-color: #ffe0e3;
            color: #e74c3c;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #e74c3c;
        }

        .success-message {
            background-color: #d4edda;
            color: #28a745;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #28a745;
        }

        .current-image {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .current-image img {
            max-width: 200px;
            border-radius: 4px;
        }

        .image-upload-box {
            border: 2px dashed #ccc;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            background: #f9f9f9;
        }

        .image-upload-box .current-image {
            margin-bottom: 15px;
            text-align: center;
        }

        .image-upload-box .current-image img {
            max-width: 200px;
            margin-bottom: 10px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .image-upload-box .image-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }

        .image-upload-box .file-input-wrapper {
            margin-top: 10px;
        }

        .image-upload-box .remove-image-option {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 10px 0;
        }

        .image-upload-box small {
            display: block;
            color: #666;
            margin-top: 5px;
            text-align: center;
        }

        .custom-file-input {
            position: relative;
            display: inline-block;
            width: 100%;
            text-align: center;
        }

        .custom-file-input input[type="file"] {
            display: none;
        }

        .custom-file-input label {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .custom-file-input label:hover {
            background-color: #45a049;
        }

        .custom-file-input .file-name {
            margin-top: 5px;
            font-size: 0.9em;
            color: #666;
        }

        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .custom-checkbox input[type="checkbox"] {
            display: none;
        }

        .custom-checkbox .checkbox-icon {
            width: 20px;
            height: 20px;
            border: 2px solid #4CAF50;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            background-color: white;
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon {
            background-color: #4CAF50;
            border-color: #4CAF50;
        }

        .custom-checkbox input[type="checkbox"]:checked + .checkbox-icon:after {
            content: '✓';
            color: white;
            font-size: 14px;
        }

        .custom-checkbox:hover .checkbox-icon {
            border-color: #45a049;
        }

        .custom-checkbox label {
            color: #333;
            font-size: 0.95em;
        }

        .image-controls {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }

            .content-container {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .save-btn, .cancel-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div id="particles-js"></div>
    <div class="dashboard-container">
        <div class="content-container">
            <div class="page-title">
                <h1><?php echo $question_id === 'new' ? 'Add New Question' : 'Edit Question'; ?></h1>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="question-block">
                    <div class="question-header">
                        Question Details
                    </div>
                    <div class="question-content">
                        <div class="form-group">
                            <label>Question Text</label>
                            <textarea 
                                name="question_text" 
                                rows="3" 
                                required
                                placeholder="Enter your question here..."
                            ><?php echo isset($question) ? htmlspecialchars($question['question_text']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_image">Question Image:</label>
                            <div class="image-upload-box">
                                <?php if (isset($question) && !empty($question['image_path'])): ?>
                                    <div class="current-image">
                                        <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="Current question image">
                                        <div class="image-controls">
                                            <label class="custom-checkbox">
                                                <input type="checkbox" id="remove_image" name="remove_image" value="1">
                                                <span class="checkbox-icon"></span>
                                                <span>Remove current image</span>
                                            </label>
                                            <div class="custom-file-input">
                                                <input type="file" id="question_image" name="question_image" accept="image/*" onchange="updateFileName(this)">
                                                <label for="question_image">Choose a new image</label>
                                                <div class="file-name"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="image-controls">
                                        <div class="custom-file-input">
                                            <input type="file" id="question_image" name="question_image" accept="image/*" onchange="updateFileName(this)">
                                            <label for="question_image">Choose an image</label>
                                            <div class="file-name"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <small>Allowed file types: JPG, JPEG, PNG, GIF</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Answer Options</label>
                            <div class="options-container">
                                <?php 
                                $numOptions = max(4, count($options));
                                for ($i = 0; $i < $numOptions; $i++): 
                                    $option = isset($options[$i]) ? $options[$i] : null;
                                ?>
                                    <div class="option-item">
                                        <input type="radio" 
                                               name="correct_option" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $option && $option['is_correct'] ? 'checked' : ''; ?>
                                               required>
                                        <input type="text" 
                                               name="options[]" 
                                               placeholder="Option <?php echo $i + 1; ?>"
                                               value="<?php echo $option ? htmlspecialchars($option['option_text']) : ''; ?>"
                                               required>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="edit_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="cancel-btn">Cancel</a>
                    <button type="submit" class="save-btn">Save Question</button>
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

        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : '';
            const fileNameDiv = input.closest('.custom-file-input').querySelector('.file-name');
            fileNameDiv.textContent = fileName;
        }
    </script>
</body>
</html>