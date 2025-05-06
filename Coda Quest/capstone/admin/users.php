<?php
// Start session
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Include database connection
require_once '../config/db_connect.php';
$pdo = getDbConnection(); // Ensure PDO is defined for this page

// Get admin information
$adminId = $_SESSION['admin_id'];
$adminSql = "SELECT username FROM admins WHERE admin_id = ?";
$adminResult = executeQuery($adminSql, [$adminId]);
$adminName = $adminResult[0]['username'] ?? 'Admin';

// Process user actions
$message = '';
$error = '';

// Delete user (student)
if (isset($_POST['delete_user']) && isset($_POST['user_id']) && isset($_POST['user_role'])) {
    $userId = $_POST['user_id'];
    $userRole = $_POST['user_role'];
    
    if ($userRole === 'student') {
        // Check if student exists
        $checkSql = "SELECT student_id FROM students WHERE student_id = ?";
        $checkResult = executeQuery($checkSql, [$userId]);
        
        if ($checkResult && count($checkResult) > 0) {
            // Delete student
            $deleteSql = "DELETE FROM students WHERE student_id = ?";
            $deleteResult = executeQuery($deleteSql, [$userId]);
            
            if ($deleteResult) {
                $message = "Student deleted successfully";
                $error = '';
            } else {
                $error = "Error deleting student";
                $message = '';
            }
        } else {
            $error = "Student not found";
        }
    } elseif ($userRole === 'admin') {
        // Check if admin exists
        $checkSql = "SELECT admin_id FROM admins WHERE admin_id = ?";
        $checkResult = executeQuery($checkSql, [$userId]);
        
        if ($checkResult && count($checkResult) > 0) {
            // Delete admin
            $deleteSql = "DELETE FROM admins WHERE admin_id = ?";
            $deleteResult = executeQuery($deleteSql, [$userId]);
            
            if ($deleteResult) {
                $message = "Admin deleted successfully";
                $error = '';
            } else {
                $error = "Error deleting admin";
                $message = '';
            }
        } else {
            $error = "Admin not found";
        }
    } else {
        $error = "Invalid user role.";
    }
}

// Only delete user functionality is maintained
// All other user management features have been removed for simplicity

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

function getUsers($limit, $offset, $search = '') {
    // Get students
    $sql = "SELECT student_id as id, username, email, date_registered, is_active, 'student' as role, total_points, current_level FROM students";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE username LIKE ? OR email LIKE ?";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam];
    }
    
    $sql .= " ORDER BY date_registered DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $result = executeQuery($sql, $params);
    $users = [];
    
    if ($result && count($result) > 0) {
        $users = $result;
    }
    
    // Only return students
    return $users;
}

// Get total users count for pagination
function getTotalUsers($search = '') {
    // Count students
    $sql = "SELECT COUNT(*) as total FROM students";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE username LIKE ? OR email LIKE ?";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam];
    }
    
    $result = executeQuery($sql, $params);
    $studentCount = $result[0]['total'] ?? 0;
    
    // Return only student count
    return $studentCount;
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get users
$users = getUsers($limit, $offset, $search);
$totalUsers = getTotalUsers($search);
$totalPages = ceil($totalUsers / $limit);

// Set page title
$pageTitle = "Manage Users - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Custom styles for users table (theme-adaptive) */
        .quizzes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border: 2px solid var(--border-color);
            font-size: 12px;
        }
        .quizzes-table th, .quizzes-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(var(--primary-color-rgb), 0.2);
            color: var(--text-color);
        }
        .quizzes-table th {
            background-color: rgba(var(--primary-color-rgb), 0.2);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        .quizzes-table tr:last-child td {
            border-bottom: none;
        }
        .quizzes-table tr:hover {
            background-color: rgba(var(--primary-color-rgb), 0.05);
        }
        /* Action buttons - matching managequiz.php style */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.2s ease;
            background-color: rgba(var(--card-bg-rgb,255,255,255), 0.8);
            border-radius: 6px;
            padding: 0;
        }
        .action-btn i.material-icons {
            font-size: 16px;
            line-height: 1;
            margin: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .view-btn {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .view-btn:hover {
            background-color: var(--primary-color);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        .delete-btn {
            color: var(--hard-color, #f44336);
            border-color: var(--hard-color, #f44336);
        }
        .delete-btn:hover {
            background-color: var(--hard-color, #f44336);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        .edit-btn {
            color: #2196F3;
            border-color: #2196F3;
        }
        .edit-btn:hover {
            background-color: #2196F3;
            color: #121212;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        
        .delete-btn {
            color: #f44336;
            border-color: #f44336;
        }
        
        .delete-btn:hover {
            background-color: var(--hard-color, #f44336);
            color: var(--background-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }
        /* Modal styles (theme-adaptive) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 25px;
            width: 400px;
            max-width: 90%;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--primary-color);
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 12px;
            color: white;
        }
        .alert-danger {
            background-color: #e74c3c;
        }
        .alert-success {
            background-color: #2ecc71;
        }
        /* No results */
        .no-results {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
            margin-top: 20px;
        }
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        .btn.btn-secondary {
            background-color: var(--secondary-color, #ccc);
            color: var(--text-color);
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn.btn-secondary:hover {
            background-color: var(--primary-color);
            color: var(--background-color);
        }
        .btn.btn-danger {
            background-color: var(--hard-color, #f44336);
            color: var(--background-color);
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn.btn-danger:hover {
            background-color: #c62828;
            color: var(--background-color);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">people</i> User Management</h1>
                <div class="user-info">

                    <!-- User avatar removed -->
                </div>
            </div>

            <div class="dashboard-content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="recent-section">
                    <div class="section-header">
                        <h2 class="section-title"><i class="material-icons">group</i> Manage Users</h2>
                    </div>

                    <div class="search-container">
                        <form action="" method="GET" class="search-form">
                            <div class="form-group">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users..." class="form-control">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">search</i> Search
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (count($users) > 0): ?>
                        <table class="quizzes-table">
                            <thead>
                                <tr>
                                    <th><i class="material-icons">person</i> User</th>
                                    <th><i class="material-icons">email</i> Email</th>
                                    <th><i class="material-icons">verified_user</i> Role</th>
                                    <th><i class="material-icons">date_range</i> Joined Date</th>
                                    <th><i class="material-icons">settings</i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'admin' : 'user'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['date_registered'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_user.php?id=<?php echo $user['id']; ?>" class="action-btn view-btn" title="View User Details">
                                                    <i class="material-icons">visibility</i>
                                                </a>
                                                <button type="button" class="action-btn delete-btn" title="Delete User" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>')">
                                                    <i class="material-icons">delete</i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-secondary">
                                    <i class="material-icons">chevron_left</i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-secondary">
                                    Next <i class="material-icons">chevron_right</i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No users found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>Confirm Delete</h2>
            <p>Are you sure you want to delete user <span id="deleteUsername"></span>?</p>
            <div class="modal-actions">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="user_role" id="deleteUserRole">
                    <input type="hidden" name="delete_user" value="1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        function confirmDelete(userId, username, role) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserRole').value = role;
            document.getElementById('deleteUsername').textContent = username + ' (' + role + ')';
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
    <!-- Include Music Player -->
    <?php include_once '../includes/music_player.php'; ?>
</body>
</html>

