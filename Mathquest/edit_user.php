<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';
$user = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $user_type = $_POST['user_type'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($user_type)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
        $error = 'Phone number must be 10-11 digits';
    } else {
        try {
            // Check if email already exists for other users
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Email already exists';
            } else {
                // Update user
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, phone = ?, user_type = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$name, $email, $phone, $user_type, $user_id]);
                
                $success = 'User updated successfully';
                
                // Log the activity
                $stmt = $pdo->prepare("
                    INSERT INTO user_activity (user_id, activity_type, activity_details, activity_time)
                    VALUES (:admin_id, 'user_update', :details, NOW())
                ");
                $stmt->execute([
                    'admin_id' => $_SESSION['user_id'],
                    'details' => "Updated user #$user_id"
                ]);
            }
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
            $error = 'An error occurred while updating the user';
        }
    }
}

// Fetch user data
if (isset($_GET['user_id']) || isset($_POST['user_id'])) {
    $user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $_POST['user_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            header('Location: manageusers.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error: " . $e->getMessage());
        $error = 'An error occurred while loading user data';
    }
} else {
    header('Location: manageusers.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Edit User</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tiny5', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            min-height: 100vh;
            position: relative;
        }

        #particles-js {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }

        .container {
            max-width: 800px;
            margin: 100px auto 60px;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 2rem;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background-color: rgba(255, 230, 230, 0.9);
            color: #cc0000;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .success-message {
            background-color: rgba(230, 255, 230, 0.9);
            color: #006600;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 1.1rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ffc0cb;
            box-shadow: 0 0 0 2px rgba(255, 192, 203, 0.2);
        }

        .btn-container {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .submit-btn,
        .cancel-btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn {
            background-color: #ffc0cb;
            color: #333;
        }

        .cancel-btn {
            background-color: #f5f5f5;
            color: #333;
        }

        .submit-btn:hover,
        .cancel-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .submit-btn:hover {
            background-color: #ffb0bb;
        }

        .cancel-btn:hover {
            background-color: #e5e5e5;
        }

        @media (max-width: 768px) {
            .container {
                margin: 80px 20px 40px;
                padding: 1.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .btn-container {
                flex-direction: column;
            }

            .submit-btn,
            .cancel-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div id="particles-js"></div>

    <div class="container">
        <h1 class="page-title">Edit User</h1>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">
            
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">Phone:</label>
                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            </div>

            <div class="form-group">
                <label for="user_type">User Type:</label>
                <select id="user_type" name="user_type" required>
                    <option value="student" <?php echo $user['user_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo $user['user_type'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                    <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>

            <div class="btn-container">
                <button type="submit" class="submit-btn">Update User</button>
                <a href="manageusers.php" class="cancel-btn">Cancel</a>
            </div>
        </form>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        particlesJS.load('particles-js', 'particles.json', function() {
            console.log('particles.js loaded');
        });
    </script>
</body>
</html>
