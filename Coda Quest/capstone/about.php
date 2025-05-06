<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['student_id']) || isset($_SESSION['admin_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
?>
<!DOCTYPE html>
<html>
<head>
    <title>CodaQuest - About Us</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/capstone/common_styles.php'; ?>
    <style>
        /* Page-specific styles leveraging common CSS variables */
        #matrix-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
        }
        .about-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
            position: relative;
            z-index: 1;
        }
        .about-hero {
            background: var(--card-bg);
            padding: 4rem 2rem;
            text-align: center;
            border-radius: var(--border-radius);
            margin-bottom: 4rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            border: 2px solid var(--border-color);
        }
        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(var(--primary-color-rgb),0.1), rgba(var(--primary-color-rgb),0.1));
            z-index: -1;
        }
        .about-hero h1 {
            margin: 0 0 1.5rem;
            color: var(--primary-color);
            font-family: 'Press Start 2P', cursive;
            font-size: 2.5rem;
            letter-spacing: -1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .about-hero p {
            margin: 0;
            color: var(--text-color);
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        .about-section {
            margin-bottom: 4rem;
            background: var(--card-bg);
            padding: 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 2px solid var(--border-color);
        }
        .about-section h2 {
            color: var(--primary-color);
            margin: 0 0 2rem;
            font-size: 2rem;
            font-family: 'Press Start 2P', cursive;
            text-align: center;
            position: relative;
            padding-bottom: 1rem;
        }
        .about-section h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        .mission-vision {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        .mission, .vision {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--border-radius);
            border: 2px solid var(--border-color);
        }
        .about-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        .about-card {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 2px solid var(--border-color);
            text-align: center;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        .about-card:hover {
            transform: translateY(-5px);
            border-color: var(--secondary-color);
        }
        .member-initials {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            font-size: 1rem;
            line-height: 1;
        }
        .team-member-name {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: bold;
        }
        .team-member-role {
            display: inline-block;
            background-color: var(--primary-color);
            color: #fff;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.9rem;
            margin: 0.5rem 0;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        @media (max-width: 767px) {
            .about-container {
                margin-top: 70px;
            }
            .about-hero {
                padding: 3rem 1.5rem;
            }
            .about-hero h1 {
                font-size: 1.8rem;
            }
            .member-initials {
                width: 80px;
                height: 80px;
                font-size: 1.2rem;
            }
            .team-member-name {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>
    
    <?php include_once 'includes/header.php'; ?>

    <div class="about-container">
        <div class="about-hero pixel-border">
            <h1>Welcome to CodaQuest</h1>
            <p>Embark on an exciting journey of coding discovery where learning meets adventure. We're transforming how students experience and master programming through interactive, engaging, and gamified learning experiences.</p>
        </div>

        <div class="about-section pixel-border">
            <h2>Our Mission & Vision</h2>
            <div class="mission-vision">
                <div class="mission">
                    <h3>Our Mission</h3>
                    <p>To revolutionize programming education by creating an engaging, accessible, and enjoyable learning platform that empowers students to build strong coding foundations. Through innovative digital solutions and personalized learning paths, we aim to make every student's coding journey a success story.</p>
                </div>
                <div class="vision">
                    <h3>Our Vision</h3>
                    <p>To be the catalyst for a world where coding is not just learned, but loved. We envision CodaQuest as the go-to platform that transforms programming challenges into exciting opportunities, fostering critical thinking and problem-solving skills that last a lifetime.</p>
                </div>
            </div>
        </div>

        <div class="about-section pixel-border">
            <h2>Meet Our Team</h2>
            <div class="about-grid">
                <div class="about-card">
                    <div class="member-initials">MX</div>
                    <h3 class="team-member-name">Teoh Ming Xun</h3>
                    <p class="team-member-role">Team Leader</p>
                    <p>Leading the development and coordination of CodaQuest</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">LY</div>
                    <h3 class="team-member-name">Chen Ling Yau</h3>
                    <p class="team-member-role">Full-Stack Developer</p>
                    <p>Handling both frontend and backend development</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">JM</div>
                    <h3 class="team-member-name">Wong Jun Ming</h3>
                    <p class="team-member-role">System Architect</p>
                    <p>Designing robust system architecture and infrastructure</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">ZF</div>
                    <h3 class="team-member-name">Wong Zhen Feng</h3>
                    <p class="team-member-role">Quality Assurance</p>
                    <p>Ensuring high-quality and bug-free user experience</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">SQ</div>
                    <h3 class="team-member-name">Yeo Szy Qi</h3>
                    <p class="team-member-role">Content Specialist</p>
                    <p>Developing educational content and learning materials</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">EX</div>
                    <h3 class="team-member-name">Tan Ee Xin</h3>
                    <p class="team-member-role">UX Researcher</p>
                    <p>Conducting user research and improving user experience</p>
                </div>
            </div>
        </div>
    </div>

    <?php include_once 'includes/footer.php'; ?>

    <!-- Include matrix background script -->
    <script src="matrix-bg.js"></script>
</body>
</html>
