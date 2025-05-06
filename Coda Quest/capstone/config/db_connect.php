<?php
/**
 * Database Connection Configuration for CodaQuest
 * 
 * This file handles the connection to the MySQL database for the CodaQuest platform.
 * It establishes a connection to the database and provides helper functions for database operations.
 */

// Database configuration
$config = [
    'host' => 'localhost',
    'dbname' => 'codaquest',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// PDO options
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

/**
 * Get database connection
 * 
 * @return PDO|null Returns a PDO connection object or null on failure
 */
function getDbConnection() {
    global $config, $pdoOptions;
    
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    
    try {
        $pdo = new PDO($dsn, $config['username'], $config['password'], $pdoOptions);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        error_log("Connection details: Host={$config['host']}, DB={$config['dbname']}");
        error_log("PDO Options: " . print_r($pdoOptions, true));
        return null;
    }
}

/**
 * Execute a query with parameters
 * 
 * @param string $sql The SQL query to execute
 * @param array $params The parameters to bind to the query
 * @return array|false Returns an array of results or false on failure
 */
function executeQuery($sql, $params = []) {
    $conn = getDbConnection();
    if (!$conn) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        // If the query is a SELECT, fetch results; otherwise, return true/false for success
        if (stripos(trim($sql), 'SELECT') === 0) {
            return $stmt->fetchAll();
        } else {
            return $stmt->rowCount() > 0;
        }
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute a simple query without parameters
 * 
 * @param string $sql The SQL query to execute
 * @return array|false Returns an array of results or false on failure
 */
function executeSimpleQuery($sql) {
    return executeQuery($sql);
}

/**
 * Insert data into a table
 * 
 * @param string $table The table to insert into
 * @param array $data Associative array of column => value pairs
 * @return int|false The last insert ID or false on failure
 */
function insertData($table, $data) {
    $conn = getDbConnection();
    if (!$conn) {
        return false;
    }
    
    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $conn->lastInsertId();
    } catch (PDOException $e) {
        error_log("Insert failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute an INSERT query and return the last insert ID
 * 
 * @param string $sql The SQL INSERT query to execute
 * @param array $params The parameters to bind to the query
 * @return int|false The last insert ID or false on failure
 */
function executeInsert($sql, $params = []) {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in executeInsert");
        return false;
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            error_log("Insert execution failed in executeInsert");
            return false;
        }
        
        $lastId = $conn->lastInsertId();
        return $lastId;
    } catch (PDOException $e) {
        error_log("Insert failed in executeInsert: " . $e->getMessage());
        return false;
    }
}

/**
 * Update data in a table
 * 
 * @param string $table The table to update
 * @param array $data Associative array of column => value pairs
 * @param string $whereClause The WHERE clause
 * @param array $whereParams Parameters for the WHERE clause
 * @return int|false The number of affected rows or false on failure
 */
function updateData($table, $data, $whereClause, $whereParams = []) {
    $conn = getDbConnection();
    if (!$conn) {
        return false;
    }
    
    try {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "$column = ?";
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $setClauses) . " WHERE $whereClause";
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Close database connection
 * 
 * @param PDO $conn The connection to close
 * @return void
 */
function closeDbConnection($conn) {
    if ($conn) {
        $conn = null;
    }
}

/**
 * Get a single row from the database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind
 * @return array|null Returns a single row as an associative array or null
 */
function getRow($sql, $params = []) {
    $result = executeQuery($sql, $params);
    
    if (is_array($result) && !empty($result)) {
        return $result[0];
    }
    
    return null;
}

/**
 * Get a single value from the database
 * 
 * @param string $sql The SQL query with placeholders
 * @param array $params The parameters to bind
 * @param string $column The column name to retrieve
 * @return mixed|null Returns the value or null
 */
function getValue($sql, $params = [], $column = null) {
    $row = getRow($sql, $params);
    
    if (is_array($row)) {
        if ($column && isset($row[$column])) {
            return $row[$column];
        } else {
            // If no column specified, return the first value
            return reset($row);
        }
    }
    
    return null;
}

/**
 * Delete data from a table
 * 
 * @param string $table The table name
 * @param string $whereClause The WHERE clause (without the 'WHERE' keyword)
 * @param array $params The parameters to bind
 * @return int|null Returns the number of affected rows or null on failure
 */
function deleteData($table, $whereClause, $params = []) {
    $sql = "DELETE FROM $table WHERE $whereClause";
    
    $result = executeQuery($sql, $params);
    
    if (is_array($result)) {
        return count($result);
    }
    
    return null;
}

/**
 * Get the last insert ID
 * 
 * @return int|string The last insert ID
 */
function getLastInsertId() {
    $conn = getDbConnection();
    if (!$conn) {
        return 0;
    }
    
    return $conn->lastInsertId();
}

/**
 * Check and add Google ID column to students table if it doesn't exist
 * 
 * @return boolean Whether the column was added or already exists
 */
function addGoogleIdColumnIfNeeded() {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in addGoogleIdColumnIfNeeded");
        return false;
    }
    
    try {
        // Check if google_id column exists in students table
        $sql = "SHOW COLUMNS FROM students LIKE 'google_id'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if (empty($result)) {
            // Column doesn't exist, add it
            $alterSql = "ALTER TABLE students ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER theme";
            $alterStmt = $conn->prepare($alterSql);
            $alterStmt->execute();
            error_log("Added google_id column to students table");
            
            return true;
        }
        
        return true; // Column already exists
    } catch (PDOException $e) {
        error_log("Failed to check/add google_id column: " . $e->getMessage());
        return false;
    }
}

// Check and add the Google ID column when this file is loaded
addGoogleIdColumnIfNeeded();

/**
 * Check and add auth_provider column to students table if it doesn't exist
 * 
 * @return boolean Whether the column was added or already exists
 */
function addAuthProviderColumnIfNeeded() {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in addAuthProviderColumnIfNeeded");
        return false;
    }
    
    try {
        // Check if auth_provider column exists in students table
        $sql = "SHOW COLUMNS FROM students LIKE 'auth_provider'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if (empty($result)) {
            // Column doesn't exist, add it
            $alterSql = "ALTER TABLE students ADD COLUMN auth_provider VARCHAR(50) DEFAULT 'local' AFTER google_id";
            $alterStmt = $conn->prepare($alterSql);
            $alterStmt->execute();
            error_log("Added auth_provider column to students table");
            
            return true;
        }
        
        return true; // Column already exists
    } catch (PDOException $e) {
        error_log("Failed to check/add auth_provider column: " . $e->getMessage());
        return false;
    }
}

