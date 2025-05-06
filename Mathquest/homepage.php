<?php
session_start();
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Homepage</title>
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
            font-family: 'Tiny5';
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
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

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 20px;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .content-container {
            max-width: 1000px;
            width: 100%;
            padding: 3rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
            margin: 0 auto;
        }

        .content-container h2 {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 2rem;
            color: #2c3e50;
            font-size: 1.2rem;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-btn {
            padding: 1rem 2rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5';
            backdrop-filter: blur(5px);
            text-decoration: none;
        }

        .action-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        a {
            text-decoration: none;
        }

        header, .header {
            background-color: #ffdcdc;
            height: 80px;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            height: 100%;
        }

        .logo img {
            height: 60px;
            margin-right: 10px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .header-links {
            display: flex;
            gap: 20px;
            font-size: 20px;
        }

        .header-links a {
            color: #666;
            transition: color 0.3s ease;
        }

        .header-links a:hover {
            color: #007bff;
        }

        footer {
            background-color: #d4e0ee;
            height: 80px;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
            margin-top: auto;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        .footer-links a {
            color: #333;
            transition: color 0.3s ease;
            font-size: 20px;
            text-decoration: none;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .footer-links a:hover {
            color: #ffcaca;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .feature-card {
            background: rgba(255, 192, 203, 0.1);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .feature-card p {
            color: #666;
            font-size: 1rem;
            line-height: 1.4;
        }

        .cta-section {
            text-align: center;
            margin: 2rem 0;
            padding: 2rem;
            background: rgba(255, 192, 203, 0.1);
            border-radius: 15px;
        }

        .cta-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .create-quiz-container {
            text-align: center;
            margin: 2rem 0;
        }

        .create-quiz-btn {
            padding: 1rem 2rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 8px;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5';
            backdrop-filter: blur(5px);
            text-decoration: none;
        }

        .create-quiz-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            header, .header {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .logo img {
                height: 50px;
            }

            footer {
                height: auto;
                min-height: 80px;
                padding: 10px 20px;
            }

            .footer-left, .footer-right {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
        }

        @media screen and (max-width: 768px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media screen and (max-width: 480px) {
            .header {
                padding: 0.8rem;
            }
            
            .main-container {
                margin: 20px auto;
                padding: 0 15px;
            }

            .content-container {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-container">
        <div class="content-container">
            <h2><?php echo isset($_COOKIE['was_logged_in']) ? 'Welcome Back to MathQuest' : 'Welcome to MathQuest'; ?></h2>
            
            <div class="welcome-text">
                <p>Embark on an exciting journey of mathematical discovery with MathQuest - where learning meets adventure!</p>
                <p>Master mathematics through interactive challenges, personalized learning paths, and engaging problem-solving experiences.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <h3>Interactive Learning</h3>
                    <p>Engage with dynamic math problems and receive instant feedback to enhance your understanding.</p>
                </div>
                <div class="feature-card">
                    <h3>Progress Tracking</h3>
                    <p>Monitor your growth with detailed progress reports and achievement badges.</p>
                </div>
                <div class="feature-card">
                    <h3>Personalized Path</h3>
                    <p>Learn at your own pace with customized content tailored to your skill level.</p>
                </div>
            </div>

            <div class="cta-section">
                <h3>Ready to Start Your Math Journey?</h3>
                <p>Join our community of learners and discover the joy of mathematics!</p>
                <div class="action-buttons">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="action-btn">Login</a>
                        <a href="register.php" class="action-btn">Register</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="action-btn">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
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