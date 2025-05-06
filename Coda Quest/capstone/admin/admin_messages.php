<?php
session_start();
require_once '../config/db_connect.php';
$pdo = getDbConnection(); // Ensure PDO is defined for this page

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle message response
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message_id = $_POST['message_id'];
        $admin_response = trim($_POST['admin_response']);
        $status = $_POST['status'];
        $resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("
            UPDATE admin_messages 
            SET admin_response = :admin_response, 
                status = :status,
                resolved_at = :resolved_at
            WHERE message_id = :message_id
        ");

        $stmt->execute([
            'admin_response' => $admin_response,
            'status' => $status,
            'resolved_at' => $resolved_at,
            'message_id' => $message_id
        ]);

        // Redirect to prevent form resubmission
        header('Location: admin_messages.php?success=1');
        exit();
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        $error = "An error occurred while updating the message.";
    }
}

$page_title = "Admin Messages";
// Sidebar and styles included below
include_once 'admin_styles.php';

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Get messages with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$total = $pdo->query("SELECT COUNT(*) FROM admin_messages")->fetchColumn();
$total_pages = ceil($total / $limit);

// Get messages for current page
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$query = "SELECT * FROM admin_messages";
if ($status_filter !== 'all') {
    $query .= " WHERE status = :status";
}
$query .= " ORDER BY submitted_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);
if ($status_filter !== 'all') {
    $stmt->bindValue(':status', $status_filter);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Admin Messages - CodaQuest'; ?></title>
    <style>
        /* Custom styles for messages page */
        .welcome-message {
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        /* Message container */
        .messages-container {
            display: flex;
            flex-direction: column;
            gap: 25px;
            margin-top: 20px;
        }
        
        /* Message card styling */
        .message-card {
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.3);
            padding-bottom: 15px;
        }
        
        .message-title {
            font-size: 16px;
            color: var(--primary-color);
            margin: 0 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .message-meta {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }
        
        /* Status badge styling */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Define theme-adaptive status colors */
        :root {
            --status-pending-color: #FFC107;
            --status-in-progress-color: #2196F3;
            --status-resolved-color: #4CAF50;
            --success-color: #4CAF50;
        }
        
        [data-theme="light"] {
            --status-pending-color: #FF9800;
            --status-in-progress-color: #1976D2;
            --status-resolved-color: #2e7d32;
            --success-color: #2e7d32;
        }
        
        .status-pending {
            background-color: rgba(245, 124, 0, 0.2);
            color: var(--status-pending-color);
            border: 1px solid var(--status-pending-color);
        }
        
        .status-in-progress {
            background-color: rgba(33, 150, 243, 0.2);
            color: var(--status-in-progress-color);
            border: 1px solid var(--status-in-progress-color);
        }
        
        .status-resolved {
            background-color: rgba(46, 125, 50, 0.2);
            color: var(--status-resolved-color);
            border: 1px solid var(--status-resolved-color);
        }
        
        /* Message content styling */
        .message-content {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-color);
            background-color: rgba(0, 0, 0, 0.2);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid var(--primary-color);
        }
        
        /* Admin response styling */
        .admin-response {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .admin-response h4 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-response p {
            color: var(--text-color);
            margin: 0 0 10px 0;
            font-size: 14px;
        }
        
        .admin-response small {
            color: var(--text-muted);
            font-size: 12px;
            display: block;
            text-align: right;
        }
        
        /* Form styling */
        .response-form {
            margin-top: 20px;
        }
        
        .response-form textarea {
            width: 100%;
            background-color: var(--input-bg);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            font-family: inherit;
            font-size: 14px;
            margin-bottom: 15px;
            resize: vertical;
            min-height: 100px;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        
        .response-form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.3);
        }
        
        .form-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .response-form select {
            background-color: var(--input-bg);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s ease;
            min-width: 150px;
        }
        
        .response-form select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .submit-btn {
            background-color: var(--primary-color);
            color: var(--background-color);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        /* Filter buttons */
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        
        .filter-btn {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 15px;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-btn.active, .filter-btn:hover {
            background-color: var(--primary-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        /* Success message */
        .success-message {
            background-color: rgba(46, 125, 50, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .success-message i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 30px 0;
        }
        
        .pagination a {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 8px 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .pagination a.current, .pagination a:hover {
            background-color: var(--primary-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        /* No messages */
        .no-messages {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
            margin-top: 20px;
        }
        
        .no-messages i {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .no-messages h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .no-messages p {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        /* Section title */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 3px solid var(--border-color);
            padding-bottom: 15px;
        }
        
        .section-title {
            font-size: 16px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .section-icon {
            margin-right: 10px;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">mail</i> Message Management</h1>
                <div class="user-info">
                    <!-- User avatar removed -->
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Welcome message removed -->
                
                <div class="section-header">
                    <h2 class="section-title"><i class="material-icons section-icon">forum</i> User Messages</h2>
                </div>
                
                <div class="filters">
                    <a href="?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All Messages</a>
                    <a href="?status=pending" class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?status=in_progress" class="filter-btn <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                    <a href="?status=resolved" class="filter-btn <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">Resolved</a>
                </div>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">
                        <i class="material-icons">check_circle</i> Message response updated successfully!
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($messages)): ?>
                    <div class="messages-container">
                        <?php foreach ($messages as $message): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div>
                                        <h3 class="message-title"><?php echo htmlspecialchars($message['subject']); ?></h3>
                                        <div class="message-meta">
                                            <span><i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 5px;">person</i> <?php echo htmlspecialchars($message['name']); ?> (<?php echo htmlspecialchars($message['email']); ?>)</span><br>
                                            <span><i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 5px;">schedule</i> <?php echo date('M j, Y g:i A', strtotime($message['submitted_at'])); ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?php echo str_replace('_', '-', $message['status']); ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($message['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                                
                                <?php if (!empty($message['admin_response'])): ?>
                                    <div class="admin-response">
                                        <h4><i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 5px;">reply</i> Admin Response</h4>
                                        <p><?php echo nl2br(htmlspecialchars($message['admin_response'])); ?></p>
                                        <?php if ($message['resolved_at']): ?>
                                            <small><i class="material-icons" style="font-size: 12px; vertical-align: middle; margin-right: 5px;">event_available</i> Resolved: <?php echo date('M j, Y g:i A', strtotime($message['resolved_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form class="response-form" method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                    <textarea name="admin_response" placeholder="Enter your response..."><?php echo htmlspecialchars($message['admin_response'] ?? ''); ?></textarea>
                                    <div class="form-actions">
                                        <select name="status">
                                            <option value="pending" <?php echo $message['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $message['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $message['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                        <button type="submit" class="submit-btn">Update Response</button>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" class="<?php echo $page === $i ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <div class="no-messages">
                        <i class="material-icons">email</i>
                        <h3>No Messages Found</h3>
                        <p>There are no messages matching your current filter.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

