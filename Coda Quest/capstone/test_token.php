<?php
// Include the database connection
require_once 'config/db_connect.php';

// Function to log messages
function debug_log($message, $data = null) {
    $log = "[" . date('Y-m-d H:i:s') . "] TEST_TOKEN: $message";
    if ($data !== null) {
        $log .= " - " . print_r($data, true);
    }
    error_log($log);
    echo "<p>" . htmlspecialchars($log) . "</p>";
}

// Test database connection
function test_db_connection() {
    echo "<h2>Testing Database Connection</h2>";
    
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }
        
        debug_log("Database connection successful", ["pdo_class" => get_class($pdo)]);
        
        // Try a simple query
        $stmt = $pdo->query("SELECT VERSION() as version");
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        debug_log("Database version", $version);
        
        return $pdo;
    } catch (Exception $e) {
        debug_log("Database connection error", $e->getMessage());
        return null;
    }
}

// Test token table
function test_token_table($pdo) {
    echo "<h2>Testing Token Table</h2>";
    
    try {
        // Check if the table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        $tableExists = $stmt->rowCount() > 0;
        debug_log("Table exists", $tableExists);
        
        if (!$tableExists) {
            // Create the table
            $sql = "
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                reset_id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                student_id INT DEFAULT NULL,
                admin_id INT DEFAULT NULL,
                INDEX (email),
                INDEX (token),
                INDEX (student_id),
                INDEX (admin_id)
            )";
            
            $result = $pdo->exec($sql);
            debug_log("Table creation result", ["sql" => $sql, "result" => $result]);
            
            // Check if table was created
            $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
            $tableExists = $stmt->rowCount() > 0;
            debug_log("Table exists after creation attempt", $tableExists);
        }
        
        // Describe the table
        $stmt = $pdo->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        debug_log("Table structure", $columns);
        
        return $tableExists;
    } catch (Exception $e) {
        debug_log("Table check error", $e->getMessage());
        return false;
    }
}

// Test token insertion
function test_token_insertion($pdo) {
    echo "<h2>Testing Token Insertion</h2>";
    
    try {
        // Generate a test token
        $testEmail = 'test@example.com';
        $testToken = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        debug_log("Generated test data", [
            "email" => $testEmail,
            "token" => $testToken,
            "expires_at" => $expiresAt
        ]);
        
        // Delete any existing tokens for this test email
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
        $stmt->execute([$testEmail]);
        debug_log("Deleted existing test tokens", ["affected_rows" => $stmt->rowCount()]);
        
        // Insert new token using prepared statement
        $sql = "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$testEmail, $testToken, $expiresAt]);
        
        debug_log("Token insertion result", [
            "success" => $result,
            "error_code" => $stmt->errorCode(),
            "error_info" => $stmt->errorInfo()
        ]);
        
        if ($result) {
            $lastId = $pdo->lastInsertId();
            debug_log("Last insert ID", $lastId);
            
            // Verify the insertion
            $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE reset_id = ?");
            $stmt->execute([$lastId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            debug_log("Verification of inserted token", $record);
            
            return $record;
        } else {
            return false;
        }
    } catch (Exception $e) {
        debug_log("Token insertion error", $e->getMessage());
        return false;
    }
}

// Show all tokens
function show_all_tokens($pdo) {
    echo "<h2>All Tokens in Table</h2>";
    
    try {
        $stmt = $pdo->query("SELECT * FROM password_reset_tokens");
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        debug_log("Total tokens", count($tokens));
        
        echo "<table border='1'>";
        echo "<tr><th>Reset ID</th><th>Email</th><th>Token</th><th>Created</th><th>Expires</th><th>Student ID</th><th>Admin ID</th></tr>";
        
        foreach ($tokens as $token) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($token['reset_id']) . "</td>";
            echo "<td>" . htmlspecialchars($token['email']) . "</td>";
            echo "<td>" . htmlspecialchars($token['token']) . "</td>";
            echo "<td>" . htmlspecialchars($token['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($token['expires_at']) . "</td>";
            echo "<td>" . htmlspecialchars($token['student_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($token['admin_id'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        return $tokens;
    } catch (Exception $e) {
        debug_log("Error retrieving tokens", $e->getMessage());
        return [];
    }
}

// Show the most recent PHP error logs
function show_error_logs() {
    echo "<h2>Recent PHP Error Logs</h2>";
    
    // Try to get the PHP error log file location
    $error_log_path = ini_get('error_log');
    echo "<p>PHP error_log path: " . htmlspecialchars($error_log_path ?: 'Not configured') . "</p>";
    
    // Try to read the error log file
    if ($error_log_path && file_exists($error_log_path) && is_readable($error_log_path)) {
        echo "<h3>Last 50 lines of error log:</h3>";
        echo "<pre style='background-color: #f8f8f8; padding: 10px; overflow: auto; max-height: 400px;'>";
        
        // Get the last 50 lines of the log file
        $log_lines = [];
        $file = new SplFileObject($error_log_path);
        $file->seek(PHP_INT_MAX); // Seek to the end of file
        $total_lines = $file->key(); // Get the total number of lines
        
        $start_line = max(0, $total_lines - 50);
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $line = $file->fgets();
            if ($line) {
                // Highlight reset password related logs
                if (strpos($line, 'RESET_PASSWORD:') !== false) {
                    echo "<strong style='color: #cc0000;'>" . htmlspecialchars($line) . "</strong>";
                } else {
                    echo htmlspecialchars($line);
                }
            }
        }
        
        echo "</pre>";
    } else {
        echo "<p>Could not read error log file. It may not exist, or the web server may not have permission to read it.</p>";
        
        // Try to find other possible log locations
        $possible_locations = [
            $_SERVER['DOCUMENT_ROOT'] . '/php_error.log',
            dirname($_SERVER['DOCUMENT_ROOT']) . '/logs/php_error.log',
            'C:/wamp64/logs/php_error.log'
        ];
        
        echo "<p>Checking alternative log locations:</p>";
        echo "<ul>";
        foreach ($possible_locations as $location) {
            echo "<li>" . htmlspecialchars($location) . ": " . (file_exists($location) ? "Exists" : "Not found") . "</li>";
        }
        echo "</ul>";
    }
}

// Run the tests
echo "<html><head><title>Password Reset Token Tests</title></head><body>";
echo "<h1>Password Reset Token Tests</h1>";

// Test database connection
$pdo = test_db_connection();

if ($pdo) {
    // Test token table
    $tableExists = test_token_table($pdo);
    
    if ($tableExists) {
        // Test token insertion
        $insertResult = test_token_insertion($pdo);
        
        // Show all tokens
        $tokens = show_all_tokens($pdo);
    }
}

// Show error logs
show_error_logs();

echo "</body></html>"; 