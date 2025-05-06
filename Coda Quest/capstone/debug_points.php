<?php
// Start session and include necessary files
session_start();
require_once 'config/db_connect.php';

// Only allow access to admins
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Process student ID if provided
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$debug_output = [];
$success_message = '';
$error_message = '';

// Function to get all students for dropdown
function getAllStudents() {
    $sql = "SELECT student_id, username, full_name, total_points FROM students ORDER BY username";
    $result = executeQuery($sql);
    return is_array($result) ? $result : [];
}

// Function to get detailed quiz points
function getQuizPoints($student_id) {
    $sql = "SELECT qa.attempt_id, q.title, q.quiz_id, 
                  SUM(qan.points_earned) as answer_points_earned,
                  qa.score, qa.start_time, qa.end_time, qa.points_awarded,
                  (SELECT SUM(points) FROM quiz_questions WHERE quiz_id = q.quiz_id) as max_possible_points
           FROM quiz_attempts qa
           JOIN quizzes q ON qa.quiz_id = q.quiz_id
           LEFT JOIN quiz_answers qan ON qa.attempt_id = qan.attempt_id
           WHERE qa.student_id = ? AND qa.is_completed = 1
           GROUP BY qa.attempt_id
           ORDER BY qa.end_time DESC";
    
    $result = executeQuery($sql, [$student_id]);
    return is_array($result) ? $result : [];
}

// Function to get detailed challenge points
function getChallengePoints($student_id) {
    $sql = "SELECT ca.attempt_id, c.challenge_name, c.challenge_id,
                  SUM(can.points_earned) as answer_points_earned,
                  ca.score, ca.start_time, ca.end_time, ca.points_awarded,
                  (SELECT SUM(points) FROM challenge_questions WHERE challenge_id = c.challenge_id) as max_possible_points
           FROM challenge_attempts ca
           JOIN challenges c ON ca.challenge_id = c.challenge_id
           LEFT JOIN challenge_answers can ON ca.attempt_id = can.attempt_id
           WHERE ca.student_id = ? AND ca.is_completed = 1
           GROUP BY ca.attempt_id
           ORDER BY ca.end_time DESC";
    
    $result = executeQuery($sql, [$student_id]);
    return is_array($result) ? $result : [];
}

// Function to get achievement points - hardcoded since user_achievements table is not used
function getAchievementPoints($student_id) {
    // Since achievements are hardcoded, we'll return an empty array
    // The actual achievement points should be included in the student's total_points
    return [];
}

