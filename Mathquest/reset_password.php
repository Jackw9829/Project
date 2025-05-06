<?php
session_start();
require_once 'config/db_connect.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Validate token
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if (!$token_data) {
            throw new Exception('Invalid or expired reset token. Please request a new password reset link.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        if (strlen($new_password) < 8) {
            throw new Exception('Password must be at least 8 characters long.');
        }

        if ($new_password !== $confirm_password) {
            throw new Exception('Passwords do not match.');
        }

        // Validate token and get user
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if (!$token_data) {
            throw new Exception('Invalid or expired reset token. Please request a new password reset link.');
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed_password, $token_data['user_id']]);

        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);

        $success = "Password has been successfully reset. You can now <a href='login.php'>login</a> with your new password.";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Reset Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 40px 20px;
        }

        .reset-container {
            max-width: 800px;
            width: 90%;
            padding: 3rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin: 0 auto;
        }

        .reset-container h2 {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
            border: none;
            border-bottom: none !important;
            text-decoration: none;
            position: relative;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            color: #2c3e50;
            font-family: 'Tiny5', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ffdcdc;
        }

        .reset-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            backdrop-filter: blur(5px);
        }

        .reset-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            padding: 0.5rem;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 4px;
        }

        .success-message {
            color: #27ae60;
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            padding: 0.5rem;
            background: rgba(39, 174, 96, 0.1);
            border-radius: 4px;
        }

        .success-message a {
            color: #2980b9;
            text-decoration: none;
        }

        .success-message a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .reset-container {
                width: 100%;
                max-width: 600px;
                padding: 2rem;
            }
            
            .reset-container h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .reset-container {
                width: 100%;
                padding: 1.5rem;
            }
            
            main {
                padding: 20px 10px;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="reset-container">
            <h2>Reset Password</h2>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php else: ?>
                <?php if (isset($_GET['token'])): ?>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                        <div class="form-group">
                            <label for="new_password">New Password:</label>
                            <input type="password" id="new_password" name="new_password" required 
                                   minlength="8" placeholder="Enter your new password">
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   minlength="8" placeholder="Confirm your new password">
                        </div>
                        <button type="submit" class="reset-btn">Reset Password</button>
                    </form>
                <?php else: ?>
                    <div class="error-message">No reset token provided. Please request a new password reset link.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
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
                    "value": "#ffc0cb"
                },
                "shape": {
                    "type": "circle"
                },
                "opacity": {
                    "value": 0.5,
                    "random": true
                },
                "size": {
                    "value": 3,
                    "random": true
                },
                "line_linked": {
                    "enable": false
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "bottom",
                    "random": true,
                    "straight": false,
                    "out_mode": "out",
                    "bounce": false
                }
            },
            "interactivity": {
                "detect_on": "canvas",
                "events": {
                    "onhover": {
                        "enable": true,
                        "mode": "bubble"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "repulse"
                    },
                    "resize": true
                }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>
