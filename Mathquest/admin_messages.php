<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
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
require_once 'includes/header.php';

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
    <title>Admin Messages - MathQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        body {
            font-family: 'Tiny5', sans-serif;
        }

        .messages-container {
            max-width: 1200px;
            margin: 6rem auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        .filters {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            font-family: 'Tiny5', sans-serif;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            background-color: #f0f0f0;
            color: #000000;
        }

        .filter-btn:hover {
            background-color: #ffdcdc;
            color: #000000;
        }

        .filter-btn.active {
            background-color: #ffdcdc;
            color: #000000;
        }

        .message-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-family: 'Tiny5', sans-serif;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            align-items: center;
        }

        .message-meta {
            font-size: 0.9em;
            color: #666;
        }

        .message-content {
            margin: 15px 0;
            line-height: 1.6;
        }

        .message-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-resolved {
            background-color: #d4edda;
            color: #155724;
        }

        .response-form {
            margin-top: 15px;
        }

        .response-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            font-family: 'Tiny5', sans-serif;
            resize: vertical;
            min-height: 100px;
        }

        .response-form select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            font-family: 'Tiny5', sans-serif;
            background-color: white;
        }

        .submit-btn {
            background-color: #ffdcdc;
            color: #333;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
        }

        .submit-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            font-family: 'Tiny5', sans-serif;
        }

        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background-color: #ffdcdc;
            border-color: #ffdcdc;
        }

        .pagination .current {
            background-color: #ffdcdc;
            border-color: #ffdcdc;
            color: #333;
        }

        h1 {
            font-family: 'Tiny5', sans-serif;
            color: #2c3e50;
            margin-bottom: 2rem;
            text-align: center;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-family: 'Tiny5', sans-serif;
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
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <div class="messages-container">
        <h1>Admin Messages</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">Message updated successfully!</div>
        <?php endif; ?>

        <div class="filters">
            <a href="?status=all" class="filter-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=pending" class="filter-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=in_progress" class="filter-btn <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?status=resolved" class="filter-btn <?php echo $status_filter === 'resolved' ? 'active' : ''; ?>">Resolved</a>
        </div>

        <?php foreach ($messages as $message): ?>
            <div class="message-card">
                <div class="message-header">
                    <div>
                        <h3><?php echo htmlspecialchars($message['subject']); ?></h3>
                        <div class="message-meta">
                            From: <?php echo htmlspecialchars($message['name']); ?> (<?php echo htmlspecialchars($message['email']); ?>)<br>
                            Submitted: <?php echo date('M j, Y g:i A', strtotime($message['submitted_at'])); ?>
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
                        <h4>Admin Response:</h4>
                        <p><?php echo nl2br(htmlspecialchars($message['admin_response'])); ?></p>
                        <?php if ($message['resolved_at']): ?>
                            <small>Resolved at: <?php echo date('M j, Y g:i A', strtotime($message['resolved_at'])); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="message-actions">
                    <form class="response-form" method="POST">
                        <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                        <textarea name="admin_response" rows="3" placeholder="Enter your response..."><?php echo htmlspecialchars($message['admin_response'] ?? ''); ?></textarea>
                        <div>
                            <select name="status">
                                <option value="pending" <?php echo $message['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $message['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $message['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                            <button type="submit" class="submit-btn">Update Response</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($messages)): ?>
            <p>No messages found.</p>
        <?php endif; ?>

        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>" 
                   class="<?php echo $page === $i ? 'current' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        particlesJS('particles-js', {
            "particles": {
                "number": {
                    "value": 150,
                    "density": {
                        "enable": true,
                        "value_area": 800
                    }
                },
                "color": {
                    "value": "#ffdcdc"
                },
                "shape": {
                    "type": "circle",
                    "stroke": {
                        "width": 0,
                        "color": "#000000"
                    },
                },
                "opacity": {
                    "value": 0.5,
                    "random": true,
                },
                "size": {
                    "value": 5,
                    "random": true,
                },
                "line_linked": {
                    "enable": false
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "bottom",
                    "straight": false,
                    "out_mode": "out"
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "repulse"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                }
            }
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
