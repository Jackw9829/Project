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

// Process content actions
$message = '';
$error = '';

// Delete content
if (isset($_POST['delete_content']) && isset($_POST['content_id'])) {
    $contentId = $_POST['content_id'];
    
    // Check if content exists
    $checkSql = "SELECT content_id FROM learning_content WHERE content_id = ?";
    $checkResult = executeQuery($checkSql, [$contentId]);
    
    if ($checkResult && count($checkResult) > 0) {
        // Delete content
        $deleteSql = "DELETE FROM learning_content WHERE content_id = ?";
        $deleteResult = executeQuery($deleteSql, [$contentId]);
        
        if ($deleteResult) {
            $message = "Content deleted successfully";
        } else {
            $error = "Error deleting content";
        }
    } else {
        $error = "Content not found";
    }
}

// Toggle content active status
if (isset($_POST['toggle_active']) && isset($_POST['content_id'])) {
    $contentId = $_POST['content_id'];
    
    // Get current status
    $statusSql = "SELECT is_active FROM learning_content WHERE content_id = ?";
    $statusResult = executeQuery($statusSql, [$contentId]);
    
    if ($statusResult && count($statusResult) > 0) {
        $currentStatus = $statusResult[0]['is_active'];
        $newStatus = $currentStatus ? 0 : 1;
        
        // Update status
        $updateSql = "UPDATE learning_content SET is_active = ? WHERE content_id = ?";
        $updateResult = executeQuery($updateSql, [$newStatus, $contentId]);
        
        if ($updateResult) {
            $message = "Content status updated successfully";
        } else {
            $error = "Error updating content status";
        }
    } else {
        $error = "Content not found";
    }
}

// Get content with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$moduleFilter = isset($_GET['module']) ? $_GET['module'] : '';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

