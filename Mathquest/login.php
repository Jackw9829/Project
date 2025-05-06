<?php
session_start();
require_once 'config/db_connect.php';
$error = '';

// Check if user was previously logged in using cookie
$wasLoggedIn = isset($_COOKIE['was_logged_in']) && $_COOKIE['was_logged_in'] === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // First, get the user's data including their hashed password
        $stmt = $pdo->prepare("SELECT user_id, password, user_type FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify the password
        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            
            // Set cookie to remember this user has logged in before
            setcookie('was_logged_in', 'true', time() + (86400 * 30), '/'); // 30 days expiry
            
            // Log the login activity
            $logStmt = $pdo->prepare("
                INSERT INTO user_activity (user_id, activity_type, activity_details, activity_time) 
                VALUES (:user_id, 'login', 'User logged in', NOW())
            ");
            $logStmt->execute(['user_id' => $user['user_id']]);

            // Redirect based on user type
            if ($user['user_type'] === 'admin') {
                header('Location: admindashboard.php');
            } else if ($user['user_type'] === 'teacher') {
                header('Location: teacherdashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'Login failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
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

        .login-container {
            max-width: 800px;
            width: 90%;
            padding: 3rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .login-container h2 {
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

        .login-container h2::after {
            display: none;
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

        .login-btn {
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

        .login-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        .form-links {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
            margin-bottom: 2rem;
            font-family: 'Tiny5', sans-serif;
        }

        .form-links a {
            color: #2c3e50;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .form-links a:hover {
            color: #e74c3c;
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
            font-family: 'Tiny5';
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #34495e;
            font-size: 0.9rem;
            font-family: 'Tiny5', sans-serif;
        }

        .register-link a {
            color: #e74c3c;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 2rem;
            }

            .login-container h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="login-container">
            <h2><?php echo $wasLoggedIn ? 'Welcome Back' : 'Login to MathQuest'; ?></h2>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <form class="login-form" method="POST" action="login.php">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="form-links">
                        <a href="forgotpassword.php">Forgot Password?</a>
                    </div>
                </div>
                <button type="submit" class="login-btn">Log In</button>
            </form>
            <div class="register-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
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
                    }
                },
                "opacity": {
                    "value": 0.8,
                    "random": true,
                    "anim": {
                        "enable": true,
                        "speed": 1,
                        "opacity_min": 0.1,
                        "sync": false
                    }
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
                        "mode": "repulse"
                    },
                    "onclick": {
                        "enable": true,
                        "mode": "push"
                    },
                    "resize": true
                }
            },
            "retina_detect": true
        });
    </script>
</body>
</html>