// Function to calculate total points from different sources
function calculateTotalPoints($student_id) {
    // Get student base points
    $sql = "SELECT total_points FROM students WHERE student_id = ?";
    $result = executeQuery($sql, [$student_id]);
    $result = is_array($result) ? $result : [];
    $base_points = $result[0]['total_points'] ?? 0;
    
    // Calculate quiz points
    $quiz_points = 0;
    $quiz_results = getQuizPoints($student_id);
    foreach ($quiz_results as $quiz) {
        // Check if points_awarded flag is set
        if (isset($quiz['points_awarded']) && $quiz['points_awarded'] == 1) {
            // If we have answer_points_earned, use that
            if (isset($quiz['answer_points_earned']) && $quiz['answer_points_earned'] > 0) {
                $quiz_points += intval($quiz['answer_points_earned']);
            } 
            // Otherwise use the score directly as points
            elseif (isset($quiz['score'])) {
                $quiz_points += intval($quiz['score']);
            }
        }
    }
    
    // Calculate challenge points
    $challenge_points = 0;
    $challenge_results = getChallengePoints($student_id);
    foreach ($challenge_results as $challenge) {
        // Check if points_awarded flag is set
        if (isset($challenge['points_awarded']) && $challenge['points_awarded'] == 1) {
            // If we have answer_points_earned, use that
            if (isset($challenge['answer_points_earned']) && $challenge['answer_points_earned'] > 0) {
                $challenge_points += intval($challenge['answer_points_earned']);
            } 
            // Otherwise use the score directly as points
            elseif (isset($challenge['score'])) {
                $challenge_points += intval($challenge['score']);
            }
            
            // Add 20 points for completing a challenge (as per take_challenge.php)
            $challenge_points += 20;
        }
    }
    
    // Calculate achievement points
    $achievement_points = 0;
    $achievement_results = getAchievementPoints($student_id);
    foreach ($achievement_results as $achievement) {
        $achievement_points += intval($achievement['points'] ?? 0);
    }
    
    // Calculate total from components
    $calculated_total = $quiz_points + $challenge_points + $achievement_points;
    
    // Base points might be redundant if they're already included in the other categories
    // Only add base_points if they represent something separate from the other categories
    // For now, we'll exclude them to avoid double-counting
    
    // Check leaderboard total
    $sql = "SELECT total_points, total_quizzes_completed, total_challenges_completed FROM leaderboard WHERE student_id = ?";
    $result = executeQuery($sql, [$student_id]);
    $result = is_array($result) ? $result : [];
    $leaderboard_points = $result[0]['total_points'] ?? 0;
    $total_quizzes = $result[0]['total_quizzes_completed'] ?? 0;
    $total_challenges = $result[0]['total_challenges_completed'] ?? 0;
    
    // Count completed quizzes and challenges
    $sql = "SELECT COUNT(DISTINCT attempt_id) as quiz_count FROM quiz_attempts WHERE student_id = ? AND is_completed = 1";
    $quiz_count_result = executeQuery($sql, [$student_id]);
    $quiz_count_result = is_array($quiz_count_result) ? $quiz_count_result : [];
    $quiz_count = $quiz_count_result[0]['quiz_count'] ?? 0;
    
    $sql = "SELECT COUNT(DISTINCT attempt_id) as challenge_count FROM challenge_attempts WHERE student_id = ? AND is_completed = 1";
    $challenge_count_result = executeQuery($sql, [$student_id]);
    $challenge_count_result = is_array($challenge_count_result) ? $challenge_count_result : [];
    $challenge_count = $challenge_count_result[0]['challenge_count'] ?? 0;
    
    return [
        'base_points' => $base_points,
        'quiz_points' => $quiz_points,
        'challenge_points' => $challenge_points,
        'achievement_points' => $achievement_points,
        'calculated_total' => $calculated_total,
        'leaderboard_total' => $leaderboard_points,
        'quiz_count' => $quiz_count,
        'challenge_count' => $challenge_count,
        'leaderboard_quiz_count' => $total_quizzes,
        'leaderboard_challenge_count' => $total_challenges
    ];
}

// Function to fix point discrepancies
function fixPointDiscrepancy($student_id, $point_summary) {
    // First, update the student's total_points in the students table
    $sql = "UPDATE students SET 
            total_points = ?
            WHERE student_id = ?";
    
    $params = [
        $point_summary['calculated_total'],
        $student_id
    ];
    
    executeQuery($sql, $params);
    
    // Then update the leaderboard entry
    $sql = "UPDATE leaderboard SET 
            total_points = ?, 
            total_quizzes_completed = ?,
            total_challenges_completed = ?,
            last_updated = NOW()
            WHERE student_id = ?";
    
    $params = [
        $point_summary['calculated_total'],
        $point_summary['quiz_count'],
        $point_summary['challenge_count'],
        $student_id
    ];
    
    $result = executeQuery($sql, $params);
    
    // If there's no leaderboard entry, create one
    if (!$result) {
        $sql = "INSERT INTO leaderboard (student_id, total_points, total_quizzes_completed, total_challenges_completed, last_updated)
                VALUES (?, ?, ?, ?, NOW())";
        $result = executeQuery($sql, $params);
    }
    
    // Log the point correction in the activity_log table
    $sql = "INSERT INTO activity_log (student_id, activity_type, description, points_earned, created_at)
            VALUES (?, 'point_correction', 'Point calculation corrected by admin', ?, NOW())";
    
    executeQuery($sql, [$student_id, 0]);
    
    return $result;
}

// Process point fix if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_points']) && isset($_POST['student_id'])) {
    $student_id = (int)$_POST['student_id'];
    $points_summary = calculateTotalPoints($student_id);
    
    if (fixPointDiscrepancy($student_id, $points_summary)) {
        $success_message = "Points updated successfully for student ID: $student_id";
        
        // Log the fix
        $activity_type = "admin_action";
        $activity_details = "Admin fixed point discrepancy for student ID: $student_id";
        $admin_id = $_SESSION['admin_id'];
        $sql = "INSERT INTO activity_log (student_id, activity_type, details) VALUES (?, ?, ?)";
        executeQuery($sql, [$student_id, $activity_type, $activity_details]);
    } else {
        $error_message = "Failed to update points for student ID: $student_id";
    }
}

