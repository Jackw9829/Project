<?php
session_start();
require_once 'config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

$isTeacher = false;
try {
    // Check if user is a teacher
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userType = $stmt->fetchColumn();
    $isTeacher = ($userType === 'teacher');
} catch(Exception $e) {
    error_log("Error checking user type: " . $e->getMessage());
}

try {
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Handle form submission for profile updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        $dob = $_POST['dob'];
        $gender = $_POST['gender'];
        
        // Validate inputs
        if (!$email) {
            throw new Exception('Invalid email address');
        }

        if (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
            throw new Exception('Name can only contain letters and spaces');
        }
        
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            throw new Exception('Phone number must be 10 or 11 digits');
        }

        // Check if email already exists for other users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email already registered to another user');
        }

        // Update user profile
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, dob = ?, gender = ?
            WHERE user_id = ?
        ");
        
        $stmt->execute([$name, $email, $phone, $dob, $gender, $_SESSION['user_id']]);
        $success = 'Profile updated successfully';
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }

} catch(Exception $e) {
    $error = $e->getMessage();
}

$page_title = 'Profile';
include 'includes/header.php';
?>

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
        padding-top: 100px;
        position: relative;
    }

    #particles-js {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }

    main {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 50px 20px;
        position: relative;
        z-index: 1;
        min-height: calc(100vh - 200px);
    }

    .profile-container {
        max-width: 800px;
        width: 100%;
        background: rgba(255, 255, 255, 0.95);
        padding: 2.5rem;
        border-radius: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(0, 0, 0, 0.05);
        margin: 50px 0;
        position: relative;
        z-index: 2;
    }

    .profile-container h1 {
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

    .update-btn {
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

    .update-btn:hover {
        background: rgba(255, 192, 203, 0.5);
        transform: translateY(-2px);
    }

    .alert {
        padding: 1rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        text-align: center;
        font-size: 0.9rem;
    }

    .alert-danger {
        background-color: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
        border: 1px solid rgba(231, 76, 60, 0.2);
    }

    .alert-success {
        background-color: rgba(46, 204, 113, 0.1);
        color: #27ae60;
        border: 1px solid rgba(46, 204, 113, 0.2);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    footer {
        position: relative;
        z-index: 10;
        margin-top: 50px;
    }

    @media (max-width: 576px) {
        .profile-container {
            padding: 2rem;
        }

        .profile-container h1 {
            font-size: 2rem;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<main>
    <div id="particles-js" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0;"></div>
    <div class="profile-container">
        <h1>My Profile</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="profile-form" onsubmit="return validateForm()">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" 
                           pattern="[A-Za-z\s]+" title="Name can only contain letters and spaces" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                           pattern="[0-9]{10,11}" title="Phone number must be 10 or 11 digits" required>
                </div>

                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($user['dob']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                    <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                    <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>

            <div class="form-group">
                <button type="submit" class="update-btn">Update Profile</button>
            </div>
        </form>

        <script>
            function validateForm() {
                const name = document.getElementById('name').value.trim();
                const phone = document.getElementById('phone').value.replace(/[^0-9]/g, '');
                
                // Validate name (letters and spaces only)
                if (!/^[a-zA-Z\s]+$/.test(name)) {
                    alert('Name can only contain letters and spaces');
                    return false;
                }
                
                // Validate phone (10-11 digits)
                if (phone.length < 10 || phone.length > 11) {
                    alert('Phone number must be 10 or 11 digits');
                    return false;
                }
                
                return true;
            }

            // Auto-format phone number as user types
            document.getElementById('phone').addEventListener('input', function(e) {
                let phone = e.target.value.replace(/[^0-9]/g, '');
                if (phone.length > 11) {
                    phone = phone.slice(0, 11);
                }
                e.target.value = phone;
            });

            // Prevent non-letter characters in name field
            document.getElementById('name').addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^a-zA-Z\s]/g, '');
            });
        </script>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
<script>
    particlesJS.load('particles-js', 'particles.json', function() {
        console.log('particles.js loaded');
    });
</script>

<?php include 'includes/footer.php'; ?>