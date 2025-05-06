<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    // Redirect to login page if not logged in
    header("Location: login.php?redirect=contact_admin.php");
    exit();
}

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
                // Get database connection
                $pdo = getDbConnection();
                if (!$pdo) {
                    throw new Exception("Database connection failed");
                }
                
                // Begin transaction
                $pdo->beginTransaction();

                try {
                    // Determine student_id and admin_id
                    $student_id = null;
                    $admin_id = null;
                    
                    if (isset($_SESSION['student_id'])) {
                        $student_id = $_SESSION['student_id'];
                    } elseif (isset($_SESSION['admin_id'])) {
                        $admin_id = $_SESSION['admin_id'];
                    }
                    
                    // Insert into database
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_messages (name, email, subject, message, submitted_at, status, student_id, admin_id) 
                        VALUES (:name, :email, :subject, :message, :submitted_at, 'pending', :student_id, :admin_id)
                    ");

                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'subject' => $subject,
                        'message' => $message,
                        'submitted_at' => $submitted_at,
                        'student_id' => $student_id,
                        'admin_id' => $admin_id
                    ]);

                    // Log the activity if user is logged in and the table exists
                    if (($student_id !== null || $admin_id !== null)) {
                        try {
                            // Check if user_activity table exists
                            $tableCheckStmt = $pdo->prepare("SHOW TABLES LIKE 'user_activity'");
                            $tableCheckStmt->execute();
                            $tableExists = $tableCheckStmt->rowCount() > 0;
                            
                            if ($tableExists) {
                                // Determine which ID to use
                                $activity_user_id = $student_id !== null ? $student_id : $admin_id;
                                $activity_user_type = $student_id !== null ? 'student' : 'admin';
                                
                                $activityStmt = $pdo->prepare("
                                    INSERT INTO user_activity (user_id, activity_type, activity_details) 
                                    VALUES (:user_id, 'contact_admin', :details)
                                ");
                                
                                $activityStmt->execute([
                                    'user_id' => $activity_user_id,
                                    'details' => "Submitted contact form: $subject"
                                ]);
                            } else {
                                // Table doesn't exist, just log a message
                                error_log("Note: user_activity table doesn't exist, skipping activity logging");
                            }
                        } catch (PDOException $activityError) {
                            // Just log the error but don't stop the process
                            error_log("Error checking/logging activity: " . $activityError->getMessage());
                            // Continue with the process, don't throw the exception
                        }
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
                    // Log the error for debugging
                    error_log("Database error in contact_admin.php: " . $e->getMessage());
                    // Return a more specific error message
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                    exit;
                }
            } else {
                // Return error response for AJAX
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Exception in contact_admin.php: " . $e->getMessage());
            // Return a more detailed error message
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'An error occurred while sending your message.', 
                'error_details' => $e->getMessage()
            ]);
            exit;
        }
    }
    exit;
}

