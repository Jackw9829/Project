<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'config/db_connect.php';

// Debug: Check for duplicate entries in the leaderboard table
$checkDuplicatesSql = "SELECT student_id, COUNT(*) as count 
                       FROM leaderboard 
                       GROUP BY student_id 
                       HAVING COUNT(*) > 1";
$duplicateEntries = executeQuery($checkDuplicatesSql, []);

if (!empty($duplicateEntries)) {
    error_log("WARNING: Found duplicate student entries in leaderboard table: " . print_r($duplicateEntries, true));
    
    // Optional: Auto-fix duplicates by removing extras
    foreach ($duplicateEntries as $duplicate) {
        $studentId = $duplicate['student_id'];
        error_log("Cleaning up duplicate entries for student_id: " . $studentId);
        
        // Get all leaderboard entries for this student
        $entriesSql = "SELECT leaderboard_id FROM leaderboard WHERE student_id = ? ORDER BY total_points DESC";
        $entries = executeQuery($entriesSql, [$studentId]);
        
        // Keep the first one (highest points), delete the rest
        if (count($entries) > 1) {
            // Skip the first entry (index 0)
            for ($i = 1; $i < count($entries); $i++) {
                $deleteId = $entries[$i]['leaderboard_id'];
                error_log("Deleting duplicate leaderboard entry ID: " . $deleteId);
                $deleteSql = "DELETE FROM leaderboard WHERE leaderboard_id = ?";
                executeQuery($deleteSql, [$deleteId]);
            }
        }
    }
}

// Helper function to update the leaderboard table
function updateUserLeaderboardEntry($studentId) {
    // Get user's statistics
    $userStats = getUserStats($studentId);
    
    // Log the statistics for debugging
    error_log("Updating leaderboard for student $studentId: Points={$userStats['total_points']}, Quizzes={$userStats['completed_quizzes']}, Challenges={$userStats['completed_challenges']}");
    
    // Check if user already has a leaderboard entry
    $checkSql = "SELECT leaderboard_id FROM leaderboard WHERE student_id = ?";
    $existingEntry = executeQuery($checkSql, [$studentId]);
    
    if (count($existingEntry) > 0) {
        // Update existing entry
        $updateSql = "UPDATE leaderboard 
                      SET total_points = ?, 
                          total_quizzes_completed = ?, 
                          total_challenges_completed = ?,
                          last_updated = NOW() 
                      WHERE student_id = ?";
        $params = [
            $userStats['total_points'],
            $userStats['completed_quizzes'],
            $userStats['completed_challenges'],
            $studentId
        ];
        executeQuery($updateSql, $params);
    } else {
        // Create new entry
        $insertSql = "INSERT INTO leaderboard (student_id, total_points, total_quizzes_completed, total_challenges_completed) 
                      VALUES (?, ?, ?, ?)";
        $params = [
            $studentId,
            $userStats['total_points'],
            $userStats['completed_quizzes'],
            $userStats['completed_challenges']
        ];
        executeQuery($insertSql, $params);
    }
    
    // Force direct update of quiz and challenge counts in the database
    $directUpdateSql = "UPDATE leaderboard l SET 
                        l.total_quizzes_completed = (SELECT COUNT(DISTINCT qa.quiz_id) FROM quiz_attempts qa 
                                                  WHERE qa.student_id = l.student_id AND qa.is_completed = 1),
                        l.total_challenges_completed = (SELECT COUNT(DISTINCT ca.challenge_id) FROM challenge_attempts ca 
                                                      WHERE ca.student_id = l.student_id AND ca.is_completed = 1)
                        WHERE l.student_id = ?";
    executeQuery($directUpdateSql, [$studentId]);
}