// Process student if specified
if ($student_id) {
    $debug_output['student_info'] = executeQuery("SELECT * FROM students WHERE student_id = ?", [$student_id])[0] ?? null;
    
    if ($debug_output['student_info']) {
        $debug_output['quiz_points'] = getQuizPoints($student_id);
        $debug_output['challenge_points'] = getChallengePoints($student_id);
        $debug_output['achievement_points'] = getAchievementPoints($student_id);
        $debug_output['point_summary'] = calculateTotalPoints($student_id);
    }
}

// Get all students for the dropdown
$students = getAllStudents();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point Calculation Debug</title>
    <!-- Include the common styles -->
    <?php include 'common_styles.php'; ?>
    <style>
        .debug-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .debug-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .debug-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .debug-table th, .debug-table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .debug-table th {
            background-color: var(--header-bg);
            color: var(--header-text);
        }
        .discrepancy {
            color: #ff5252;
            font-weight: bold;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .summary-item {
            background: var(--bg-accent);
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .summary-item h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--text-muted);
        }
        .summary-item p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .mt-3 {
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            border: none;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="debug-container">
        <div class="debug-card">
            <h1>Point Calculation Debug</h1>
            <p>Select a student to analyze point calculations across quizzes, challenges, and achievements.</p>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <form method="get">
                <div class="form-group">
                    <select name="student_id" class="form-control" onchange="this.form.submit()">
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['student_id'] ?>" <?= $student_id == $student['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['username']) ?> (<?= htmlspecialchars($student['full_name']) ?>) - <?= $student['total_points'] ?> pts
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <?php if ($student_id && isset($debug_output['student_info'])): ?>
            <div class="debug-card">
                <h2>Student Information</h2>
                <p>
                    <strong>ID:</strong> <?= $debug_output['student_info']['student_id'] ?><br>
                    <strong>Username:</strong> <?= htmlspecialchars($debug_output['student_info']['username']) ?><br>
                    <strong>Name:</strong> <?= htmlspecialchars($debug_output['student_info']['full_name']) ?><br>
                    <strong>Total Points:</strong> <?= $debug_output['student_info']['total_points'] ?><br>
                    <strong>Current Level:</strong> <?= $debug_output['student_info']['current_level'] ?><br>
                    <strong>Registered:</strong> <?= $debug_output['student_info']['date_registered'] ?><br>
                </p>
                
                <h2>Points Summary</h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <h3>Base Points</h3>
                        <p><?= $debug_output['point_summary']['base_points'] ?></p>
                        <small>(Not included in total to avoid double-counting)</small>
                    </div>
                    <div class="summary-item">
                        <h3>Quiz Points</h3>
                        <p><?= $debug_output['point_summary']['quiz_points'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Challenge Points</h3>
                        <p><?= $debug_output['point_summary']['challenge_points'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Achievement Points</h3>
                        <p><?= $debug_output['point_summary']['achievement_points'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Calculated Total</h3>
                        <p><?= $debug_output['point_summary']['calculated_total'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Leaderboard Points</h3>
                        <p class="<?= $debug_output['point_summary']['calculated_total'] != $debug_output['point_summary']['leaderboard_total'] ? 'discrepancy' : '' ?>">
                            <?= $debug_output['point_summary']['leaderboard_total'] ?>
                        </p>
                    </div>
                </div>
                
                <div class="summary-grid mt-3">
                    <div class="summary-item">
                        <h3>Quizzes Completed</h3>
                        <p><?= $debug_output['point_summary']['quiz_count'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Challenges Completed</h3>
                        <p><?= $debug_output['point_summary']['challenge_count'] ?></p>
                    </div>
                    <div class="summary-item">
                        <h3>Leaderboard Quizzes</h3>
                        <p class="<?= $debug_output['point_summary']['quiz_count'] != $debug_output['point_summary']['leaderboard_quiz_count'] ? 'discrepancy' : '' ?>">
                            <?= $debug_output['point_summary']['leaderboard_quiz_count'] ?>
                        </p>
                    </div>
                    <div class="summary-item">
                        <h3>Leaderboard Challenges</h3>
                        <p class="<?= $debug_output['point_summary']['challenge_count'] != $debug_output['point_summary']['leaderboard_challenge_count'] ? 'discrepancy' : '' ?>">
                            <?= $debug_output['point_summary']['leaderboard_challenge_count'] ?>
                        </p>
                    </div>
                </div>
                
                <?php 
                $has_discrepancy = 
                    $debug_output['point_summary']['calculated_total'] != $debug_output['point_summary']['leaderboard_total'] ||
                    $debug_output['point_summary']['quiz_count'] != $debug_output['point_summary']['leaderboard_quiz_count'] ||
                    $debug_output['point_summary']['challenge_count'] != $debug_output['point_summary']['leaderboard_challenge_count'];
                
                if ($has_discrepancy): 
                ?>
                    <div class="alert alert-danger mt-3">
                        <strong>Discrepancy Detected:</strong> The calculated values do not match the leaderboard values.
                    </div>
                    
                    <!-- Fix button if discrepancy found -->
                    <form method="post" class="mt-3">
                        <input type="hidden" name="student_id" value="<?= $student_id ?>">
                        <input type="hidden" name="fix_points" value="1">
                        <button type="submit" class="btn btn-warning">Fix Point Discrepancies</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="debug-card">
                <h2>Quiz Points (<?= count($debug_output['quiz_points']) ?>)</h2>
                <?php if (empty($debug_output['quiz_points'])): ?>
                    <p>No quiz attempts found</p>
                <?php else: ?>
                    <table class="debug-table">
                        <thead>
                            <tr>
                                <th>Attempt ID</th>
                                <th>Quiz</th>
                                <th>Points Earned</th>
                                <th>Score</th>
                                <th>Points Awarded?</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_output['quiz_points'] as $quiz): ?>
                                <tr>
                                    <td><?= $quiz['attempt_id'] ?></td>
                                    <td><?= htmlspecialchars($quiz['title']) ?></td>
                                    <td><?= $quiz['answer_points_earned'] ?? 0 ?></td>
                                    <td><?= $quiz['score'] ?></td>
                                    <td><?= $quiz['points_awarded'] ? 'Yes' : 'No' ?></td>
                                    <td><?= $quiz['end_time'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="debug-card">
                <h2>Challenge Points (<?= count($debug_output['challenge_points']) ?>)</h2>
                <?php if (empty($debug_output['challenge_points'])): ?>
                    <p>No challenge attempts found</p>
                <?php else: ?>
                    <table class="debug-table">
                        <thead>
                            <tr>
                                <th>Attempt ID</th>
                                <th>Challenge</th>
                                <th>Points Earned</th>
                                <th>Score</th>
                                <th>Points Awarded?</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_output['challenge_points'] as $challenge): ?>
                                <tr>
                                    <td><?= $challenge['attempt_id'] ?></td>
                                    <td><?= htmlspecialchars($challenge['challenge_name']) ?></td>
                                    <td><?= $challenge['answer_points_earned'] ?? 0 ?></td>
                                    <td><?= $challenge['score'] ?></td>
                                    <td><?= $challenge['points_awarded'] ? 'Yes' : 'No' ?></td>
                                    <td><?= $challenge['end_time'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="debug-card">
                <h2>Achievement Points (<?= is_array($debug_output['achievement_points']) ? count($debug_output['achievement_points']) : 0 ?>)</h2>
                <?php if (empty($debug_output['achievement_points']) || !is_array($debug_output['achievement_points'])): ?>
                    <p>No achievements found</p>
                <?php else: ?>
                    <table class="debug-table">
                        <thead>
                            <tr>
                                <th>Achievement</th>
                                <th>Description</th>
                                <th>Points</th>
                                <th>Earned At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debug_output['achievement_points'] as $achievement): ?>
                                <tr>
                                    <td><?= htmlspecialchars($achievement['title']) ?></td>
                                    <td><?= htmlspecialchars($achievement['description']) ?></td>
                                    <td><?= $achievement['points'] ?></td>
                                    <td><?= $achievement['earned_at'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php elseif ($student_id): ?>
            <div class="debug-card">
                <div class="alert alert-danger">
                    <strong>Error:</strong> Student not found.
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    <?php include_once 'includes/music_player.php'; ?>
</body>
</html> 