<?php
/**
 * Database Configuration
 * Monastery Healthcare and Donation Management System
 */

// Prevent direct access
if (!defined('INCLUDED')) {
    die('Direct access not permitted');
}

// Database Configuration Constants
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'monastery_system');
define('DB_CHARSET', 'utf8mb4');

/**
 * Database Connection Class
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get database instance (Singleton pattern)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a query with parameters
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch single result
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Insert data into table
     */
    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(',', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * Update data in table
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        $params = [];
        $i = 0;
        foreach ($data as $key => $value) {
            $paramName = ":set_{$key}";
            $fields[] = "{$key} = {$paramName}";
            $params[$paramName] = $value;
        }
        $fields = implode(', ', $fields);
        
        // Convert positional ? in where clause to named params
        $j = 0;
        $namedWhere = preg_replace_callback('/\?/', function($match) use (&$j, $whereParams, &$params) {
            $paramName = ":where_{$j}";
            $params[$paramName] = $whereParams[$j];
            $j++;
            return $paramName;
        }, $where);
        
        $sql = "UPDATE {$table} SET {$fields} WHERE {$namedWhere}";
        
        return $this->query($sql, $params);
    }
    
    /**
     * Delete data from table
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Initialize database connection
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log("Failed to initialize database: " . $e->getMessage());
    die("Database connection failed. Please check configuration.");
}
?>