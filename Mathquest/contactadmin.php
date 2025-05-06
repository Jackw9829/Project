<?php
session_start();
require_once 'config/db_connect.php';

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Get form data
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            $submitted_at = date('Y-m-d H:i:s');
            
            // Basic validation
            $errors = [];
            if (empty($name)) $errors[] = "Name is required";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
            if (empty($subject)) $errors[] = "Subject is required";
            if (empty($message)) $errors[] = "Message is required";

            if (empty($errors)) {
                // Begin transaction
                $pdo->beginTransaction();

                try {
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_messages (name, email, subject, message, submitted_at, status, user_id) 
                        VALUES (:name, :email, :subject, :message, :submitted_at, 'pending', :user_id)
                    ");

                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'subject' => $subject,
                        'message' => $message,
                        'submitted_at' => $submitted_at,
                        'user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null
                    ]);

                    // Log the activity if user is logged in
                    if (isset($_SESSION['user_id'])) {
                        $activityStmt = $pdo->prepare("
                            INSERT INTO user_activity (user_id, activity_type, activity_details) 
                            VALUES (:user_id, 'contact_admin', :details)
                        ");
                        
                        $activityStmt->execute([
                            'user_id' => $_SESSION['user_id'],
                            'details' => "Submitted contact form: $subject"
                        ]);
                    }

                    // Commit transaction
                    $pdo->commit();

                    // Return success response for AJAX
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Message sent successfully! Admin will review and respond to your inquiry.']);
                    exit;
                } catch (PDOException $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    throw $e;
                }
            } else {
                // Return error response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'An error occurred while sending your message.']);
            exit;
        }
    }
    exit;
}

$is_admin = isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';

// Get user's information
$user_info = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching user info: " . $e->getMessage());
    }
}

$page_title = "Contact Admin";
require_once 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>MathQuest - Contact Admin</title>
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
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            background: #ffffff;
            position: relative;
            padding-top: 80px;
            padding-bottom: 60px;
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

        a {
            text-decoration: none;
        }

        /* Standard header styling for all pages */
        header, .header {
            background-color: #ffdcdc;
            height: 80px; /* Increased to 80px */
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

        /* Logo standardization */
        .logo {
            display: flex;
            align-items: center;
            height: 100%;
        }

        .logo img {
            height: 60px; /* Increased from 40px */
            margin-right: 10px;
        }

        .logo-text {
            font-size: 28px; /* Increased from 24px */
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

        /* Standard footer styling for all pages */
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 10;
            background-color: rgba(212, 224, 238, 0.95);
            backdrop-filter: blur(5px);
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

        .main-container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 20px;
            position: relative;
            z-index: 1;
            min-height: calc(100vh - 280px);
        }

        .content-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
            margin: 0 auto;
            max-width: 1000px;
            width: 95%;
            margin-bottom: 2rem;
        }

        .content-container h2 {
            color: #2c3e50;
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
            font-family: 'Tiny5', sans-serif;
            position: relative;
        }

        .content-container h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #ffdcdc, transparent);
            border-radius: 2px;
        }

        .form-container {
            padding: 15px;
        }

        .form-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 25px;
        }

        .form-group input,
        .form-group textarea {
            flex: 1;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ffdcdc;
            box-shadow: 0 0 5px rgba(255, 220, 220, 0.5);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            font-family: 'Tiny5', sans-serif;
        }

        .form-group label {
            min-width: 100px;
            font-family: 'Tiny5', sans-serif;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 192, 203, 0.3);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Tiny5', sans-serif;
            font-size: 1rem;
            color: #333;
        }

        @media screen and (max-width: 480px) {
            header, .header {
                padding: 0.8rem;
            }
            
            .main-container {
                padding: 15px;
                margin-top: 20px; /* Adjusted for smaller header on mobile */
            }

            .content-container {
                padding: 1.5rem;
            }

            .form-container {
                padding: 15px;
            }
        }

        .success-dialog-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .success-dialog {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 90%;
            position: relative;
            font-family: 'Tiny5', sans-serif;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .success-dialog h3 {
            color: #28a745;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .success-dialog p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .success-dialog button {
            background: #007bff;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Tiny5', sans-serif;
            transition: background-color 0.2s;
        }

        .success-dialog button:hover {
            background: #0056b3;
        }

        .success-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div id="particles-js"></div>
    <?php include 'includes/header.php'; ?>
    
    <!-- Add success dialog -->
    <div class="success-dialog-overlay" id="successDialog">
        <div class="success-dialog">
            <div class="success-icon">✓</div>
            <h3>Message Sent Successfully!</h3>
            <p>Thank you for your inquiry. Our admin team will review and respond as soon as possible.</p>
            <button onclick="closeSuccessDialog()">OK</button>
        </div>
    </div>

    <div class="main-container">
        <div class="content-container">
            <h2>Contact Admin</h2>
            <div class="form-container">
                <form id="contactForm" method="POST">
                    <div class="form-group">
                        <label for="name">Name:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_info['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" readonly required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject:</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message:</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    
                    <div class="form-group" style="display: flex; gap: 10px;">
                        <button type="submit" class="submit-btn">Send Message</button>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="my_inquiries.php" class="submit-btn" style="text-decoration: none; text-align: center;">My Inquiries</a>
                        <?php endif; ?>
                    </div>
                </form>
                <div id="message-status"></div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
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
                    "out_mode": "out"
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
        document.querySelector('#contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email');
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailRegex.test(email.value)) {
                alert('Please enter a valid email address');
                email.focus();
                return;
            }

            const formData = new FormData(this);

            fetch('contactadmin.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Show success dialog
                    document.getElementById('successDialog').style.display = 'flex';
                    this.reset();
                } else {
                    alert(result.errors ? result.errors.join('\n') : result.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });

        function closeSuccessDialog() {
            document.getElementById('successDialog').style.display = 'none';
            // Redirect after closing dialog
            const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
            if (isAdmin) {
                window.location.href = 'admin_messages.php';
            } else {
                window.location.href = 'my_inquiries.php';
            }
        }
    </script>
</body>
</html>