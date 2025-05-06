<?php
/**
 * Database Utility Functions for CodaQuest
 * 
 * This file contains utility functions for database maintenance tasks.
 */

// Require the database connection
require_once 'db_connect.php';

/**
 * Reset AUTO_INCREMENT value for a specific table
 *
 * @param string $tableName The name of the table to reset
 * @return bool True if successful, false otherwise
 */
function resetAutoIncrement($tableName) {
    try {
        // Get the next available ID
        $sql = "SELECT MAX(CASE 
                   WHEN INSTR(COLUMN_NAME, '_id') > 0 THEN 
                     CONCAT('SELECT COALESCE(MAX(', COLUMN_NAME, '), 0) + 1 FROM ', TABLE_NAME) 
                   END) as reset_query
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = 'codaquest' 
                AND TABLE_NAME = ?
                AND COLUMN_KEY = 'PRI'";
        
        $result = executeQuery($sql, [$tableName]);
        
        if (!empty($result) && isset($result[0]['reset_query']) && !empty($result[0]['reset_query'])) {
            $resetQuery = $result[0]['reset_query'];
            $nextId = executeSimpleQuery($resetQuery);
            
            if (is_array($nextId) && !empty($nextId)) {
                $nextIdValue = reset($nextId[0]); // Get first value of first row
                
                // Reset the AUTO_INCREMENT value to the next available ID
                $alterSql = "ALTER TABLE $tableName AUTO_INCREMENT = $nextIdValue";
                executeSimpleQuery($alterSql);
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Error resetting AUTO_INCREMENT for $tableName: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all tables in the database
 *
 * @return array List of table names
 */
function getAllTables() {
    try {
        $sql = "SHOW TABLES FROM codaquest";
        $result = executeSimpleQuery($sql);
        
        $tables = [];
        if (is_array($result)) {
            foreach ($result as $row) {
                $tables[] = reset($row); // Get the first (and only) value in each row
            }
        }
        
        return $tables;
    } catch (Exception $e) {
        error_log("Error getting tables: " . $e->getMessage());
        return [];
    }
}

/**
 * Reset AUTO_INCREMENT values for all tables in the database
 *
 * @return array Associative array of table names and their reset status
 */
function resetAllAutoIncrements() {
    $tables = getAllTables();
    $results = [];
    
    foreach ($tables as $table) {
        $results[$table] = resetAutoIncrement($table);
    }
    
    return $results;
}

/**
 * Get the next available ID for a specific table
 *
 * @param string $tableName The name of the table
 * @return int|null The next available ID or null on failure
 */
function getNextAvailableId($tableName) {
    try {
        // Find the primary key column name
        $primaryKeySql = "SELECT COLUMN_NAME 
                          FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = 'codaquest' 
                          AND TABLE_NAME = ? 
                          AND COLUMN_KEY = 'PRI'";
                          
        $primaryKeyResult = executeQuery($primaryKeySql, [$tableName]);
        
        if (!empty($primaryKeyResult) && isset($primaryKeyResult[0]['COLUMN_NAME'])) {
            $primaryKey = $primaryKeyResult[0]['COLUMN_NAME'];
            
            // Get the max value
            $maxSql = "SELECT COALESCE(MAX($primaryKey), 0) + 1 AS next_id FROM $tableName";
            $maxResult = executeSimpleQuery($maxSql);
            
            if (is_array($maxResult) && !empty($maxResult) && isset($maxResult[0]['next_id'])) {
                return (int)$maxResult[0]['next_id'];
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting next available ID for $tableName: " . $e->getMessage());
        return null;
    }
}

/**
 * Get the current AUTO_INCREMENT value for a table
 *
 * @param string $tableName The name of the table
 * @return int|null The current AUTO_INCREMENT value or null on failure
 */
function getCurrentAutoIncrement($tableName) {
    try {
        $sql = "SELECT AUTO_INCREMENT 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = 'codaquest' 
                AND TABLE_NAME = ?";
                
        $result = executeQuery($sql, [$tableName]);
        
        if (!empty($result) && isset($result[0]['AUTO_INCREMENT'])) {
            return (int)$result[0]['AUTO_INCREMENT'];
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting AUTO_INCREMENT for $tableName: " . $e->getMessage());
        return null;
    }
}

/**
 * Optimize database tables
 *
 * @param array $tables List of tables to optimize or empty for all tables
 * @return bool True if successful, false otherwise
 */
function optimizeTables($tables = []) {
    try {
        if (empty($tables)) {
            $tables = getAllTables();
        }
        
        if (!empty($tables)) {
            $tableList = implode(', ', $tables);
            $sql = "OPTIMIZE TABLE $tableList";
            executeSimpleQuery($sql);
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error optimizing tables: " . $e->getMessage());
        return false;
    }
} 