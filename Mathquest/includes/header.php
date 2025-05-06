<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MathQuest - <?php echo isset($page_title) ? $page_title : 'Home'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tiny5&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tiny5';
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 80px;
        }

        a {
            text-decoration: none;
        }

        header, .header {
            background-color: #ffdcdc;
            min-height: 80px;
            padding: 10px 20px;
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
            flex-wrap: wrap;
        }

        .logo {
            display: flex;
            align-items: center;
            height: 60px;
        }

        .logo img {
            height: 100%;
            margin-right: 10px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        .nav-btn {
            color: #666;
            transition: all 0.3s ease;
            font-size: 20px;
            padding: 8px 16px;
            margin: 5px;
            border-radius: 6px;
            background: transparent;
            white-space: nowrap;
        }

        .nav-btn:hover {
            background: #333;
            color: #fff;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 10px;
            color: #333;
            transition: all 0.3s ease;
            border-radius: 6px;
        }

        .menu-toggle:hover {
            background-color: rgba(51, 51, 51, 0.1);
            transform: scale(1.1);
        }

        .menu-toggle:active {
            background-color: rgba(51, 51, 51, 0.2);
            transform: scale(0.95);
        }

        .menu-toggle.active {
            background-color: rgba(51, 51, 51, 0.15);
        }

        @media (max-width: 1024px) {
            .nav-btn {
                font-size: 18px;
                padding: 6px 12px;
            }

            .logo-text {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            header, .header {
                padding: 10px;
            }

            .logo img {
                height: 50px;
            }

            .logo-text {
                font-size: 20px;
            }

            .nav-btn {
                font-size: 16px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 640px) {
            .menu-toggle {
                display: block;
            }

            .header-right {
                display: none;
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                padding: 10px 0;
            }

            .header-right.active {
                display: flex;
            }

            .nav-btn {
                text-align: center;
                margin: 5px 10px;
            }

            header, .header {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .logo {
                flex: 1;
            }
        }
    </style>
    <?php if(isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <header class="header">
        <a href="homepage.php" class="logo">
            <img src="mathquestlogo.png" alt="MathQuest Logo">
            <span class="logo-text">MathQuest</span>
        </a>
        <button class="menu-toggle" onclick="toggleMenu()">☰</button>
        <div class="header-right">
            <?php if(isset($_SESSION['user_id']) && isset($_SESSION['user_type'])): ?>
                <?php if($_SESSION['user_type'] === 'admin'): ?>
                    <a href="admindashboard.php" class="nav-btn">Dashboard</a>
                    <a href="manageusers.php" class="nav-btn">Manage Users</a>
                    <a href="logs.php" class="nav-btn">User Logs</a>
                    <a href="leaderboard.php" class="nav-btn">Leaderboard</a>
                <?php elseif($_SESSION['user_type'] === 'teacher'): ?>
                    <a href="teacherdashboard.php" class="nav-btn">Dashboard</a>
                    <a href="quizcreation.php" class="nav-btn">Create Quiz</a>
                    <a href="leaderboard.php" class="nav-btn">Leaderboard</a>
                <?php elseif($_SESSION['user_type'] === 'student'): ?>
                    <a href="dashboard.php" class="nav-btn">Dashboard</a>
                    <a href="leaderboard.php" class="nav-btn">Leaderboard</a>
                <?php endif; ?>
                <a href="profile.php" class="nav-btn">Profile</a>
                <a href="logout.php" class="nav-btn">Logout</a>
            <?php else: ?>
                <a href="login.php" class="nav-btn">Login</a>
                <a href="register.php" class="nav-btn">Register</a>
            <?php endif; ?>
        </div>
    </header>

    <script>
        function toggleMenu() {
            const headerRight = document.querySelector('.header-right');
            const menuToggle = document.querySelector('.menu-toggle');
            headerRight.classList.toggle('active');
            menuToggle.classList.toggle('active');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            const headerRight = document.querySelector('.header-right');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (!event.target.closest('.header-right') && 
                !event.target.closest('.menu-toggle') && 
                headerRight.classList.contains('active')) {
                headerRight.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
    </script>
    <main>
