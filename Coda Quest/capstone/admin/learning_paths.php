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

// Process path actions
$message = '';
$error = '';

// Delete path
if (isset($_POST['delete_path']) && isset($_POST['path_id'])) {
    $pathId = $_POST['path_id'];
    
    // Check if path exists
    $checkSql = "SELECT path_id FROM learning_paths WHERE path_id = ?";
    $checkResult = executeQuery($checkSql, [$pathId]);
    
    if ($checkResult && count($checkResult) > 0) {
        // Delete path
        $deleteSql = "DELETE FROM learning_paths WHERE path_id = ?";
        $deleteResult = executeQuery($deleteSql, [$pathId]);
        
        if ($deleteResult) {
            $message = "Learning path deleted successfully";
        } else {
            $error = "Error deleting learning path";
        }
    } else {
        $error = "Learning path not found";
    }
}

// Toggle path active status
if (isset($_POST['toggle_active']) && isset($_POST['path_id'])) {
    $pathId = $_POST['path_id'];
    
    // Get current status
    $statusSql = "SELECT is_active FROM learning_paths WHERE path_id = ?";
    $statusResult = executeQuery($statusSql, [$pathId]);
    
    if ($statusResult && count($statusResult) > 0) {
        $currentStatus = $statusResult[0]['is_active'];
        $newStatus = $currentStatus ? 0 : 1;
        
        // Update status
        $updateSql = "UPDATE learning_paths SET is_active = ? WHERE path_id = ?";
        $updateResult = executeQuery($updateSql, [$newStatus, $pathId]);
        
        if ($updateResult) {
            $message = "Learning path status updated successfully";
        } else {
            $error = "Error updating learning path status";
        }
    } else {
        $error = "Learning path not found";
    }
}

// Get learning paths with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

function getLearningPaths($limit, $offset, $search = '') {
    $params = [$limit, $offset];
    $searchCondition = '';
    
    if (!empty($search)) {
        $searchCondition = "WHERE path_name LIKE ? OR description LIKE ?";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $limit, $offset];
    }
    
    $sql = "SELECT lp.path_id, lp.path_name, lp.description, lp.difficulty_level, 
            lp.estimated_duration, lp.is_active, lp.created_at, lp.updated_at,
            COUNT(DISTINCT m.module_id) as module_count,
            COUNT(DISTINCT up.user_id) as enrollment_count
            FROM learning_paths lp
            LEFT JOIN learning_modules m ON lp.path_id = m.path_id
            LEFT JOIN user_progress up ON lp.path_id = up.path_id
            $searchCondition
            GROUP BY lp.path_id
            ORDER BY lp.created_at DESC
            LIMIT ? OFFSET ?";
    
    $result = executeQuery($sql, $params);
    $paths = [];
    
    if ($result && count($result) > 0) {
        $paths = $result;
    }
    
    return $paths;
}

// Get total paths count for pagination
function getTotalPaths($search = '') {
    $searchCondition = '';
    $params = [];
    
    if (!empty($search)) {
        $searchCondition = "WHERE path_name LIKE ? OR description LIKE ?";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam];
    }
    
    $sql = "SELECT COUNT(*) as total FROM learning_paths $searchCondition";
    $result = executeQuery($sql, $params);
    
    return $result[0]['total'] ?? 0;
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get learning paths
$paths = getLearningPaths($limit, $offset, $search);
$totalPaths = getTotalPaths($search);
$totalPages = ceil($totalPaths / $limit);

// Set page title
$pageTitle = "Manage Learning Paths - CodaQuest Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to learning paths page */
        .paths-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .path-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 2px solid var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .path-card:hover {
            transform: translateY(-5px);
        }
        
        .path-header {
            padding: 15px;
            border-bottom: 2px solid var(--primary-color);
            position: relative;
        }
        
        .path-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-color);
        }
        
        .path-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .path-status {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .path-content {
            padding: 15px;
        }
        
        .path-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .path-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .view-btn {
            background-color: var(--accent-color);
        }
        
        .edit-btn {
            background-color: var(--primary-color);
        }
        
        .delete-btn {
            background-color: #e74c3c;
        }
        
        .toggle-btn {
            background-color: #f39c12;
        }
        
        .search-container {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">alt_route</i> Learning Paths</h1>
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
                        <h2 class="section-title"><i class="material-icons section-icon">alt_route</i> Manage Learning Paths</h2>
                        <a href="add_path.php" class="view-all">
                            <i class="material-icons">add</i> Add New Path
                        </a>
                    </div>

                    <div class="search-container">
                        <form action="" method="GET" class="search-form">
                            <div class="form-group">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search learning paths..." class="form-control">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">search</i> Search
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (count($paths) > 0): ?>
                        <div class="paths-grid">
                            <?php foreach ($paths as $path): ?>
                                <div class="path-card">
                                    <div class="path-header">
                                        <h3 class="path-title"><?php echo htmlspecialchars($path['path_name']); ?></h3>
                                        <p class="path-description"><?php echo htmlspecialchars(substr($path['description'], 0, 100)) . (strlen($path['description']) > 100 ? '...' : ''); ?></p>
                                        <div class="path-status">
                                            <span class="status-badge <?php echo $path['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $path['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="path-content">
                                        <div class="path-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $path['module_count']; ?></div>
                                                <div class="stat-label">Modules</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $path['enrollment_count']; ?></div>
                                                <div class="stat-label">Students</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $path['completion_rate']; ?>%</div>
                                                <div class="stat-label">Completion</div>
                                            </div>
                                        </div>
                                        <div class="path-actions">
                                            <a href="view_path.php?id=<?php echo $path['path_id']; ?>" class="action-btn view-btn" title="View Path">
                                                <i class="material-icons">visibility</i>
                                            </a>
                                            <a href="edit_path.php?id=<?php echo $path['path_id']; ?>" class="action-btn edit-btn" title="Edit Path">
                                                <i class="material-icons">edit</i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="path_id" value="<?php echo $path['path_id']; ?>">
                                                <button type="submit" name="toggle_active" class="action-btn toggle-btn" title="<?php echo $path['is_active'] ? 'Deactivate' : 'Activate'; ?> Path">
                                                    <i class="material-icons"><?php echo $path['is_active'] ? 'visibility_off' : 'visibility'; ?></i>
                                                </button>
                                            </form>
                                            <button type="button" class="action-btn delete-btn" title="Delete Path" 
                                                    onclick="confirmDelete(<?php echo $path['path_id']; ?>, '<?php echo htmlspecialchars($path['path_name']); ?>')">
                                                <i class="material-icons">delete</i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

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
                            <p>No learning paths found.</p>
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
            <p>Are you sure you want to delete the learning path <span id="deletePathName"></span>?</p>
            <div class="modal-actions">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="path_id" id="deletePathId">
                    <input type="hidden" name="delete_path" value="1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        function confirmDelete(pathId, pathName) {
            document.getElementById('deletePathId').value = pathId;
            document.getElementById('deletePathName').textContent = pathName;
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

