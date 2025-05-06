<?php
session_start();
require_once 'config/db_connect.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $name = trim($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm-password'];
        $dob = $_POST['dob'];
        $gender = $_POST['gender'];
        
        if (!$email) {
            throw new Exception('Invalid email address');
        }
        
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            throw new Exception('Phone number must be 10 or 11 digits');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already registered');
        }

        // Insert new teacher
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, dob, gender, user_type) VALUES (?, ?, ?, ?, ?, ?, 'teacher')");
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Execute and check if successful
        if (!$stmt->execute([$name, $email, $hashed_password, $phone, $dob, $gender])) {
            throw new Exception('Failed to create account. Please try again.');
        }

        // Get the new user's ID
        $user_id = $pdo->lastInsertId();

        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type'] = 'teacher';
        $_SESSION['name'] = $name;

        // Redirect to teacher dashboard
        header('Location: teacherdashboard.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MathQuest - Register As Teacher</title>
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
            color: #007bff;
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

        .login-container {
            max-width: 800px;
            width: 90%;
            margin: 2rem auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
        }

        .form-group label {
            min-width: 100px;
            color: #333;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .form-group input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #333;
            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.15);
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .login-btn {
            background: #c6e5d9;
            border: 2px solid #333;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: #333;
            font-size: 16px;
            font-family: 'Tiny5';
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            width: 100%;
            margin-top: 15px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: #333;
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .login-btn:active {
            transform: translateY(1px);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        button:hover {
            opacity: 0.9;
        }

        .text-link {
            font-family: 'Tiny5';
            font-size: 16px;
            text-decoration: none;
            color: #333;
            cursor: pointer;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
        }

        .text-link:hover {
            color: #0066cc;
            text-decoration: none;
        }

        select, input[type="date"] {
            font-family: 'tiny5';
            font-size: 14px;
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        select:focus, input[type="date"]:focus {
            outline: none;
            border-color: #333;
            box-shadow: 3px 3px 6px rgba(0, 0, 0, 0.15);
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
        }

        input[type="date"]::-webkit-datetime-edit {
            font-family: 'tiny5';
        }

        select option {
            font-family: 'tiny5';
        }
        input::placeholder,
        select::placeholder {
            font-family: 'tiny5';
        }

        ::-webkit-input-placeholder { 
            font-family: 'tiny5';
        }
        :-moz-placeholder { 
            font-family: 'tiny5';
        }
        ::-moz-placeholder { 
            font-family: 'tiny5';
        }
        :-ms-input-placeholder { 
            font-family: 'tiny5';
        }

        .form-actions {
            text-align: center;
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-actions span,
        .form-actions a {
            font-family: 'tiny5';
        }

        .form-row {
            display: flex;
            gap: 3rem;
            margin-bottom: 1.5rem;
        }

        .form-column {
            flex: 1;
        }

        .form-column .form-group {
            width: 100%;
        }

        .form-column .form-group:last-child {
            margin-bottom: 0;
        }

        .form-actions, 
        .login-btn {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        @media screen and (max-width: 768px) {
            .login-container {
                max-width: 95%;
                margin: 1rem auto;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-column {
                width: 100%;
            }

            .form-column:first-child .form-group:last-child {
                margin-bottom: 25px;
            }

            .form-group {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .form-group input,
            .form-group select {
                width: 100%;
            }

            .login-btn {
                width: 100%;
                max-width: none;
            }
        }

        @media screen and (max-width: 480px) {
            .login-container {
                max-width: 100%;
                padding: 1rem;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .header-right {
            margin-left: auto;
        }

        .teacher-register-btn {
            font-family: 'tiny5';
            background-color: transparent;
            border: 2px solid #333;
            color: #333;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .teacher-register-btn:hover {
            background-color: #333;
            color: white;
        }

        @media screen and (max-width: 768px) {
            .header {
                padding: 1rem;
            }
            
            .teacher-register-btn {
                padding: 6px 12px;
                font-size: 14px;
            }
        }

        @media screen and (max-width: 480px) {
            .header {
                padding: 0.8rem;
            }
            
            .teacher-register-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }

        .error-message {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="homepage.php" class="logo">
        <img src="mathquestlogo.png" alt="MathQuest Logo">
        <span class="logo-text">Mathquest</span>
    </a>
</header>

<main class="login-container">
    <h2>Register as Teacher</h2>
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form class="login-form" method="POST" action="registerteacher.php">
        <div class="form-row">
            <div class="form-column">
                <div class="form-group">
                    <label for="name">Name :</label>
                    <input type="text" id="name" name="name" 
                           placeholder="Enter your name"
                           required>
                </div>
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email" 
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           placeholder="Enter your email address"
                           title="Please enter a valid email address"
                           required>
                </div>
                <div class="form-group">
                    <label for="password">Password :</label>
                    <input type="password" id="password" name="password" 
                           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9!@#$%^&*]).{8,}"
                           placeholder="Enter your password"
                           title="Password must be at least 8 characters and contain at least one lowercase letter, one uppercase letter, and one number or special character"
                           required>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm Password :</label>
                    <input type="password" id="confirm-password" name="confirm-password" 
                           placeholder="Confirm your password"
                           required>
                </div>
            </div>
            <div class="form-column">
                <div class="form-group">
                    <label for="phone">Phone Number :</label>
                    <input type="tel" id="phone" name="phone" 
                           pattern="[0-9]{10,11}"
                           placeholder="Enter your phone number (10-11 digits)"
                           title="Please enter a valid phone number (10 or 11 digits)"
                           required>
                </div>
                <div class="form-group">
                    <label for="gender">Gender :</label>
                    <select id="gender" name="gender" required>
                        <option value="" disabled selected>Select your gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dob">DOB :</label>
                    <input type="date" id="dob" name="dob" 
                           placeholder="DD/MM/YYYY"
                           required>
                </div>
            </div>
        </div>
        <button type="submit" class="login-btn">Create Account</button>
    </form>
</main>

<footer>
    <div class="footer-left">
        <p>&copy; 2024 MathQuest. All rights reserved.</p>
        <a href="#" class="footer-links">About Us</a>
    </div>
    <div class="footer-right">
        <a href="contactadmin.php" class="footer-links">Admin Support</a>
    </div>
</footer>

<script>
document.querySelector('.login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const email = document.getElementById('email');
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!emailRegex.test(email.value)) {
        alert('Please enter a valid email address');
        email.focus();
        return;
    }

    const phone = document.getElementById('phone');
    const phoneRegex = /^[0-9]{10,11}$/;
    if (!phoneRegex.test(phone.value)) {
        alert('Please enter a valid phone number (10 or 11 digits)');
        phone.focus();
        return;
    }

    const password = document.getElementById('password');
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9!@#$%^&*]).{8,}$/;
    if (!passwordRegex.test(password.value)) {
        alert('Password must be at least 8 characters and contain at least one lowercase letter, one uppercase letter, and one number or special character');
        password.focus();
        return;
    }

    const confirmPassword = document.getElementById('confirm-password');
    if (password.value !== confirmPassword.value) {
        alert('Passwords do not match');
        confirmPassword.focus();
        return;
    }
    
    this.submit();
});

document.getElementById('phone').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
    
    if (this.value.length > 11) {
        this.value = this.value.slice(0, 11);
    }
    
    if (this.value.length < 10 || this.value.length > 11) {
        this.setCustomValidity('Please enter a valid phone number (10 or 11 digits)');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    let messages = [];
    
    if (password.length < 8) {
        messages.push('• Minimum of 8 characters are required');
    }
    if (!/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
        messages.push('• Must include both uppercase and lowercase letters');
    }
    if (!/[0-9!@#$%^&*]/.test(password)) {
        messages.push('• Must include at least one number or special character');
    }
    
    const message = messages.slice(0, 3).join('\n');
    
    this.setCustomValidity(message);
    this.title = message || 'Password meets requirements';
});
</script>
</body>
</html>