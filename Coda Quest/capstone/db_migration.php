<?php
/**
 * Database Migration Script for CodaQuest
 * 
 * This script adds a reset_code column to the users table and
 * generates random reset codes for all existing users.
 */

// Include database connection
require_once 'config/db_connect.php';

// Function to generate a random reset code
function generateResetCode() {
    // Generate an 8-digit numeric code
    return sprintf("%08d", mt_rand(10000000, 99999999));
}

try {
    // Check if users table exists
    $tableCheckSql = "SHOW TABLES LIKE 'users'";
    $tableExists = executeSimpleQuery($tableCheckSql);
    
    if (empty($tableExists)) {
        die("Error: The users table does not exist.");
    }
    
    // Check if reset_code column already exists
    $columnCheckSql = "SHOW COLUMNS FROM users LIKE 'reset_code'";
    $columnExists = executeSimpleQuery($columnCheckSql);
    
    if (empty($columnExists)) {
        // Add reset_code column to users table
        $alterTableSql = "ALTER TABLE users ADD COLUMN reset_code VARCHAR(32) DEFAULT NULL";
        $alterResult = executeSimpleQuery($alterTableSql);
        
        if ($alterResult === false) {
            die("Error: Failed to add reset_code column to users table.");
        }
        
        echo "Successfully added reset_code column to users table.<br>";
        
        // Get all users
        $getUsersSql = "SELECT user_id FROM users";
        $users = executeSimpleQuery($getUsersSql);
        
        if (!empty($users)) {
            $updatedCount = 0;
            
            // Generate and update reset code for each user
            foreach ($users as $user) {
                $userId = $user['user_id'];
                $resetCode = generateResetCode();
                
                $updateSql = "UPDATE users SET reset_code = ? WHERE user_id = ?";
                $updateResult = executeQuery($updateSql, [$resetCode, $userId]);
                
                if ($updateResult !== false) {
                    $updatedCount++;
                }
            }
            
            echo "Generated reset codes for {$updatedCount} out of " . count($users) . " users.<br>";
        } else {
            echo "No users found in the database.<br>";
        }
    } else {
        echo "The reset_code column already exists in the users table.<br>";
        
        // Update any NULL reset_code values
        $nullCodesSql = "SELECT user_id FROM users WHERE reset_code IS NULL";
        $nullCodes = executeSimpleQuery($nullCodesSql);
        
        if (!empty($nullCodes)) {
            $updatedCount = 0;
            
            foreach ($nullCodes as $user) {
                $userId = $user['user_id'];
                $resetCode = generateResetCode();
                
                $updateSql = "UPDATE users SET reset_code = ? WHERE user_id = ?";
                $updateResult = executeQuery($updateSql, [$resetCode, $userId]);
                
                if ($updateResult !== false) {
                    $updatedCount++;
                }
            }
            
            echo "Generated reset codes for {$updatedCount} users with missing codes.<br>";
        } else {
            echo "All users already have reset codes.<br>";
        }
    }
    
    echo "Migration completed successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 