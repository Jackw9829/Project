<?php
session_start();
require_once 'config/db_connect.php';

$page_title = "Manage Users";

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

try {
    // Fetch all users with their roles
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, phone, user_type, created_at 
        FROM users 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while loading the users.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MathQuest - <?php echo $page_title; ?></title>
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
            max-width: 1200px;
            width: 95%;
            margin: 0 auto 2rem;
            position: relative;
            z-index: 1;
            padding: 0 1rem;
        }

        .page-title-container {
            text-align: center;
            margin: 4rem auto 4rem;
            position: relative;
            z-index: 1;
            padding-top: 2rem;
        }

        .page-title {
            font-size: 3rem;
            color: #2c3e50;
            margin: 0;
            padding: 0;
            position: relative;
            display: inline-block;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #ffdcdc, transparent);
            border-radius: 2px;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-top: 1rem;
            font-weight: normal;
        }

        .users-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        .user-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 2px solid #ffdcdc;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }

        .user-info {
            display: grid;
            gap: 1rem;
        }

        .user-name {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 0.5rem;
            border-bottom: 2px solid #ffdcdc;
            padding-bottom: 0.5rem;
        }

        .user-meta {
            display: grid;
            gap: 0.8rem;
            color: #666;
        }

        .meta-label {
            font-weight: bold;
            color: #333;
        }

        .user-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            justify-content: center;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Tiny5';
            font-size: 1rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #333;
            min-width: 100px;
        }

        .edit-btn {
            background: #ffdcdc;
            color: #333;
        }

        .delete-btn {
            background: #ff9999;
            color: #333;
        }

        .action-btn:hover {
            background: #333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 2rem;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            border: 2px solid #ffdcdc;
        }

        .modal-content h3 {
            margin-bottom: 1.5rem;
            color: #333;
            font-size: 1.5rem;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .modal .confirm-btn,
        .modal .cancel-btn {
            padding: 8px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Tiny5';
            font-size: 1rem;
            border: 2px solid #333;
            min-width: 100px;
            transition: all 0.3s ease;
        }

        .modal .confirm-btn {
            background: #ff9999;
            color: #333;
        }

        .modal .cancel-btn {
            background: #ffdcdc;
            color: #333;
        }

        .modal .confirm-btn:hover,
        .modal .cancel-btn:hover {
            background: #333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin: 1rem auto;
                padding: 0 1rem;
            }

            .users-list {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .user-card {
                padding: 1rem;
            }

            .user-actions {
                flex-direction: column;
            }

            .action-btn {
                width: 100%;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }

            .modal-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .modal .confirm-btn,
            .modal .cancel-btn {
                width: 100%;
            }

            .page-title {
                font-size: 2.5rem;
            }
            .page-subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div id="particles-js"></div>
    
    <div class="page-title-container">
        <h1 class="page-title"><?php echo $page_title; ?></h1>
        <p class="page-subtitle">View and manage system users</p>
    </div>

    <div class="dashboard-container">
        <div class="users-list">
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif (empty($users)): ?>
                <p class="no-users">No users found.</p>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="user-card">
                        <div class="user-info">
                            <h3 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <div class="user-meta">
                                <p>
                                    <span class="meta-label">Email:</span> 
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </p>
                                <p>
                                    <span class="meta-label">Phone:</span> 
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </p>
                                <p>
                                    <span class="meta-label">Role:</span> 
                                    <?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>
                                </p>
                                <p>
                                    <span class="meta-label">Joined:</span> 
                                    <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        <div class="user-actions">
                            <a href="edit_user.php?user_id=<?php echo $user['user_id']; ?>" class="action-btn edit-btn">Edit</a>
                            <button onclick="deleteUser(<?php echo $user['user_id']; ?>)" class="action-btn delete-btn">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Delete User '<span id="deleteUserName"></span>'?</h3>
            <p style="margin-bottom: 1.5rem; color: #ff4444;">This Process Can't be Undone</p>
            <div class="modal-buttons">
                <button class="confirm-btn">Yes</button>
                <button class="cancel-btn">No</button>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded');
        });

        let userToDelete = null;
        let userNameToDelete = null;

        function deleteUser(userId) {
            userToDelete = userId;
            // Find the user card and get the user's name
            const userCard = event.target.closest('.user-card');
            userNameToDelete = userCard.querySelector('.user-name').textContent;
            document.getElementById('deleteUserName').textContent = userNameToDelete;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        document.querySelector('#deleteModal .cancel-btn').addEventListener('click', function() {
            document.getElementById('deleteModal').style.display = 'none';
            userToDelete = null;
            userNameToDelete = null;
        });

        document.querySelector('#deleteModal .confirm-btn').addEventListener('click', async function() {
            if (!userToDelete) return;
            
            try {
                const response = await fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `user_id=${userToDelete}`
                });

                const result = await response.json();
                
                if (result.success) {
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to delete user');
                }
            } catch (error) {
                alert('An error occurred while deleting the user');
            } finally {
                document.getElementById('deleteModal').style.display = 'none';
                userToDelete = null;
                userNameToDelete = null;
            }
        });
    </script>
</body>
</html>