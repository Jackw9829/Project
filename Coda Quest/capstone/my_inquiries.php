<?php
// Include authentication check
require_once 'includes/auth_check.php';

// Start session is already handled in auth_check.php

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';

// Include database connection
require_once 'config/db_connect.php';

// Get user inquiries
$inquiries = [];
if ($isLoggedIn) {
    try {
        // Get database connection
        $pdo = getDbConnection();
        if ($pdo) {
            // Determine if user is student or admin
            if (isset($_SESSION['student_id'])) {
                $sql = "SELECT * FROM admin_messages 
                        WHERE student_id = ? 
                        ORDER BY submitted_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['student_id']]);
            } elseif (isset($_SESSION['admin_id'])) {
                $sql = "SELECT * FROM admin_messages 
                        WHERE admin_id = ? 
                        ORDER BY submitted_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['admin_id']]);
            }
            
            if (isset($stmt)) {
                $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching inquiries: " . $e->getMessage());
    }
}

// Set page title
$pageTitle = "My Inquiries - CodaQuest";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'common_styles.php'; ?>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            color: var(--primary-color);
            font-family: 'Press Start 2P', cursive;
            font-size: 1.8rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .page-description {
            color: var(--text-color);
            font-size: 1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .inquiries-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .inquiry-card {
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .inquiry-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }
        
        .inquiry-header {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-color);
        }
        
        .inquiry-subject {
            font-family: 'Press Start 2P', cursive;
            font-size: 1rem;
            color: var(--primary-color);
            margin: 0;
        }
        
        .inquiry-date {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .inquiry-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #FFC107;
            color: #000;
        }
        
        .status-in-progress {
            background-color: #2196F3;
            color: #fff;
        }
        
        .status-resolved {
            background-color: #4CAF50;
            color: #fff;
        }
        
        .inquiry-content {
            padding: 20px;
        }
        
        .inquiry-message {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.2);
        }
        
        .message-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }
        
        .message-text {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        .admin-response {
            background-color: rgba(0, 180, 216, 0.1);
            padding: 15px;
            border-radius: var(--border-radius);
            border-left: 3px solid var(--secondary-color);
        }
        
        .response-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .response-text {
            white-space: pre-wrap;
            line-height: 1.6;
        }
        
        .response-date {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
            margin-top: 10px;
            text-align: right;
        }
        
        .no-inquiries {
            text-align: center;
            padding: 40px;
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
        }
        
        .no-inquiries-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: var(--text-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-family: 'Press Start 2P', cursive;
            font-size: 0.8rem;
            transition: background-color 0.3s ease;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background-color: var(--secondary-color);
        }
        
        @media (max-width: 768px) {
            .inquiry-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .inquiry-subject {
                font-size: 0.9rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="material-icons">question_answer</i> My Inquiries</h1>
            <p class="page-description">View and track your inquiries to the admin team.</p>
        </div>
        
        <div class="inquiries-container">
            <?php if (count($inquiries) > 0): ?>
                <?php foreach ($inquiries as $inquiry): ?>
                    <div class="inquiry-card">
                        <div class="inquiry-header">
                            <h3 class="inquiry-subject"><?php echo htmlspecialchars($inquiry['subject']); ?></h3>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="inquiry-date">
                                    <?php echo date('M d, Y', strtotime($inquiry['submitted_at'])); ?>
                                </span>
                                <?php 
                                    $statusClass = '';
                                    switch($inquiry['status']) {
                                        case 'pending':
                                            $statusClass = 'status-pending';
                                            break;
                                        case 'in_progress':
                                            $statusClass = 'status-in-progress';
                                            break;
                                        case 'resolved':
                                            $statusClass = 'status-resolved';
                                            break;
                                    }
                                ?>
                                <span class="inquiry-status <?php echo $statusClass; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $inquiry['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="inquiry-content">
                            <div class="inquiry-message">
                                <div class="message-label">Your Message:</div>
                                <div class="message-text"><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></div>
                            </div>
                            
                            <?php if (!empty($inquiry['admin_response'])): ?>
                                <div class="admin-response">
                                    <div class="response-label">
                                        <i class="material-icons">support_agent</i> Admin Response:
                                    </div>
                                    <div class="response-text"><?php echo nl2br(htmlspecialchars($inquiry['admin_response'])); ?></div>
                                    <?php if (!empty($inquiry['resolved_at'])): ?>
                                        <div class="response-date">
                                            Responded on <?php echo date('M d, Y', strtotime($inquiry['resolved_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-inquiries">
                    <h3>No Inquiries Found</h3>
                    <p>You haven't submitted any inquiries to the admin team yet.</p>
                    <a href="contact_admin.php" class="action-btn">Submit an Inquiry</a>
                </div>
            <?php endif; ?>
            
            <?php if (count($inquiries) > 0): ?>
            <div style="text-align: center; margin-top: 30px;">
                <a href="contact_admin.php" class="action-btn">Submit New Inquiry</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include_once 'includes/footer.php'; ?>
    
    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