// Check and add the auth_provider column when this file is loaded
addAuthProviderColumnIfNeeded();

/**
 * Check and add google_id column to admins table if it doesn't exist
 * 
 * @return boolean Whether the column was added or already exists
 */
function addAdminGoogleIdColumnIfNeeded() {
    $conn = getDbConnection();
    if (!$conn) {
        error_log("Database connection failed in addAdminGoogleIdColumnIfNeeded");
        return false;
    }
    
    try {
        // Check if google_id column exists in admins table
        $sql = "SHOW COLUMNS FROM admins LIKE 'google_id'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll();
        
        if (empty($result)) {
            // Column doesn't exist, add it
            $alterSql = "ALTER TABLE admins ADD COLUMN google_id VARCHAR(255) DEFAULT NULL AFTER theme";
            $alterStmt = $conn->prepare($alterSql);
            $alterStmt->execute();
            error_log("Added google_id column to admins table");
            
            // Also add auth_provider column if it doesn't exist
            $sql2 = "SHOW COLUMNS FROM admins LIKE 'auth_provider'";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->execute();
            $result2 = $stmt2->fetchAll();
            
            if (empty($result2)) {
                $alterSql2 = "ALTER TABLE admins ADD COLUMN auth_provider VARCHAR(50) DEFAULT 'local' AFTER google_id";
                $alterStmt2 = $conn->prepare($alterSql2);
                $alterStmt2->execute();
                error_log("Added auth_provider column to admins table");
            }
            
            return true;
        }
        
        return true; // Column already exists
    } catch (PDOException $e) {
        error_log("Failed to check/add admin google_id column: " . $e->getMessage());
        return false;
    }
}

// Check and add the admin Google ID column when this file is loaded
addAdminGoogleIdColumnIfNeeded();
