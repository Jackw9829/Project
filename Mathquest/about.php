<?php
session_start();
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - About Us</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            font-family: 'Poppins', sans-serif;
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

        .about-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .about-hero {
            background: rgba(255, 192, 203, 0.3);
            padding: 4rem 2rem;
            text-align: center;
            border-radius: 20px;
            margin-bottom: 4rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            position: relative;
            overflow: hidden;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 220, 220, 0.3) 0%, rgba(255, 255, 255, 0.3) 100%);
            z-index: -1;
        }

        .about-hero h1 {
            margin: 0 0 1.5rem;
            color: #2c3e50;
            font-size: 4rem;
            font-weight: 700;
            letter-spacing: -1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .about-hero p {
            margin: 0;
            color: #34495e;
            font-size: 1.6rem;
            font-weight: 400;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .about-section {
            margin-bottom: 4rem;
            background: rgba(255, 255, 255, 0.9);
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
        }

        .about-section h2 {
            color: #2c3e50;
            margin: 0 0 2rem;
            font-size: 2.5rem;
            font-weight: 600;
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
            background: linear-gradient(90deg, #ffdcdc, #ffbdbd);
            border-radius: 2px;
        }

        .mission-vision {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .mission, .vision {
            background: rgba(255, 255, 255, 0.9);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .mission:hover, .vision:hover {
            transform: translateY(-5px);
        }

        .mission h3, .vision h3 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .mission h3::after, .vision h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #ffdcdc;
            border-radius: 2px;
        }

        .mission p, .vision p {
            color: #34495e;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 0;
        }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3rem;
        }

        .about-grid .about-card:last-child {
            grid-column: 2;
        }

        .about-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
        }

        .about-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            border-color: #ffdcdc;
        }

        .member-initials {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, #ffdcdc 0%, #ffbdbd 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            color: #2c3e50;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .team-member-name {
            color: #2c3e50;
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
        }

        .team-member-role {
            color: #596775;
            font-size: 1rem;
            font-weight: 500;
            margin: 0 0 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .about-card p:last-child {
            color: #596775;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 1200px) {
            .about-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 991px) {
            .about-hero h1 {
                font-size: 3rem;
            }
            .about-hero p {
                font-size: 1.2rem;
            }
            .about-section {
                padding: 2rem;
            }
            .about-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .about-grid .about-card:last-child {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 767px) {
            .about-container {
                margin-top: 70px;
            }
            .about-hero {
                padding: 3rem 1.5rem;
            }
            .about-hero h1 {
                font-size: 2.5rem;
            }
            .about-hero p {
                font-size: 1.1rem;
            }
            .mission-vision {
                grid-template-columns: 1fr;
            }
            .about-section h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .about-container {
                padding: 0 15px;
            }
            .about-hero {
                padding: 2rem 1rem;
                margin-bottom: 2rem;
            }
            .about-hero h1 {
                font-size: 2rem;
            }
            .about-section {
                padding: 1.5rem;
                margin-bottom: 2rem;
            }
            .about-grid {
                grid-template-columns: 1fr;
            }
            .about-grid .about-card:last-child {
                grid-column: 1;
            }
            .member-initials {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            .team-member-name {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>

    <div class="about-container">
        <div class="about-hero">
            <h1>Welcome to MathQuest</h1>
            <p>Embark on an exciting journey of mathematical discovery where learning meets adventure. We're transforming how students experience and master mathematics through interactive, engaging, and personalized learning experiences.</p>
        </div>

        <div class="about-section">
            <h2>Our Mission & Vision</h2>
            <div class="mission-vision">
                <div class="mission">
                    <h3>Our Mission</h3>
                    <p>To revolutionize mathematics education by creating an engaging, accessible, and enjoyable learning platform that empowers students to build strong mathematical foundations. Through innovative digital solutions and personalized learning paths, we aim to make every student's mathematical journey a success story.</p>
                </div>
                <div class="vision">
                    <h3>Our Vision</h3>
                    <p>To be the catalyst for a world where mathematics is not just learned, but loved. We envision MathQuest as the go-to platform that transforms mathematical challenges into exciting opportunities, fostering critical thinking and problem-solving skills that last a lifetime.</p>
                </div>
            </div>
        </div>

        <div class="about-section">
            <h2>Meet Our Team</h2>
            <div class="about-grid">
                <div class="about-card">
                    <div class="member-initials">CLY</div>
                    <h3 class="team-member-name">Chen Ling Yau</h3>
                    <p class="team-member-role">Team Leader</p>
                    <p>Leading the development and coordination of MathQuest</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">TMX</div>
                    <h3 class="team-member-name">Teoh Ming Xun</h3>
                    <p class="team-member-role">Backend Developer</p>
                    <p>Handling server-side logic and database management</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">TEX</div>
                    <h3 class="team-member-name">Tan Ee Xin</h3>
                    <p class="team-member-role">Frontend Developer</p>
                    <p>Creating intuitive user interfaces and experiences</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">WJM</div>
                    <h3 class="team-member-name">Wong Jun Ming</h3>
                    <p class="team-member-role">System Architect</p>
                    <p>Designing robust system architecture and infrastructure</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">WZF</div>
                    <h3 class="team-member-name">Wong Zhen Feng</h3>
                    <p class="team-member-role">Quality Assurance</p>
                    <p>Ensuring high-quality and bug-free user experience</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">ZMC</div>
                    <h3 class="team-member-name">Zoe Marie Chor Shu En</h3>
                    <p class="team-member-role">Content Specialist</p>
                    <p>Developing educational content and learning materials</p>
                </div>
                <div class="about-card">
                    <div class="member-initials">VA</div>
                    <h3 class="team-member-name">Vishalleni A/P Arumugam</h3>
                    <p class="team-member-role">UX Researcher</p>
                    <p>Conducting user research and improving user experience</p>
                </div>
            </div>
        </div>
    </div>

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
                    },
                },
                "opacity": {
                    "value": 0.5,
                    "random": true,
                },
                "size": {
                    "value": 5,
                    "random": true,
                },
                "line_linked": {
                    "enable": false
                },
                "move": {
                    "enable": true,
                    "speed": 2,
                    "direction": "bottom",
                    "straight": false,
                    "out_mode": "out"
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
                }
            }
        });
    </script>
</body>
</html>