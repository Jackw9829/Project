<?php
function renderQuizCard($quiz, $userType = 'student', $options = []) {
    global $pdo;
    
    // Get the base URL for the application
    $baseUrl = '';
    if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI'])) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $baseUrl .= "://" . $_SERVER['HTTP_HOST'];
        // If we're in a subdirectory, get the base path
        $baseUrl .= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    }
    
    // Check if quiz is active and not past due date
    $isActive = isset($quiz['is_active']) ? $quiz['is_active'] : 1;
    $isOverdue = isset($quiz['due_date']) && strtotime($quiz['due_date']) < time();
    $isActive = $isActive && !$isOverdue; // Quiz is only active if it's both marked active and not overdue
    
    $teacherName = isset($quiz['teacher_name']) ? $quiz['teacher_name'] : ($options['teacher_name'] ?? 'Unknown');
    $quizId = isset($quiz['quiz_id']) ? $quiz['quiz_id'] : (isset($quiz['quiz_identifier']) ? $quiz['quiz_identifier'] : null);
    
    // Add JavaScript function for grading
    static $jsAdded = false;
    if (!$jsAdded) {
        echo "<script>
            function gradeQuiz(quizId) {
                window.location.href = 'grade_quiz.php?quiz_id=' + encodeURIComponent(quizId);
            }
        </script>";
        $jsAdded = true;
    }
    
    // Check if quiz has questions
    $stmt = $pdo->prepare("SELECT COUNT(*) as question_count FROM questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $result = $stmt->fetch();
    $hasQuestions = $result['question_count'] > 0;
    
    // Get quiz statistics based on user type
    $quizStats = [];
    if ($userType === 'admin' || $userType === 'teacher') {
        // Use pre-fetched statistics if available
        if (isset($quiz['unique_attempts']) && isset($quiz['total_attempts']) && isset($quiz['average_score'])) {
            $quizStats = [
                'unique_attempts' => $quiz['unique_attempts'],
                'total_attempts' => $quiz['total_attempts'],
                'average_score' => $quiz['average_score'],
                'highest_score' => $quiz['highest_score'] ?? null
            ];
        } else {
            // Fallback to fetching statistics if not provided
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT user_id) as unique_attempts,
                    COUNT(*) as total_attempts,
                    AVG(score) as average_score,
                    MAX(score) as highest_score
                FROM quiz_attempts 
                WHERE quiz_id = ? AND score IS NOT NULL AND completed_at IS NOT NULL
            ");
            $stmt->execute([$quizId]);
            $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } elseif ($userType === 'student') {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as attempt_count,
                MAX(score) as highest_score,
                MAX(CASE WHEN score = (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = ? AND user_id = ? AND score IS NOT NULL AND completed_at IS NOT NULL) 
                    THEN attempted_at END) as best_attempt_date,
                (SELECT attempt_id FROM quiz_attempts 
                 WHERE quiz_id = ? AND user_id = ? AND score IS NOT NULL AND completed_at IS NOT NULL
                 ORDER BY attempted_at DESC LIMIT 1) as latest_attempt_id
            FROM quiz_attempts 
            WHERE quiz_id = ? AND user_id = ? AND score IS NOT NULL AND completed_at IS NOT NULL
        ");
        $stmt->execute([$quizId, $_SESSION['user_id'], $quizId, $_SESSION['user_id'], $quizId, $_SESSION['user_id']]);
        $quizStats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Format dates with time
    $createdAt = isset($quiz['created_at']) ? date('M j, Y g:i A', strtotime($quiz['created_at'])) : 'Not available';
    $dueDate = isset($quiz['due_date']) ? date('M j, Y g:i A', strtotime($quiz['due_date'])) : 'No due date';
    $isOverdue = isset($quiz['due_date']) && strtotime($quiz['due_date']) < time();
    ?>
    <div class="quiz-card<?php 
        echo !$hasQuestions ? ' no-questions' : ''; 
        echo $isOverdue ? ' overdue' : ''; 
        echo $userType === 'teacher' ? ' teacher-dashboard' : ''; 
    ?>">
        <div class="quiz-image-container">
            <?php 
            // Base64 encoded placeholder image (light gray background with quiz icon)
            $placeholderImage = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2YzZjRmNiIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjAiIGZpbGw9IiM5Y2EzYWYiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiPlF1aXo8L3RleHQ+PC9zdmc+';
        
            // Check if custom image exists, otherwise use placeholder
            $imagePath = !empty($quiz['image_path']) && file_exists($quiz['image_path']) ? 
                        htmlspecialchars($quiz['image_path']) : 
                        $placeholderImage;
            ?>
            <img src="<?php echo $imagePath; ?>" 
                 alt="<?php echo htmlspecialchars($quiz['title']); ?> thumbnail"
                 class="quiz-thumbnail"
                 onerror="this.onerror=null; this.src='<?php echo $placeholderImage; ?>';">
        </div>
        
        <div class="quiz-info">
            <div class="quiz-header">
                <?php if ($userType === 'teacher' || $userType === 'admin'): ?>
                    <div class="status-badge <?php echo $isActive ? 'status-active' : 'status-inactive'; ?>" 
                         role="status" 
                         aria-label="Quiz status: <?php echo $isActive ? 'Active' : 'Inactive'; ?>">
                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                    </div>
                <?php endif; ?>
                
                <div class="quiz-title-row">
                    <h3 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                    <?php if ($userType === 'admin'): ?>
                        <span class="quiz-id">ID: <?php echo htmlspecialchars($quizId); ?></span>
                    <?php endif; ?>
                </div>
                <p class="quiz-author">By <?php echo htmlspecialchars($teacherName); ?></p>
                <p class="quiz-description"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></p>
            </div>

            <div class="quiz-meta">
                <div class="meta-item">
                    <span class="meta-label">Created:</span>
                    <span class="meta-value"><?php echo $createdAt; ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Due:</span>
                    <span class="meta-value <?php echo $isOverdue ? 'overdue' : ''; ?>">
                        <?php echo $dueDate; ?>
                    </span>
                </div>
                <?php if ($userType === 'student' && isset($quiz['submission_date'])): ?>
                    <div class="meta-item">
                        <span class="meta-label">Date Submitted:</span>
                        <span class="meta-value"><?php echo date('M j, Y g:i A', strtotime($quiz['submission_date'])); ?></span>
                    </div>
                    <?php if (isset($quiz['highest_score'])): ?>
                        <div class="meta-item">
                            <span class="meta-label">Points:</span>
                            <span class="meta-value"><?php echo number_format($quiz['highest_score']); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="quiz-actions">
                <?php if ($userType === 'teacher'): ?>
                    <a href="<?php echo $baseUrl; ?>/preview_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>" 
                       class="btn view-btn"
                       role="button"
                       aria-label="View quiz: <?php echo htmlspecialchars($quiz['title']); ?>">View</a>
                    <a href="<?php echo $baseUrl; ?>/edit_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>" 
                       class="btn edit-btn"
                       role="button"
                       aria-label="Edit quiz: <?php echo htmlspecialchars($quiz['title']); ?>">Edit</a>
                    <a href="<?php echo $baseUrl; ?>/teacher_grade_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>" 
                       class="btn grade-btn"
                       role="button"
                       aria-label="Grade quiz: <?php echo htmlspecialchars($quiz['title']); ?>">Grade</a>
                    <button onclick="confirmDelete(<?php echo htmlspecialchars($quizId); ?>, '<?php echo htmlspecialchars(addslashes($quiz['title'])); ?>')" 
                            class="btn delete-btn"
                            role="button"
                            aria-label="Delete quiz: <?php echo htmlspecialchars($quiz['title']); ?>">Delete</button>
                <?php elseif ($userType === 'admin'): ?>
                    <a href="<?php echo $baseUrl; ?>/preview_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>" 
                       class="btn view-btn"
                       role="button"
                       aria-label="View quiz: <?php echo htmlspecialchars($quiz['title']); ?>">View</a>
                    <a href="<?php echo $baseUrl; ?>/teacher_grade_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>" 
                       class="btn grade-btn"
                       role="button"
                       aria-label="Grade quiz: <?php echo htmlspecialchars($quiz['title']); ?>">Grade</a>
                <?php elseif ($userType === 'student'): ?>
                    <?php if ($hasQuestions): ?>
                        <?php if ($quiz['can_attempt']): ?>
                            <a href="<?php echo $baseUrl; ?>/start_quiz.php?id=<?php echo htmlspecialchars($quizId); ?>" 
                               class="btn start-quiz-btn<?php echo $isOverdue ? ' disabled' : ''; ?>"
                               role="button"
                               <?php echo $isOverdue ? 'disabled' : ''; ?>
                               aria-label="Start quiz: <?php echo htmlspecialchars($quiz['title']); ?>">
                                <?php echo $isOverdue ? 'Quiz Closed' : 'Start Quiz'; ?>
                            </a>
                        <?php else: ?>
                            <?php if ($isOverdue): ?>
                                <button class="btn start-quiz-btn" disabled>Quiz Closed</button>
                            <?php elseif (isset($quiz['submission_date'])): ?>
                                <button class="btn start-quiz-btn" disabled>Quiz Completed</button>
                            <?php else: ?>
                                <button class="btn start-quiz-btn" disabled>Quiz Not Available</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($quiz['can_review']): ?>
                            <a href="<?php echo $baseUrl; ?>/preview_quiz.php?quiz_id=<?php echo htmlspecialchars($quizId); ?>&attempt_id=<?php echo htmlspecialchars($quiz['latest_attempt_id']); ?>" 
                               class="btn review-btn"
                               role="button"
                               aria-label="Review attempt for: <?php echo htmlspecialchars($quiz['title']); ?>">
                                Review Attempt
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn start-quiz-btn" disabled>Quiz Not Ready</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <style>
        .quiz-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-family: 'Tiny5', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #333;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            color: #333;
            text-decoration: none;
        }

        .btn.view-btn {
            background-color: #ded6ce;
        }
        .btn.view-btn:hover {
            background-color: #cec6be;
        }

        .btn.edit-btn {
            background-color: #fdf3d8;
        }
        .btn.edit-btn:hover {
            background-color: #ede3c8;
        }

        .btn.grade-btn {
            background-color: #e2f8e8;
        }
        .btn.grade-btn:hover {
            background-color: #d2e8d8;
        }

        .btn.feedback-btn {
            background-color: #ddedfd;
        }
        .btn.feedback-btn:hover {
            background-color: #cddded;
        }

        .btn.delete-btn {
            background-color: #ffdcdc;
        }
        .btn.delete-btn:hover {
            background-color: #efcccc;
        }
        
        .quiz-meta {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .quiz-meta .meta-item {
            margin: 0.25rem 0;
        }

        .quiz-meta .meta-label {
            font-weight: bold;
        }

        .quiz-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .quiz-info {
            display: flex;
            justify-content: space-between;
        }
        
        .quiz-content {
            flex: 1;
        }
        
        .quiz-sidebar {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        
        .teacher-dashboard {
            background-color: #f7f7f7;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
    <?php
}
?>