// Helper function to get user statistics
function getUserStats($studentId) {
    // Get all stats in a single query for efficiency
    $statsSql = "SELECT 
                    s.total_points as student_points,
                    COALESCE(SUM(qa.points), 0) as quiz_points,
                    COALESCE(SUM(ca.points), 0) as challenge_points,
                    COUNT(DISTINCT CASE WHEN qa.is_completed = 1 THEN qa.quiz_id END) as completed_quizzes,
                    COUNT(DISTINCT CASE WHEN ca.is_completed = 1 THEN ca.challenge_id END) as completed_challenges
                FROM 
                    students s
                LEFT JOIN 
                    quiz_attempts qa ON s.student_id = qa.student_id AND qa.is_completed = 1
                LEFT JOIN 
                    challenge_attempts ca ON s.student_id = ca.student_id AND ca.is_completed = 1
                WHERE 
                    s.student_id = ?";
    
    try {
        $statsResult = executeQuery($statsSql, [$studentId]);
        
        if (is_array($statsResult) && count($statsResult) > 0) {
            $stats = $statsResult[0];
            
            // Calculate total points
            $studentPoints = isset($stats['student_points']) ? intval($stats['student_points']) : 0;
            $quizPoints = isset($stats['quiz_points']) ? intval($stats['quiz_points']) : 0;
            $challengePoints = isset($stats['challenge_points']) ? intval($stats['challenge_points']) : 0;
            $totalPoints = $studentPoints + $quizPoints + $challengePoints;
            
            // Get counts
            $completedQuizzes = isset($stats['completed_quizzes']) ? intval($stats['completed_quizzes']) : 0;
            $completedChallenges = isset($stats['completed_challenges']) ? intval($stats['completed_challenges']) : 0;
            
            // Log the calculation for debugging
            error_log("Leaderboard points calculation for student $studentId: Base=$studentPoints, Quiz=$quizPoints, Challenge=$challengePoints, Total=$totalPoints");
            
            return [
                'student_points' => $studentPoints,
                'quiz_points' => $quizPoints,
                'challenge_points' => $challengePoints,
                'total_points' => $totalPoints,
                'completed_quizzes' => $completedQuizzes,
                'completed_challenges' => $completedChallenges
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting user stats: " . $e->getMessage());
    }
    
    // Return default values if query fails
    return [
        'student_points' => 0,
        'quiz_points' => 0,
        'challenge_points' => 0,
        'total_points' => 0,
        'completed_quizzes' => 0,
        'completed_challenges' => 0
    ];
}

// Sync leaderboard data for all users if needed (can be resource intensive, use wisely)
function syncAllLeaderboardData() {
    try {
        // Get all active students
        $studentsSql = "SELECT student_id FROM students";
        $students = executeSimpleQuery($studentsSql);
        
        if (is_array($students) && count($students) > 0) {
            foreach ($students as $student) {
                try {
                    updateUserLeaderboardEntry($student['student_id']);
                } catch (Exception $e) {
                    // Log error but continue with next student
                    error_log("Error updating leaderboard for student ID {$student['student_id']}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in syncAllLeaderboardData: " . $e->getMessage());
    }
}

// Sync leaderboard data for current user if they're logged in
if (isset($_SESSION['student_id'])) {
    $currentStudentId = $_SESSION['student_id'];
    // Update the current user's leaderboard entry on each page load
    try {
        updateUserLeaderboardEntry($currentStudentId);
    } catch (Exception $e) {
        error_log("Error updating leaderboard for current user: " . $e->getMessage());
    }
}

// Handle sync request (admin only)
$syncMessage = '';
if (isset($_GET['sync']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    try {
        syncAllLeaderboardData();
        header("Location: leaderboard.php?synced=1");
        exit();
    } catch (Exception $e) {
        header("Location: leaderboard.php?error=sync_failed");
        exit();
    }
}

// Set page title
$pageTitle = "CodaQuest - Leaderboard";

// Get current user's ID if logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$currentStudentId = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;

// Fetch leaderboard data from database
$leaderboardData = [];
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';

// Time constraints for different periods
$timeConstraint = '';
$timeParams = [];

// For weekly/monthly filtering
if ($period === 'weekly') {
    // Get data from the last 7 days
    $timeConstraint = 'AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} else if ($period === 'monthly') {
    // Get data from the last 30 days
    $timeConstraint = 'AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} else {
    // All time - no time constraint
    $timeConstraint = '';
}

// Use a direct query approach to get the most accurate and up-to-date data
$sql = "SELECT 
            l.leaderboard_id,
            l.student_id,
            s.username,
            l.total_points,
            (SELECT COUNT(DISTINCT ca.challenge_id) FROM challenge_attempts ca 
             WHERE ca.student_id = l.student_id AND ca.is_completed = 1) as completed_challenges,
            (SELECT COUNT(DISTINCT qa.quiz_id) FROM quiz_attempts qa 
             WHERE qa.student_id = l.student_id AND qa.is_completed = 1) as completed_quizzes
        FROM 
            leaderboard l
        JOIN
            students s ON l.student_id = s.student_id
        ORDER BY 
            l.total_points DESC, 
            completed_challenges DESC, 
            completed_quizzes DESC";

// Execute the query
$result = executeQuery($sql, $timeParams);

// Debug - log the raw result count
error_log("Leaderboard query returned " . count($result) . " rows");

// Let's check for duplicate student_ids directly in the result
$studentCounts = array();
foreach ($result as $entry) {
    $studentId = $entry['student_id'];
    if (!isset($studentCounts[$studentId])) {
        $studentCounts[$studentId] = 1;
    } else {
        $studentCounts[$studentId]++;
        error_log("Duplicate student_id found in results: " . $studentId . " (count: " . $studentCounts[$studentId] . ")");
    }
}

// If no results, possibly empty leaderboard table, let's populate it
if (empty($result)) {
    // Try to sync the data
    syncAllLeaderboardData();
    
    // Try the query again
    $result = executeQuery($sql, $timeParams);
}

// Check if query was successful
if (is_array($result) && !empty($result)) {
    // Filter out duplicate student IDs
    $uniqueStudents = [];
    $filteredResult = [];
    
    foreach ($result as $entry) {
        if (!in_array($entry['student_id'], $uniqueStudents)) {
            $uniqueStudents[] = $entry['student_id'];
            $filteredResult[] = $entry;
        } else {
            error_log("Filtered out duplicate student ID: " . $entry['student_id']);
        }
    }
    
    $result = $filteredResult;
    error_log("After filtering: " . count($result) . " unique students");
    
    // Add rank to each user (ranks are already sorted by the SQL query)
    $rank = 1;
    foreach ($result as &$user) {
        $user['rank'] = $rank++;
    }
    
    $leaderboardData = $result;
} else {
    // If no results, try to sync the leaderboard data
    try {
        syncAllLeaderboardData();
        // Try the query again
        $result = executeQuery($sql, $timeParams);
        
        if (is_array($result) && !empty($result)) {
            // Filter out duplicate student IDs
            $uniqueStudents = [];
            $filteredResult = [];
            
            foreach ($result as $entry) {
                if (!in_array($entry['student_id'], $uniqueStudents)) {
                    $uniqueStudents[] = $entry['student_id'];
                    $filteredResult[] = $entry;
                } else {
                    error_log("Filtered out duplicate student ID (retry): " . $entry['student_id']);
                }
            }
            
            $result = $filteredResult;
            error_log("After filtering (retry): " . count($result) . " unique students");
            
            // Add rank to each user
            $rank = 1;
            foreach ($result as &$user) {
                $user['rank'] = $rank++;
            }
            $leaderboardData = $result;
        } else {
            // Still no results, use empty array
            $leaderboardData = [];
        }
    } catch (Exception $e) {
        error_log("Error syncing leaderboard data: " . $e->getMessage());
        $leaderboardData = [];
    }
}

// Filter out duplicate student IDs before rendering
$displayLeaderboard = [];
$displayedStudentIds = [];

// Only use entries with unique student IDs
foreach ($leaderboardData as $entry) {
    if (!in_array($entry['student_id'], $displayedStudentIds)) {
        $displayedStudentIds[] = $entry['student_id'];
        $displayLeaderboard[] = $entry;
    }
}

// Log the final count
error_log("Final leaderboard entries for display: " . count($displayLeaderboard));

// Additional styles for leaderboard page
$additionalStyles = '
<style>
    /* Matrix Background */
    #matrix-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.1;
    }
    
    /* Add bronze color variable for both themes */
    :root {
        --bronze-color: #cd7f32;
    }
    body.dark-mode {
        --bronze-color: #e0916b;
    }
    
    body {
        padding: 0;
        margin: 0;
        background: var(--background-color);
        color: var(--text-color);
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
    }
    
    .container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        background-color: var(--card-bg);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        text-align: center;
        border: 4px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }
    
    .page-title {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        text-transform: uppercase;
        text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .page-description {
        color: var(--text-color);
        max-width: 800px;
        margin: 0 auto;
        font-family: inherit;
    }
    .leaderboard-table-container {
        max-width: 1100px;
        margin: 0 auto;
        background-color: var(--card-bg);
        border-radius: var(--border-radius);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        border: 4px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .leaderboard-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .leaderboard-table td,
    .leaderboard-table th {
        padding: 18px 25px;
        text-align: left;
        border-bottom: 2px solid rgba(var(--primary-color-rgb), 0.3);
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
        color: var(--text-color);
        transition: all 0.3s ease;
    }
    
    .leaderboard-table tbody tr {
        transition: transform 0.2s ease, background-color 0.3s ease;
    }
    
    .leaderboard-table tbody tr:hover {
        background-color: rgba(var(--primary-color-rgb), 0.05);
        transform: translateY(-2px);
    }
    
    /* Theme-specific styling */
    [data-theme="dark"] .leaderboard-table-container {
        background-color: rgba(30, 30, 30, 0.95);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
    }
    
    [data-theme="dark"] .leaderboard-table tbody tr:hover {
        background-color: rgba(var(--primary-color-rgb), 0.1);
    }

    .leaderboard-table th {
        color: var(--primary-color);
        font-weight: bold;
        background: var(--card-bg);
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding-top: 20px;
        padding-bottom: 20px;
        border-bottom: 3px solid var(--primary-color);
    }
    
    .leaderboard-table td.points {
        color: var(--primary-color);
        font-weight: bold;
        font-size: 1.2rem;
        text-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.3);
    }
    
    .leaderboard-table td .material-icons {
        color: var(--primary-color);
        vertical-align: middle;
        font-size: 1.2em;
        margin-right: 5px;
    }
    
    .leaderboard-table tr:last-child td {
        border-bottom: none;
    }
    
    .rank {
        font-weight: bold;
        color: var(--primary-color);
        font-size: 1.4rem;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin: 0 auto;
        background-color: rgba(var(--primary-color-rgb), 0.1);
        box-shadow: 0 0 15px rgba(var(--primary-color-rgb), 0.2);
    }
    
    .rank-1 {
        background-color: rgba(255, 215, 0, 0.2);
        color: #FFD700;
        box-shadow: 0 0 15px rgba(255, 215, 0, 0.3);
    }
    
    .rank-2 {
        background-color: rgba(192, 192, 192, 0.2);
        color: #C0C0C0;
        box-shadow: 0 0 15px rgba(192, 192, 192, 0.3);
    }
    
    .rank-3 {
        background-color: rgba(205, 127, 50, 0.2);
        color: #CD7F32;
        box-shadow: 0 0 15px rgba(205, 127, 50, 0.3);
    }
    
    .user {
        min-width: 200px;
        font-family: inherit;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .username-plain {
        color: var(--text-color);
        font-weight: bold;
        font-size: 1rem;
        text-shadow: 0 0 5px rgba(var(--primary-color-rgb), 0.2);
    }
.stats {
        display: flex;
        align-items: center;
        font-size: 0.9rem;
        font-family: inherit;
        gap: 15px;
    }
    
    .stat {
        display: flex;
        align-items: center;
        gap: 8px;
        background-color: rgba(var(--primary-color-rgb), 0.08);
        padding: 6px 12px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    
    .stat:hover {
        background-color: rgba(var(--primary-color-rgb), 0.15);
        transform: translateY(-2px);
    }
    
    .stat-value {
        font-weight: bold;
        color: var(--primary-color);
    }
    
    .stat-label {
        color: var(--text-muted);
        font-size: 0.8rem;
    }
    
    /* Ensure all stats are visible in both light and dark themes */
    .stat-value {
        color: var(--primary-color);
        font-weight: bold;
        font-size: 1.2em;
    }
    
    .stat-label {
        color: var(--text-color);
    }
    
    /* Dark mode specific overrides */
    body.dark-mode .stat-label {
        color: rgba(255, 255, 255, 0.9);
    }
    
    /* Override row backgrounds in dark mode */
    body.dark-mode .leaderboard-table tr {
        background-color: rgba(30, 30, 30, 0.9) !important;
    }
    
    body.dark-mode .leaderboard-table tr:nth-child(odd) td {
        background-color: rgba(30, 30, 30, 0.9) !important;
    }
    
    body.dark-mode .leaderboard-table tr:nth-child(even) td {
        background-color: rgba(40, 40, 40, 0.9) !important;
    }
    
    body.dark-mode .leaderboard-table th {
        background-color: rgba(20, 20, 20, 0.95) !important;
    }
    @media (max-width: 800px) {
        .leaderboard-table th, .leaderboard-table td {
            padding: 10px 8px;
            font-size: 0.85rem;
        }
        .user {
            min-width: 100px;
        }
        .stats {
            flex-direction: column;
            gap: 5px;
        }
    }
    
    .leaderboard-table th {
        background-color: var(--primary-color);
        color: var(--text-color);
        font-weight: 700;
    }
    
    .leaderboard-table tr:last-child td {
        border-bottom: none;
    }
    
    .leaderboard-table tr:hover {
        background-color: var(--accent-color);
    }
    
    .leaderboard-table tr.current-user td {
        background-color: #f0f8ff;
        font-weight: bold;
        color: #333;
    }
    
    [data-theme="dark"] .leaderboard-table tr.current-user td,
    body.dark-mode .leaderboard-table tr.current-user td {
        background-color: transparent;
        color: var(--text-color);
    }
    
    .current-user {
        border-left: 4px solid var(--primary-color);
    }
    
    .rank {
        font-weight: 700;
        width: 60px;
        text-align: center;
    }
    
    .rank-1, .rank-2, .rank-3 {
        font-size: 1.2rem;
    }
    
    .rank-1 {
        color: gold;
    }
    
    .rank-2 {
        color: silver;
    }
    
    .rank-3 {
        color: var(--bronze-color, #cd7f32); /* bronze with fallback */
    }
    
    .username {
        font-weight: 600;
        color: var(--text-color);
    }
    
    .points {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .stats {
        display: flex;
        gap: 25px;
        min-width: 250px;
    }
    
    .stat {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .points-breakdown {
        min-width: 200px;
    }
    
    .breakdown {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .breakdown-item {
        display: flex;
        justify-content: space-between;
        font-size: 0.9rem;
        padding: 2px 0;
    }
    
    .breakdown-item .label {
        color: var(--text-color);
        margin-right: 10px;
    }
    
    .breakdown-item .value {
        font-weight: bold;
        color: var(--primary-color);
    }
    
    .user {
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 200px;
    }
    
    .leaderboard-tabs {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-bottom: 32px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 20px;
        border-radius: 20px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-color);
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .filter-btn:hover:not(.active) {
        border-color: var(--primary-color);
        background-color: rgba(var(--primary-color-rgb), 0.1);
    }
    
    .badge-you {
        background-color: var(--primary-color);
        color: white;
        font-size: 0.7rem;
        padding: 3px 8px;
        border-radius: 10px;
        text-transform: uppercase;
        font-weight: bold;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 5px rgba(var(--primary-color-rgb), 0.3);
    }
    
    /* Current user highlighting */
    tr.current-user {
        background-color: rgba(var(--primary-color-rgb), 0.05) !important;
        border-left: 4px solid var(--primary-color);
        position: relative;
    }
    
    [data-theme="dark"] tr.current-user {
        background-color: rgba(var(--primary-color-rgb), 0.1) !important;
    }
    
    /* Admin Controls */
    .admin-controls {
        margin: 15px 0;
        padding: 10px;
        text-align: center;
    }
    
    .sync-message {
        padding: 10px 15px;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        margin: 15px auto;
        max-width: 500px;
        text-align: center;
        font-size: 0.9rem;
    }
    
    .sync-message.success {
        background-color: rgba(0, 255, 0, 0.1);
        border-color: green;
    }
    
    @media (max-width: 768px) {
        .leaderboard-table th,
        .leaderboard-table td {
            padding: 10px;
        }
        
        .stats {
            flex-direction: column;
            gap: 5px;
        }
        
        .leaderboard-tabs {
            flex-wrap: wrap;
        }
    }
</style>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <?php include_once 'common_styles.php'; ?>
    <?php echo $additionalStyles; ?>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="material-icons">leaderboard</i> Leaderboard</h1>
            <p class="page-description">See how you stack up against other CodaQuest learners. Complete challenges and quizzes to earn points and climb the ranks!</p>
            
            <?php if ($syncMessage): ?>
            <div class="sync-message success">
                <?php echo $syncMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="admin-controls">
                <a href="leaderboard.php?sync=true" class="btn btn-secondary">
                    <i class="material-icons">sync</i> Sync Leaderboard Data
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="leaderboard-container">
            
            <div class="leaderboard-tabs">
                <button class="filter-btn <?php echo ($period === 'all_time' || !isset($_GET['period'])) ? 'active' : ''; ?>" data-period="all_time">All Time</button>
                <button class="filter-btn <?php echo ($period === 'monthly') ? 'active' : ''; ?>" data-period="monthly">This Month</button>
                <button class="filter-btn <?php echo ($period === 'weekly') ? 'active' : ''; ?>" data-period="weekly">This Week</button>
            </div>
            
            <div class="leaderboard-table-container">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th width="80">Rank</th>
                            <th>User</th>
                            <th width="150">Total Points</th>
                            <th>Stats</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($displayLeaderboard as $index => $user): 
                            // Determine if this is the current user
                            $isCurrentUser = (isset($user['student_id']) && isset($currentStudentId) && $user['student_id'] == $currentStudentId);
                            
                            // For dark mode, we'll add a data attribute that we'll target with JavaScript
                            $rowClass = $isCurrentUser ? 'current-user' : '';
                        ?>
                            <tr class="<?php echo $rowClass; ?>" data-row-index="<?php echo $index; ?>">
                                <td>
                                    <div class="rank rank-<?php echo $user['rank']; ?>">
                                        <?php echo $user['rank']; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="user">
                                        <?php if ($user['rank'] <= 3): ?>
                                            <i class="material-icons">
                                                <?php 
                                                    if ($user['rank'] == 1) echo 'emoji_events';
                                                    elseif ($user['rank'] == 2) echo 'workspace_premium';
                                                    else echo 'military_tech';
                                                ?>
                                            </i>
                                        <?php endif; ?>
                                        <span class="username-plain"><?php echo htmlspecialchars($user['username']); ?></span>
                                        <?php if ($isCurrentUser): ?>
                                            <span class="badge-you">You</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="points">
                                    <span><?php echo number_format($user['total_points']); ?></span>
                                </td>
                                <td>
                                    <div class="stats">
                                        <div class="stat">
                                            <i class="material-icons">code</i>
                                            <span class="stat-value"><?php echo $user['completed_challenges']; ?></span>
                                            <span class="stat-label">challenges</span>
                                        </div>
                                        <div class="stat">
                                            <i class="material-icons">quiz</i>
                                            <span class="stat-value"><?php echo $user['completed_quizzes']; ?></span>
                                            <span class="stat-label">quizzes</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <script>
        // Handle tab switching and theme adaptation
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.filter-btn');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Get the selected period and redirect to refresh the page with new data
                    const period = this.getAttribute('data-period');
                    window.location.href = `leaderboard.php?period=${period}`;
                });
            });
            
            // Highlight the top 3 ranks with special effects
            const highlightTopRanks = () => {
                // Add special effects to top 3 ranks
                const rank1 = document.querySelector('.rank-1');
                const rank2 = document.querySelector('.rank-2');
                const rank3 = document.querySelector('.rank-3');
                
                if (rank1) {
                    rank1.innerHTML += '<div class="crown"></div>';
                }
                
                // Highlight the current user's row
                const currentUserRow = document.querySelector('tr.current-user');
                if (currentUserRow) {
                    currentUserRow.classList.add('highlight-pulse');
                }
            };
            
            // Call the highlight function
            highlightTopRanks();
            
            // Add animation to stats on hover
            const stats = document.querySelectorAll('.stat');
            stats.forEach(stat => {
                stat.addEventListener('mouseenter', function() {
                    const value = this.querySelector('.stat-value');
                    if (value) {
                        value.classList.add('pulse');
                        setTimeout(() => {
                            value.classList.remove('pulse');
                        }, 1000);
                    }
                });
            });
        });
    </script>
    
    <style>
        /* Animation for highlighting */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes highlight-pulse {
            0% { box-shadow: 0 0 0 0 rgba(var(--primary-color-rgb), 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(var(--primary-color-rgb), 0); }
            100% { box-shadow: 0 0 0 0 rgba(var(--primary-color-rgb), 0); }
        }
        
        .highlight-pulse {
            animation: highlight-pulse 2s infinite;
        }
    </style>
    
    <!-- Include matrix background script for leaderboard page -->
    <script src="matrix-bg.js"></script>
</body>
</html>