function getContent($limit, $offset, $moduleFilter = '', $typeFilter = '', $search = '') {
    $conditions = [];
    $params = [];
    
    if (!empty($moduleFilter)) {
        $conditions[] = "lc.module_id = ?";
        $params[] = $moduleFilter;
    }
    
    if (!empty($typeFilter)) {
        $conditions[] = "lc.content_type = ?";
        $params[] = $typeFilter;
    }
    
    if (!empty($search)) {
        $conditions[] = "(lc.content_title LIKE ? OR lc.content_body LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $sql = "SELECT lc.content_id, lc.content_title, lc.content_type, lc.estimated_duration, 
            lc.points_reward, lc.difficulty_level, lc.is_active, lc.created_at, lc.updated_at,
            lm.module_name, lp.path_name
            FROM learning_content lc
            JOIN learning_modules lm ON lc.module_id = lm.module_id
            JOIN learning_paths lp ON lm.path_id = lp.path_id
            $whereClause
            ORDER BY lc.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $result = executeQuery($sql, $params);
    $content = [];
    
    if ($result && count($result) > 0) {
        $content = $result;
    }
    
    return $content;
}

// Get total content count for pagination
function getTotalContent($moduleFilter = '', $typeFilter = '', $search = '') {
    $conditions = [];
    $params = [];
    
    if (!empty($moduleFilter)) {
        $conditions[] = "lc.module_id = ?";
        $params[] = $moduleFilter;
    }
    
    if (!empty($typeFilter)) {
        $conditions[] = "lc.content_type = ?";
        $params[] = $typeFilter;
    }
    
    if (!empty($search)) {
        $conditions[] = "(lc.content_title LIKE ? OR lc.content_body LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $sql = "SELECT COUNT(*) as total 
            FROM learning_content lc
            JOIN learning_modules lm ON lc.module_id = lm.module_id
            JOIN learning_paths lp ON lm.path_id = lp.path_id
            $whereClause";
    
    $result = executeQuery($sql, $params);
    
    return $result[0]['total'] ?? 0;
}

// Get modules for filter dropdown
function getModules() {
    $sql = "SELECT lm.module_id, lm.module_name, lp.path_name
            FROM learning_modules lm
            JOIN learning_paths lp ON lm.path_id = lp.path_id
            WHERE lm.is_active = TRUE
            ORDER BY lp.path_name, lm.module_order";
    
    $result = executeSimpleQuery($sql);
    $modules = [];
    
    if ($result && count($result) > 0) {
        $modules = $result;
    }
    
    return $modules;
}

// Get content types for filter dropdown
function getContentTypes() {
    $sql = "SELECT DISTINCT content_type FROM learning_content ORDER BY content_type";
    $result = executeSimpleQuery($sql);
    $types = [];
    
    if ($result && count($result) > 0) {
        foreach ($result as $row) {
            $types[] = $row['content_type'];
        }
    }
    
    return $types;
}

// Get content
$content = getContent($limit, $offset, $moduleFilter, $typeFilter, $search);
$totalContent = getTotalContent($moduleFilter, $typeFilter, $search);
$totalPages = ceil($totalContent / $limit);

// Get filter options
$modules = getModules();
$contentTypes = getContentTypes();

// Set page title
$pageTitle = "Manage Content - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to content page */
        .filter-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .filter-select {
            padding: 8px 10px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-family: inherit;
        }
        
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--primary-color);
        }
        
        .content-table th,
        .content-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .content-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .content-table tr:last-child td {
            border-bottom: none;
        }
        
        .content-table tr:hover {
            background-color: rgba(255, 107, 142, 0.05);
        }
        
        .content-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .content-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .content-type {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .type-video {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-article {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .type-quiz {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .type-exercise {
            background-color: #fff3e0;
            color: #e65100;
        }
        
        .type-interactive {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .action-btns {
            display: flex;
            gap: 5px;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">article</i> Content Management</h1>
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
                        <h2 class="section-title"><i class="material-icons section-icon">library_books</i> Manage Content</h2>
                        <a href="add_content.php" class="view-all">
                            <i class="material-icons">add</i> Add New Content
                        </a>
                    </div>

                    <form action="" method="GET" class="filter-form">
                        <div class="filter-container">
                            <div class="filter-group">
                                <label for="search" class="filter-label">Search</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search content..." class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="module" class="filter-label">Module</label>
                                <select id="module" name="module" class="filter-select">
                                    <option value="">All Modules</option>
                                    <?php foreach ($modules as $module): ?>
                                        <option value="<?php echo $module['module_id']; ?>" <?php echo $moduleFilter == $module['module_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($module['module_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="type" class="filter-label">Content Type</label>
                                <select id="type" name="type" class="filter-select">
                                    <option value="">All Types</option>
                                    <?php foreach ($contentTypes as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $typeFilter == $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group" style="justify-content: flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="material-icons">filter_list</i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (count($content) > 0): ?>
                        <table class="content-table">
                            <thead>
                                <tr>
                                    <th><i class="material-icons">title</i> Content</th>
                                    <th><i class="material-icons">category</i> Type</th>
                                    <th><i class="material-icons">folder</i> Module</th>
                                    <th><i class="material-icons">schedule</i> Duration</th>
                                    <th><i class="material-icons">visibility</i> Status</th>
                                    <th><i class="material-icons">settings</i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($content as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="content-title"><?php echo htmlspecialchars($item['content_title']); ?></div>
                                            <div class="content-description"><?php echo htmlspecialchars(substr($item['content_body'], 0, 100)) . (strlen($item['content_body']) > 100 ? '...' : ''); ?></div>
                                        </td>
                                        <td>
                                            <span class="content-type type-<?php echo strtolower($item['content_type']); ?>">
                                                <?php echo htmlspecialchars($item['content_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['module_name']); ?></td>
                                        <td><?php echo $item['estimated_duration']; ?> min</td>
                                        <td>
                                            <span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'danger'; ?>">
                                                <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="view_content.php?id=<?php echo $item['content_id']; ?>" class="action-btn view-btn" title="View Content">
                                                    <i class="material-icons">visibility</i>
                                                </a>
                                                <a href="edit_content.php?id=<?php echo $item['content_id']; ?>" class="action-btn edit-btn" title="Edit Content">
                                                    <i class="material-icons">edit</i>
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="content_id" value="<?php echo $item['content_id']; ?>">
                                                    <button type="submit" name="toggle_active" class="action-btn toggle-btn" title="<?php echo $item['is_active'] ? 'Deactivate' : 'Activate'; ?> Content">
                                                        <i class="material-icons"><?php echo $item['is_active'] ? 'visibility_off' : 'visibility'; ?></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="action-btn delete-btn" title="Delete Content" 
                                                        onclick="confirmDelete(<?php echo $item['content_id']; ?>, '<?php echo htmlspecialchars($item['content_title']); ?>')">
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
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($moduleFilter) ? '&module=' . urlencode($moduleFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?>" class="btn btn-secondary">
                                    <i class="material-icons">chevron_left</i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($moduleFilter) ? '&module=' . urlencode($moduleFilter) : ''; ?><?php echo !empty($typeFilter) ? '&type=' . urlencode($typeFilter) : ''; ?>" class="btn btn-secondary">
                                    Next <i class="material-icons">chevron_right</i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No content found matching your criteria. Try adjusting your filters or <a href="add_content.php">add new content</a>.</p>
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
            <p>Are you sure you want to delete the content <span id="deleteContentName"></span>?</p>
            <div class="modal-actions">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="content_id" id="deleteContentId">
                    <input type="hidden" name="delete_content" value="1">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        function confirmDelete(contentId, contentName) {
            document.getElementById('deleteContentId').value = contentId;
            document.getElementById('deleteContentName').textContent = contentName;
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

