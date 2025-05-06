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

// Process module actions
$message = '';
$error = '';

// Delete module
if (isset($_POST['delete_module']) && isset($_POST['module_id'])) {
    $moduleId = $_POST['module_id'];
    
    // Check if module exists
    $checkSql = "SELECT module_id FROM learning_modules WHERE module_id = ?";
    $checkResult = executeQuery($checkSql, [$moduleId]);
    
    if ($checkResult && count($checkResult) > 0) {
        // Check if module has content
        $contentSql = "SELECT COUNT(*) as count FROM learning_content WHERE module_id = ?";
        $contentResult = executeQuery($contentSql, [$moduleId]);
        
        if ($contentResult[0]['count'] > 0) {
            $error = "Cannot delete module. It has associated content items. Remove content first.";
        } else {
            // Delete module
            $deleteSql = "DELETE FROM learning_modules WHERE module_id = ?";
            $deleteResult = executeQuery($deleteSql, [$moduleId]);
            
            if ($deleteResult) {
                $message = "Module deleted successfully";
            } else {
                $error = "Error deleting module";
            }
        }
    } else {
        $error = "Module not found";
    }
}

// Toggle module active status
if (isset($_POST['toggle_active']) && isset($_POST['module_id'])) {
    $moduleId = $_POST['module_id'];
    
    // Get current status
    $statusSql = "SELECT is_active FROM learning_modules WHERE module_id = ?";
    $statusResult = executeQuery($statusSql, [$moduleId]);
    
    if ($statusResult && count($statusResult) > 0) {
        $currentStatus = $statusResult[0]['is_active'];
        $newStatus = $currentStatus ? 0 : 1;
        
        // Update status
        $updateSql = "UPDATE learning_modules SET is_active = ? WHERE module_id = ?";
        $updateResult = executeQuery($updateSql, [$newStatus, $moduleId]);
        
        if ($updateResult) {
            $message = "Module status updated successfully";
        } else {
            $error = "Error updating module status";
        }
    } else {
        $error = "Module not found";
    }
}

