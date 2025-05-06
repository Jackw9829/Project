<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'config/db_connect.php';

// Get user information
function getUserInfo($userId) {
    // Check if user is a student or admin based on role
    if ($_SESSION['role'] === 'student') {
        $sql = "SELECT username, email, full_name, profile_picture, auth_provider, date_registered, 
                total_points, current_level FROM students WHERE student_id = ?";
    } else {
        $sql = "SELECT username, email, full_name, profile_picture, auth_provider, date_registered 
                FROM admins WHERE admin_id = ?";
    }
    
    $result = executeQuery($sql, [$userId]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

// Get user achievements
function getUserAchievements($userId) {
    // Only students have achievements
    if ($_SESSION['role'] !== 'student') {
        return [];
    }
    
    $sql = "SELECT a.title, a.description, a.icon, a.points, 
            ua.earned_at
            FROM achievements a
            JOIN user_achievements ua ON a.achievement_id = ua.achievement_id
            WHERE ua.student_id = ?
            ORDER BY ua.earned_at DESC";
    
    $result = executeQuery($sql, [$userId]);
    $achievements = [];
    
    if ($result && count($result) > 0) {
        $achievements = $result;
    }
    
    return $achievements;
}

// Get user learning progress
function getUserLearningProgress($userId) {
    $sql = "SELECT lp.path_name, lp.difficulty_level, up.progress_percentage, 
            up.last_activity_date, up.completed_modules, up.total_modules
            FROM learning_paths lp
            JOIN user_progress up ON lp.path_id = up.path_id
            WHERE up.user_id = ?
            ORDER BY up.last_activity_date DESC";
    
    $result = executeQuery($sql, [$userId]);
    $progress = [];
    
    if ($result && count($result) > 0) {
        $progress = $result;
    }
    
    return $progress;
}

// Get user recent activity
function getUserRecentActivity($userId, $limit = 5) {
    $sql = "SELECT activity_type, activity_details, timestamp
            FROM activity_log
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT ?";
    
    $result = executeQuery($sql, [$userId, $limit]);
    $activities = [];
    
    if ($result && count($result) > 0) {
        $activities = $result;
    }
    
    return $activities;
}

// Process profile update if form submitted
$updateMessage = '';
$updateError = '';

// File upload directory
$uploadDir = 'uploads/profile_pictures/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $userId = $_SESSION['role'] === 'student' ? $_SESSION['student_id'] : $_SESSION['admin_id'];
    
    // Handle profile picture upload (no password needed)
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['profile_picture']['name']);
        $extension = strtolower($fileInfo['extension']);
        
        // Check if file is an image
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extension, $allowedExtensions)) {
            // Generate unique filename
            $newFileName = 'profile_' . $userId . '_' . time() . '.' . $extension;
            $targetFile = $uploadDir . $newFileName;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
                // Update database with new profile picture
                $updatePictureSql = "UPDATE users SET profile_picture = ? WHERE user_id = ?";
                executeQuery($updatePictureSql, [$newFileName, $userId]);
                
                $updateMessage = "Profile picture updated successfully";
            } else {
                $updateError = "Failed to upload profile picture";
            }
        } else {
            $updateError = "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed";
        }
    }
    
    // Only process other profile updates if there's no error from profile picture upload
    if (empty($updateError)) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updateError = "Invalid email format";
        } else {
            // Update user information
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
            $result = executeQuery($sql, [$firstName, $lastName, $email, $userId]);
            
            if ($result) {
                $updateMessage = !empty($updateMessage) ? $updateMessage . ". Profile information updated successfully" : "Profile updated successfully";
            } else {
                $updateError = "Error updating profile information";
            }
        }
    }
}

// Get user data
$user = getUserInfo($userId);
$achievements = getUserAchievements($userId);
$learningProgress = getUserLearningProgress($userId);
$recentActivities = getUserRecentActivity($userId);

