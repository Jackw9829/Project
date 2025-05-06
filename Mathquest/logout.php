<?php
session_start();
require_once 'config/db_connect.php';

// Log the logout activity
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_activity 
            (user_id, activity_type, activity_details, activity_time) 
            VALUES 
            (:user_id, 'logout', 'User logged out', NOW())
        ");
        
        $stmt->execute([
            'user_id' => $_SESSION['user_id']
        ]);
        
    } catch (PDOException $e) {
        error_log("Logout Error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Logging Out</title>
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

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 40px 20px;
        }

        .logout-container {
            max-width: 800px;
            width: 90%;
            padding: 3rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .logout-message {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .back-to-login {
            display: inline-block;
            text-decoration: none;
            padding: 0.8rem 2rem;
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

        .back-to-login:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        h2 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }

        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            z-index: 10;
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

        @media (max-width: 576px) {
            .logout-container {
                width: 95%;
                padding: 2rem;
            }

            h2 {
                font-size: 2rem;
            }

            .logout-message {
                font-size: 1rem;
            }

            .back-to-login {
                padding: 10px 25px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="logout-container">
            <h2>Goodbye!</h2>
            <p class="logout-message">You have been successfully logged out.<br>Thank you for using MathQuest!</p>
            <a href="login.php" class="back-to-login">Back to Login</a>
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
                    "value": "#ffdcdc"
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
    <script>
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
</body>
</html>