// Get modules with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter parameters
$pathFilter = isset($_GET['path']) ? $_GET['path'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

function getModules($limit, $offset, $pathFilter = '', $search = '') {
    $conditions = [];
    $params = [];
    
    if (!empty($pathFilter)) {
        $conditions[] = "lm.path_id = ?";
        $params[] = $pathFilter;
    }
    
    if (!empty($search)) {
        $conditions[] = "(lm.module_name LIKE ? OR lm.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $sql = "SELECT lm.module_id, lm.module_name, lm.description, lm.module_order, 
            lm.is_active, lm.created_at, lm.updated_at, lp.path_id, lp.path_name,
            (SELECT COUNT(*) FROM learning_content lc WHERE lc.module_id = lm.module_id) as content_count
            FROM learning_modules lm
            JOIN learning_paths lp ON lm.path_id = lp.path_id
            $whereClause
            ORDER BY lp.path_name, lm.module_order
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $result = executeQuery($sql, $params);
    $modules = [];
    
    if ($result && count($result) > 0) {
        $modules = $result;
    }
    
    return $modules;
}

// Get total modules count for pagination
function getTotalModules($pathFilter = '', $search = '') {
    $conditions = [];
    $params = [];
    
    if (!empty($pathFilter)) {
        $conditions[] = "lm.path_id = ?";
        $params[] = $pathFilter;
    }
    
    if (!empty($search)) {
        $conditions[] = "(lm.module_name LIKE ? OR lm.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    $sql = "SELECT COUNT(*) as total 
            FROM learning_modules lm
            JOIN learning_paths lp ON lm.path_id = lp.path_id
            $whereClause";
    
    $result = executeQuery($sql, $params);
    
    return $result[0]['total'] ?? 0;
}

// Get learning paths for filter dropdown
function getLearningPaths() {
    $sql = "SELECT path_id, path_name FROM learning_paths ORDER BY path_name";
    $result = executeSimpleQuery($sql);
    $paths = [];
    
    if ($result && count($result) > 0) {
        $paths = $result;
    }
    
    return $paths;
}

// Get modules
$modules = getModules($limit, $offset, $pathFilter, $search);
$totalModules = getTotalModules($pathFilter, $search);
$totalPages = ceil($totalModules / $limit);

// Get filter options
$paths = getLearningPaths();

// Set page title
$pageTitle = "Manage Modules - CodaQuest Admin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <?php include_once 'admin_styles.php'; ?>
    <style>
        /* Additional styles specific to modules page */
        .modules-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .path-header {
            grid-column: 1 / -1;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .path-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .path-title i {
            margin-right: 10px;
        }
        
        .module-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            border: 2px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .module-header {
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .module-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }
        
        .module-body {
            padding: 15px;
        }
        
        .module-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .module-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .module-meta-item {
            display: flex;
            align-items: center;
        }
        
        .module-meta-item i {
            margin-right: 5px;
            font-size: 16px;
            color: var(--primary-color);
        }
        
        .module-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .module-order {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 30px;
            height: 30px;
            background-color: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.8rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .badge-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
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
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px 20px;
            background-color: var(--card-bg);
            border-radius: 8px;
            border: 2px dashed var(--border-color);
        }
        
        .empty-state-icon {
            font-size: 48px;
            color: var(--text-secondary);
            margin-bottom: 15px;
        }
        
        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-color);
        }
        
        .empty-state-desc {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        /* Modal styles */
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
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar is included from admin_styles.php -->
        <div class="main-content">
            <div class="header">
                <h1 class="header-title"><i class="material-icons">folder</i> Module Management</h1>
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
                        <h2 class="section-title"><i class="material-icons section-icon">folder_open</i> Manage Modules</h2>
                        <a href="add_module.php" class="view-all">
                            <i class="material-icons">add</i> Add New Module
                        </a>
                    </div>

                    <form action="" method="GET" class="filter-form">
                        <div class="filter-container">
                            <div class="filter-group">
                                <label for="search" class="filter-label">Search</label>
                                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search modules..." class="form-control">
                            </div>
                            <div class="filter-group">
                                <label for="path" class="filter-label">Learning Path</label>
                                <select id="path" name="path" class="filter-select">
                                    <option value="">All Paths</option>
                                    <?php foreach ($paths as $path): ?>
                                        <option value="<?php echo $path['path_id']; ?>" <?php echo $pathFilter == $path['path_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($path['path_name']); ?>
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

                    <?php if (count($modules) > 0): ?>
                        <div class="modules-list">
                            <?php 
                            $currentPath = null;
                            foreach ($modules as $module): 
                                if ($currentPath !== $module['path_id']):
                                    $currentPath = $module['path_id'];
                            ?>
                                <div class="path-header">
                                    <h3 class="path-title">
                                        <i class="material-icons">route</i>
                                        <?php echo htmlspecialchars($module['path_name']); ?>
                                    </h3>
                                </div>
                            <?php endif; ?>
                                
                                <div class="module-card">
                                    <div class="module-order"><?php echo $module['module_order']; ?></div>
                                    <div class="module-header">
                                        <h3 class="module-title"><?php echo htmlspecialchars($module['module_name']); ?></h3>
                                        <span class="badge badge-<?php echo $module['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $module['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </div>
                                    <div class="module-body">
                                        <div class="module-description">
                                            <?php echo htmlspecialchars(substr($module['description'], 0, 100)) . (strlen($module['description']) > 100 ? '...' : ''); ?>
                                        </div>
                                        <div class="module-meta">
                                            <div class="module-meta-item">
                                                <i class="material-icons">book</i>
                                                <?php echo $module['content_count']; ?> Items
                                            </div>
                                        </div>
                                        <div class="module-actions">
                                            <a href="content.php?module=<?php echo $module['module_id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="material-icons">visibility</i> View
                                            </a>
                                            <a href="edit_module.php?id=<?php echo $module['module_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="material-icons">edit</i> Edit
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
                                                <button type="submit" name="toggle_active" class="btn btn-secondary btn-sm">
                                                    <i class="material-icons"><?php echo $module['is_active'] ? 'visibility_off' : 'visibility'; ?></i>
                                                    <?php echo $module['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $module['module_id']; ?>, '<?php echo htmlspecialchars($module['module_name']); ?>')">
                                                <i class="material-icons">delete</i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($pathFilter) ? '&path=' . urlencode($pathFilter) : ''; ?>" class="btn btn-secondary">
                                    <i class="material-icons">chevron_left</i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($pathFilter) ? '&path=' . urlencode($pathFilter) : ''; ?>" class="btn btn-secondary">
                                    Next <i class="material-icons">chevron_right</i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="material-icons empty-state-icon">folder_open</i>
                            <h3 class="empty-state-title">No Modules Found</h3>
                            <p class="empty-state-desc">
                                No modules found matching your criteria. Try adjusting your filters or create a new module.
                            </p>
                            <a href="add_module.php" class="btn btn-primary">
                                <i class="material-icons">add</i> Add New Module
                            </a>
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
            <p>Are you sure you want to delete the module <span id="deleteModuleName"></span>?</p>
            <p class="text-danger">This action cannot be undone. All content in this module will be orphaned.</p>
            <div class="modal-actions">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="module_id" id="deleteModuleId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="delete_module" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        function confirmDelete(moduleId, moduleName) {
            document.getElementById('deleteModuleId').value = moduleId;
            document.getElementById('deleteModuleName').textContent = moduleName;
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

