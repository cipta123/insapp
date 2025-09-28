<?php
/**
 * Simple Database Class
 * Simplified version without the problematic checkDatabase method
 */

class SimpleDatabase {
    private static $instance = null;
    private $connection;
    private $config;
    
    private function __construct() {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['database']};charset={$this->config['charset']}";
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            $this->log('Database connection established successfully');
        } catch (PDOException $e) {
            $this->log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Insert data and return last insert ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            $insertId = $this->connection->lastInsertId();
            $this->log("Insert successful - Table: $table, ID: $insertId");
            return $insertId;
        } catch (PDOException $e) {
            $this->log('Insert failed: ' . $e->getMessage() . ' Table: ' . $table . ' SQL: ' . $sql);
            return false;
        }
    }
    
    /**
     * Select data
     */
    public function select($table, $columns = '*', $where = '', $whereParams = [], $orderBy = '', $limit = '') {
        $sql = "SELECT {$columns} FROM {$table}";
        
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        
        if (!empty($orderBy)) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if (!empty($limit)) {
            $sql .= " LIMIT {$limit}";
        }
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->log('Select failed: ' . $e->getMessage() . ' Table: ' . $table);
            return [];
        }
    }
    
    /**
     * Simple table existence check
     */
    public function tableExists($tableName) {
        try {
            $result = $this->connection->query("SHOW TABLES LIKE '$tableName'");
            return $result->rowCount() > 0;
        } catch (PDOException $e) {
            $this->log('Table check failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages to file
     */
    private function log($message) {
        $logFile = __DIR__ . '/../logs/simple_database.log';
        
        // Create logs directory if it doesn't exist
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
?>
