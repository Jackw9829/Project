<?php
session_start();
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>CodaQuest - Homepage</title>
    <?php include_once 'common_styles.php'; ?>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background-color: var(--background-color);
            color: var(--text-color);
            font-family: var(--font-family);
            position: relative;
            background-image: url('assets/grid-bg.png');
            background-size: 50px 50px;
            background-repeat: repeat;
        }
        
        /* Audio player styles moved to includes/music_player.php */
        
        #matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.15;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 6rem 20px 3rem;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .content-container {
            max-width: 1000px;
            width: 100%;
            padding: 2rem;
            background-color: var(--card-bg);
            box-shadow: var(--shadow);
            border: 4px solid var(--primary-color);
            position: relative;
            z-index: 1;
            margin: 0 auto;
            image-rendering: pixelated;
        }
        .content-container h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            margin-top: 1rem;
            letter-spacing: 2px;
            text-shadow: 3px 3px 0px rgba(0, 0, 0, 0.5);
            text-transform: uppercase;
        }
        

        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .action-btn {
            padding: 1rem 1.5rem;
            background-color: var(--primary-color);
            border: 4px solid var(--border-color);
            color: var(--text-color);
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: var(--shadow);
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 0 rgba(0, 0, 0, 0.3);
        }
        
        .action-btn:active {
            transform: translateY(0);
            box-shadow: var(--shadow);
        }
        
        a { text-decoration: none; }
        /* Header styles are now handled by includes/header.php and common_styles.php */
        /* Footer styles are now handled by includes/footer.php and common_styles.php */
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }
        
        .feature-card {
            background-color: rgba(var(--primary-color-rgb), 0.1);
            padding: 1.5rem;
            text-align: center;
            border: 4px solid var(--border-color);
            color: var(--text-color);
            transition: var(--transition);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 6px 0 rgba(0, 0, 0, 0.3);
        }
        .feature-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .feature-card p {
            color: var(--text-color);
            font-size: 0.8rem;
            line-height: 1.4;
        }
        
        .cta-section {
            text-align: center;
            margin: 2.5rem 0 1rem;
            padding: 2rem;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            border: 4px solid var(--border-color);
            box-shadow: var(--shadow);
        }
        
        .cta-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .cta-section p {
            font-size: 0.8rem;
            margin-bottom: 1.5rem;
        }
        @media screen and (max-width: 768px) {
            .features-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .action-btn { width: 100%; }
        }
        
        @media screen and (max-width: 480px) {
            .main-container { margin: 80px auto 20px; padding: 0 15px; }
            .content-container { padding: 1.5rem; }
            .content-container h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <?php include_once 'includes/music_player.php'; ?>
    
    <?php include_once 'includes/header.php'; ?>
    <div class="main-container">
        <div class="content-container">
            <h2><?php echo isset($_COOKIE['was_logged_in']) ? 'Welcome Back to CodaQuest' : 'Welcome to CodaQuest'; ?></h2>
            <div class="welcome-message" style="text-align: center;">
                Embark on a retro coding adventure with CodaQuest — where Python mastery meets pixel-powered fun!
                <p>Level up your skills through interactive tutorials, personalized learning paths, and gamified challenges.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <h3>Interactive Python Learning</h3>
                    <p>Practice with real coding problems and get instant feedback as you progress.</p>
                </div>
                <div class="feature-card">
                    <h3>Progress & Achievements</h3>
                    <p>Track your journey, earn badges, and climb the leaderboard as you conquer new concepts.</p>
                </div>
                <div class="feature-card">
                    <h3>Personalized Paths</h3>
                    <p>Enjoy content tailored to your interests and skill level, from fundamentals to advanced topics.</p>
                </div>
            </div>
            <div class="cta-section">
                <h3>Ready to Start Your Coding Quest?</h3>
                <p>Join our community and unlock your programming potential!</p>
                <div class="action-buttons">
                    <?php if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])): ?>
                        <a href="login.php" class="action-btn">Login</a>
                        <a href="signup.php" class="action-btn">Register</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="action-btn">Go to Dashboard</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="matrix-bg.js"></script>
    
    <!-- Background Music Script moved to includes/music_player.php -->
</body>
</html>