$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Get user's information
$user_info = [];
if (isset($_SESSION['student_id'])) {
    try {
        // Get database connection
        $pdo = getDbConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT username, email FROM students WHERE student_id = ?");
            $stmt->execute([$_SESSION['student_id']]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching student info: " . $e->getMessage());
    }
} elseif (isset($_SESSION['admin_id'])) {
    try {
        // Get database connection
        $pdo = getDbConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT username, email FROM admins WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching admin info: " . $e->getMessage());
    }
}

$pageTitle = "Contact Admin";
$additionalStyles = '
<style>
    /* Matrix Background */
    #matrix-bg {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        opacity: 0.1;
    }
    
    .contact-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .form-container {
        background-color: var(--card-bg);
        border: 4px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: 30px;
        box-shadow: var(--shadow);
        margin-bottom: 30px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--text-color);
        font-size: 0.9rem;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius);
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--text-color);
        font-family: "Press Start 2P", monospace;
        font-size: 0.8rem;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
    }
    
    textarea.form-control {
        min-height: 150px;
        resize: vertical;
    }
    
    .btn-primary, .btn-secondary {
        background-color: var(--primary-color);
        color: var(--text-color);
        border: 3px solid var(--border-color);
        padding: 12px 25px;
        font-family: "Press Start 2P", monospace;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border-radius: var(--border-radius);
        display: inline-block;
        text-align: center;
        min-width: 200px;
        margin-right: 15px;
        box-shadow: 0 4px 0 rgba(0,0,0,0.2);
        position: relative;
        top: 0;
        text-decoration: none;
    }
    
    .btn-primary:hover, .btn-secondary:hover {
        background-color: var(--secondary-color);
        transform: translateY(-2px);
        box-shadow: 0 6px 0 rgba(0,0,0,0.2);
    }
    
    .btn-primary:active, .btn-secondary:active {
        top: 4px;
        box-shadow: 0 0 0 rgba(0,0,0,0.2);
    }
    
    .btn-secondary {
        background-color: var(--secondary-color);
    }
    
    .form-actions {
        display: flex;
        justify-content: center;
        margin-top: 30px;
    }
    
    .success-dialog-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .success-dialog {
        background: var(--card-bg);
        padding: 30px;
        border-radius: var(--border-radius);
        border: 4px solid var(--border-color);
        box-shadow: var(--shadow);
        text-align: center;
        max-width: 400px;
        width: 90%;
        position: relative;
        animation: pixelSlideIn 0.3s steps(5) forwards;
    }
    
    @keyframes pixelSlideIn {
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
        color: var(--primary-color);
        margin-bottom: 20px;
        font-size: 1.2rem;
    }
    
    .success-dialog p {
        color: var(--text-color);
        margin-bottom: 25px;
        line-height: 1.6;
        font-size: 0.9rem;
    }
    
    .success-icon {
        font-size: 3rem;
        color: var(--primary-color);
        margin-bottom: 20px;
        text-shadow: 2px 2px 0 rgba(0,0,0,0.2);
    }
    
    .container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    .page-header {
        background-color: var(--card-bg);
        padding: 2rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        text-align: center;
        border: 4px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        position: relative;
        overflow: hidden;
    }
    
    .page-header {
        position: relative;
    }
    
    .page-header::before {
        content:
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(var(--primary-color-rgb), 0.1), rgba(var(--primary-color-rgb), 0.1));
        z-index: 0;
    }
    
    .page-header > * {
        position: relative;
        z-index: 1;
    }
    
    .page-title {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        text-transform: uppercase;
        text-shadow: 2px 2px 0 rgba(0, 0, 0, 0.5);
        font-family: "Press Start 2P", "Tiny5", "monospace", sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .page-title i {
        margin-right: 15px;
        font-size: 2rem;
    }
    
    .page-description {
        color: var(--text-color);
        max-width: 800px;
        margin: 0 auto;
        font-family: inherit;
    }
</style>';

include_once 'includes/header.php';
?>

<body>
    <!-- Matrix Background -->
    <canvas id="matrix-bg"></canvas>
    
    <!-- Include Music Player -->
    <?php include_once 'includes/music_player.php'; ?>
    
<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="material-icons">contact_support</i> Contact Admin</h1>
        <p class="page-description">Have a question or need help? Send a message to our admin team.</p>
    </div>
    
    <!-- Success dialog -->
    <div class="success-dialog-overlay" id="successDialog">
        <div class="success-dialog">
            <div class="success-icon">✓</div>
            <h3>Message Sent Successfully!</h3>
            <p>Thank you for your inquiry. Our admin team will review and respond as soon as possible.</p>
            <button onclick="closeSuccessDialog()" class="btn-primary">OK</button>
        </div>
    </div>

    <div class="contact-container">
        <div class="form-container">
            <form id="contactForm" method="POST">
                <div class="form-group">
                    <label for="name" class="form-label">Name:</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label">Email:</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" <?php echo (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) ? 'readonly' : ''; ?> required>
                </div>
                
                <div class="form-group">
                    <label for="subject" class="form-label">Subject:</label>
                    <input type="text" id="subject" name="subject" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="message" class="form-label">Message:</label>
                    <textarea id="message" name="message" class="form-control" required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Send Message</button>
                    <?php if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])): ?>
                        <a href="my_inquiries.php" class="btn-secondary">My Inquiries</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>

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

        // Add a console log to debug the form data
        console.log('Form data being sent:', Object.fromEntries(formData));
        
        fetch('contact_admin.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // Check if the response is ok (status in the range 200-299)
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status + ' ' + response.statusText);
            }
            // Check if the response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new TypeError('Expected JSON response but got ' + contentType);
            }
            return response.json();
        })
        .then(result => {
            console.log('Response received:', result); // Debug the response
            if (result.success) {
                // Show success dialog
                document.getElementById('successDialog').style.display = 'flex';
                this.reset();
            } else {
                alert(result.errors ? result.errors.join('\n') : result.message);
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            alert('An error occurred while sending your message. Please check the console for details.');
        });
    });

    function closeSuccessDialog() {
        document.getElementById('successDialog').style.display = 'none';
        // Redirect after closing dialog
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
        if (isAdmin) {
            window.location.href = 'admin/messages.php';
        } else if (<?php echo (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) ? 'true' : 'false'; ?>) {
            window.location.href = 'my_inquiries.php';
        } else {
            window.location.href = 'homepage.php';
        }
    }
</script>

<!-- Include matrix background script -->
<script src="matrix-bg.js"></script>
</body>
