<?php
session_start();
require_once 'config/db_connect.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validate inputs
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm-password'];
        $name = trim($_POST['name']);
        
        if (!$email) {
            throw new Exception('Invalid email address');
        }

        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            throw new Exception('Name can only contain letters and spaces');
        }
        
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            throw new Exception('Phone number must be 10 or 11 digits');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email already registered');
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, dob, gender, user_type) 
            VALUES (?, ?, ?, ?, ?, ?, 'student')
        ");
        
        $stmt->execute([
            $name,
            $email,
            $hashed_password,
            $phone,
            $_POST['dob'],
            $_POST['gender']
        ]);
        
        // Create leaderboard entry
        $user_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO leaderboard (user_id) VALUES (?)");
        $stmt->execute([$user_id]);
        
        // Redirect to login with success message
        header('Location: login.php?registered=1');
        exit();
        
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Register</title>
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
            font-family: 'Tiny5', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
            padding-top: 120px;
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
            align-items: flex-start;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 40px 20px;
        }

        .register-container {
            max-width: 800px;
            width: 90%;
            padding: 3rem;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .register-container h2 {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 1rem;
            color: #2c3e50;
            font-family: 'Tiny5', sans-serif;
            transition: border-color 0.3s ease;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #ffdcdc;
        }

        .register-btn {
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

        .register-btn:hover {
            background: rgba(255, 192, 203, 0.5);
            transform: translateY(-2px);
        }

        .error-message {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
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
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 2rem;
            }

            .register-container h2 {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>

    <main>
        <div class="register-container">
            <h2>Create Account</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form class="register-form" method="POST" action="register.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name:</label>
                        <input type="text" id="name" name="name" required pattern="[A-Za-z\s]+" 
                               placeholder="Enter your full name"
                               title="Only letters and spaces are allowed" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" 
                               pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                               placeholder="Enter your email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               title="Please enter a valid email address"
                               required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number:</label>
                        <input type="tel" id="phone" name="phone" required pattern="[0-9]{10,11}" 
                               placeholder="Enter your phone number"
                               title="Phone number must be valid" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth:</label>
                        <input type="date" id="dob" name="dob" 
                               placeholder="DD/MM/YYYY"
                               value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>"
                               required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled <?php echo !isset($_POST['gender']) ? 'selected' : ''; ?>>Select your gender</option>
                        <option value="male" <?php echo isset($_POST['gender']) && $_POST['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo isset($_POST['gender']) && $_POST['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" 
                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9!@#$%^&*]).{8,}"
                               placeholder="Enter your password"
                               title="Password must be at least 8 characters and contain at least one lowercase letter, one uppercase letter, and one number or special character"
                               required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm Password:</label>
                        <input type="password" id="confirm-password" name="confirm-password" 
                               placeholder="Confirm your password"
                               required>
                    </div>
                </div>
                <button type="submit" class="register-btn">Register</button>
            </form>
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
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

        // Add input validation for phone numbers
        document.getElementById('phone').addEventListener('input', function(e) {
            // Remove any non-digit characters
            this.value = this.value.replace(/\D/g, '');
            
            // Limit to 11 digits
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
        });

        // Add input validation for name
        document.getElementById('name').addEventListener('input', function(e) {
            // Remove any characters that aren't letters or spaces
            this.value = this.value.replace(/[^A-Za-z\s]/g, '');
        });
    </script>
</body>
</html>
