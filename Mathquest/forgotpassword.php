<?php
session_start();
require_once 'config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Email address not found in our system');
        }

        // Check if there's an existing valid token
        $stmt = $pdo->prepare("
            SELECT created_at 
            FROM password_reset_tokens 
            WHERE user_id = ? AND expires_at > CONVERT_TZ(NOW(), 'SYSTEM', '+08:00')
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user['user_id']]);
        $existing_token = $stmt->fetch();

        if ($existing_token) {
            // Check if the last token was created less than 15 minutes ago
            $created_at_utc = new DateTime($existing_token['created_at'], new DateTimeZone('UTC'));
            $created_at_local = $created_at_utc->setTimezone(new DateTimeZone('+08:00'));
            $now = new DateTime('now', new DateTimeZone('+08:00'));
            $diff = $now->diff($created_at_local);
            
            if ($diff->i < 15) { // 15 minutes cooldown
                $minutes_left = 15 - $diff->i;
                throw new Exception("Please wait {$minutes_left} minutes before requesting another password reset.");
            }
        }

        // Generate a new token
        $token = bin2hex(random_bytes(32)); // 64 characters long
        $expires_at = (new DateTime('now', new DateTimeZone('+08:00')))->modify('+1 hour')->format('Y-m-d H:i:s');

        // Delete any existing tokens for this user
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? OR expires_at <= CONVERT_TZ(NOW(), 'SYSTEM', '+08:00')");
        $stmt->execute([$user['user_id']]);

        // Insert new token
        $stmt = $pdo->prepare("
            INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, CONVERT_TZ(NOW(), 'SYSTEM', '+08:00'))
        ");
        $stmt->execute([$user['user_id'], $token, $expires_at]);

        // For demonstration, we'll just show the reset link
        // In production, this should be sent via email
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/rwdd/reset_password.php?token=" . $token;
        $success = "A password reset link has been generated. Please click the link below:<br><br>" . 
                  "<a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a><br><br>" .
                  "This link will expire in 1 hour.";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Forgot Password</title>
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

        .forgot-container {
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

        .forgot-container h2 {
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
            word-break: break-all;
        }

        .success-message a:hover {
            text-decoration: underline;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #34495e;
            font-size: 0.9rem;
            font-family: 'Tiny5', sans-serif;
        }

        .login-link a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .forgot-container {
                width: 100%;
                max-width: 600px;
                padding: 2rem;
            }
            
            .forgot-container h2 {http://localhost/rwdd/login.php
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .forgot-container {
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
        <div class="forgot-container">
            <h2>Reset Password</h2>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <form class="forgot-form" method="POST">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your email address">
                </div>
                <button type="submit" class="reset-btn">Send Reset Link</button>
            </form>
            <div class="login-link">
                Remember your password? <a href="login.php">Login here</a>
            </div>
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