// Set page title
$pageTitle = "User Profile - CodaQuest";
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
    <link rel="stylesheet" href="common.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Tiny5", sans-serif;
            font-weight: 400;
            font-style: normal;
        }

        .profile-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            overflow: hidden;
            border: 4px solid var(--primary-color);
        }
        
        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-picture-upload {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 15px;
            border: 3px solid var(--primary-color);
            background-color: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-picture-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .form-control-file {
            margin-top: 10px;
            background-color: transparent;
            color: white;
            padding: 5px;
            border: 1px dashed var(--border-color);
            border-radius: 4px;
            cursor: pointer;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .profile-username {
            font-size: 18px;
            color: #777;
            margin-bottom: 15px;
        }

        .profile-stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 14px;
            color: #777;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
        }

        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .progress-item {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .progress-title {
            font-weight: 600;
            color: var(--text-color);
            display: flex;
            justify-content: space-between;
        }

        .progress-bar-container {
            height: 10px;
            background-color: #eee;
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--primary-color);
            border-radius: 5px;
        }

        .progress-details {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #777;
        }

        .achievement-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 20px;
        }

        .achievement-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .achievement-badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            color: white;
            font-size: 24px;
        }

        .achievement-badge.locked {
            background-color: #ccc;
            opacity: 0.7;
        }

        .achievement-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .achievement-points {
            font-size: 12px;
            color: #777;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .activity-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .activity-time {
            font-size: 14px;
            color: #777;
        }

        .edit-profile-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
        }

        .form-group input {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(176, 222, 253, 0.3);
        }

        .update-btn {
            background-color: var(--primary-color);
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            padding: 12px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            align-self: flex-start;
        }

        .update-btn:hover {
            background-color: var(--secondary-color);
        }

        .update-message {
            padding: 10px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .update-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .update-error {
            background-color: #ffebee;
            color: #c62828;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-stats {
                justify-content: center;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-picture">
                <?php if (!empty($user['profile_picture']) && file_exists($uploadDir . $user['profile_picture'])): ?>
                    <img src="<?php echo $uploadDir . htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                <?php elseif (isset($user['auth_provider']) && $user['auth_provider'] === 'google' && isset($_SESSION['google_picture'])): ?>
                    <img src="https://lh3.googleusercontent.com/a/<?php echo htmlspecialchars($_SESSION['google_picture']); ?>" alt="Google Profile Picture" onerror="this.src='assets/images/default-profile.png';">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h1>
                <p class="profile-username">@<?php echo $user['username']; ?></p>
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $user['total_points']; ?></span>
                        <span class="stat-label">Points</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $user['current_level']; ?></span>
                        <span class="stat-label">Level</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo count($achievements); ?></span>
                        <span class="stat-label">Achievements</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($updateMessage) || !empty($updateError)): ?>
            <div class="update-message <?php echo !empty($updateMessage) ? 'update-success' : 'update-error'; ?>">
                <?php echo !empty($updateMessage) ? $updateMessage : $updateError; ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <div class="profile-card">
                <h2 class="card-title">Learning Progress</h2>
                <div class="progress-list">
                    <?php if (count($learningProgress) > 0): ?>
                        <?php foreach ($learningProgress as $progress): ?>
                            <div class="progress-item">
                                <div class="progress-title">
                                    <span><?php echo $progress['path_name']; ?></span>
                                    <span><?php echo $progress['progress_percentage']; ?>%</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $progress['progress_percentage']; ?>%"></div>
                                </div>
                                <div class="progress-details">
                                    <span>Difficulty: <?php echo ucfirst($progress['difficulty_level']); ?></span>
                                    <span>Modules: <?php echo $progress['completed_modules']; ?>/<?php echo $progress['total_modules']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No learning paths started yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-card">
                <h2 class="card-title">Achievements</h2>
                <div class="achievement-list">
                    <?php if (count($achievements) > 0): ?>
                        <?php foreach ($achievements as $achievement): ?>
                            <div class="achievement-item">
                                <div class="achievement-badge <?php echo $achievement['completed'] ? '' : 'locked'; ?>">
                                    <i class="material-icons"><?php echo $achievement['completed'] ? 'emoji_events' : 'lock'; ?></i>
                                </div>
                                <div class="achievement-name"><?php echo $achievement['achievement_name']; ?></div>
                                <div class="achievement-points"><?php echo $achievement['points_reward']; ?> points</div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No achievements earned yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-card">
                <h2 class="card-title">Recent Activity</h2>
                <div class="activity-list">
                    <?php if (count($recentActivities) > 0): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="material-icons">
                                        <?php
                                        $icon = 'event_note';
                                        switch ($activity['activity_type']) {
                                            case 'login':
                                                $icon = 'login';
                                                break;
                                            case 'complete_module':
                                                $icon = 'check_circle';
                                                break;
                                            case 'complete_quiz':
                                                $icon = 'quiz';
                                                break;
                                            case 'earn_achievement':
                                                $icon = 'emoji_events';
                                                break;
                                            case 'complete_challenge':
                                                $icon = 'code';
                                                break;
                                        }
                                        echo $icon;
                                        ?>
                                    </i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title"><?php echo $activity['activity_details']; ?></div>
                                    <div class="activity-time"><?php echo date('F j, Y, g:i a', strtotime($activity['timestamp'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No recent activity.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-card">
                <h2 class="card-title">Edit Profile</h2>
                <form class="edit-profile-form" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group profile-picture-upload">
                        <label>Profile Picture</label>
                        <div class="profile-picture-preview">
                            <?php if (!empty($user['profile_picture']) && file_exists($uploadDir . $user['profile_picture'])): ?>
                                <img id="profile-preview" src="<?php echo $uploadDir . htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture">
                            <?php elseif (isset($user['auth_provider']) && $user['auth_provider'] === 'google' && isset($_SESSION['google_picture'])): ?>
                                <img id="profile-preview" src="https://lh3.googleusercontent.com/a/<?php echo htmlspecialchars($_SESSION['google_picture']); ?>" alt="Google Profile Picture" onerror="this.src='assets/images/default-profile.png';">
                            <?php else: ?>
                                <img id="profile-preview" src="assets/images/default-profile.png" alt="Default Profile Picture">
                            <?php endif; ?>
                        </div>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control-file">
                        <small class="form-text">Upload a profile picture (JPG, JPEG, PNG, or GIF)</small>
                        <?php if (isset($user['auth_provider']) && $user['auth_provider'] === 'google'): ?>
                            <small class="form-text">You're using Google authentication. Your Google profile picture is shown by default.</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo $user['first_name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo $user['last_name']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo $user['email']; ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="update-btn">Update Profile</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Matrix Background Effect
        const canvas = document.getElementById('matrix-bg');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const matrix = "ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789@#$%^&*()*&^%";
        const matrixChars = matrix.split("");

        const fontSize = 14;
        const columns = canvas.width / fontSize;

        const drops = [];
        for (let i = 0; i < columns; i++) {
            drops[i] = 1;
        }

        function draw() {
            ctx.fillStyle = "rgba(255, 255, 255, 0.05)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = "#b2defd";
            ctx.font = fontSize + "px Tiny5";

            for (let i = 0; i < drops.length; i++) {
                const text = matrixChars[Math.floor(Math.random() * matrixChars.length)];
                ctx.fillText(text, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }

                drops[i]++;
            }
        }

        setInterval(draw, 35);

        // Resize canvas on window resize
        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });
    </script>
    <script>
        // Preview profile picture before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profile-preview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
    <?php include_once 'includes/music_player.php'; ?>
</body>
</html>
