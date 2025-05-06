<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user's messages with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_messages WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $limit);

    // Get messages for current page
    $stmt = $pdo->prepare("
        SELECT * FROM admin_messages 
        WHERE user_id = ? 
        ORDER BY submitted_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $_SESSION['user_id']);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while retrieving your messages.";
}

$page_title = "My Inquiries";
require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inquiries - MathQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Tiny5', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
            padding-top: 80px;
            padding-bottom: 60px;
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

        .inquiries-container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 5rem auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .message-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .message-meta {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .message-content {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .admin-response {
            margin-top: 1.5rem;
            padding: 1rem;
            background: #e8f4ff;
            border-radius: 4px;
        }

        .admin-response h4 {
            color: #004085;
            margin: 0 0 0.5rem 0;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
            text-decoration: none;
        }

        .pagination a.current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .no-messages {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            margin-top: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .submit-btn {
            width: auto;
            padding: 1rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            color: #333;
        }

        .submit-btn:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>
    
    <div class="inquiries-container">
        <div class="page-header">
            <h1>My Inquiries</h1>
            <a href="contactadmin.php" class="submit-btn" style="text-decoration: none;">New Inquiry</a>
        </div>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-card">
                    <div class="message-header">
                        <div>
                            <h3><?php echo htmlspecialchars($message['subject']); ?></h3>
                            <div class="message-meta">
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
                </div>
            <?php endforeach; ?>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="<?php echo $page === $i ? 'current' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-messages">
                <p>You haven't submitted any inquiries yet.</p>
                <p>If you need help or have questions, click the "New Inquiry" button above.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
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
                },
                "opacity": {
                    "value": 0.5,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
                },
                "size": {
                    "value": 5,
                    "random": true,
                    "anim": {
                        "enable": false,
                        "speed": 40,
                        "size_min": 0.1,
                        "sync": false
                    }
                },
                "line_linked": {
                    "enable": false
                },
                "move": {
                    "enable": true,
                    "speed": 3,
                    "direction": "bottom",
                    "random": false,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false,
                    "attract": {
                        "enable": false,
                        "rotateX": 600,
                        "rotateY": 1200
                    }
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
                    "resize": true
                },
                "modes": {
                    "repulse": {
                        "distance": 100,
                        "duration": 0.4
                    },
                    "push": {
                        "particles_nb": 4
                    }
                }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>
