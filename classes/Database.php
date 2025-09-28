<?php
/**
 * Database Connection Class
 * Handles database connections and operations
 */

class Database {
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
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->log('Query failed: ' . $e->getMessage() . ' SQL: ' . $sql);
            throw new Exception('Query failed: ' . $e->getMessage());
        }
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
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->log('Insert failed: ' . $e->getMessage() . ' Table: ' . $table);
            throw new Exception('Insert failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Update data
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(array_merge($data, $whereParams));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->log('Update failed: ' . $e->getMessage() . ' Table: ' . $table);
            throw new Exception('Update failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Delete data
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($whereParams);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->log('Delete failed: ' . $e->getMessage() . ' Table: ' . $table);
            throw new Exception('Delete failed: ' . $e->getMessage());
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
            throw new Exception('Select failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if database and tables exist
     */
    public function checkDatabase() {
        try {
            // Check if database exists
            $stmt = $this->connection->query("SELECT DATABASE()");
            $currentDb = $stmt->fetchColumn();
            
            if (!$currentDb) {
                throw new Exception('No database selected');
            }
            
            // Check if main tables exist
            $tables = [
                'instagram_comments',
                'instagram_messages',
                'instagram_mentions',
                'webhook_events_log'
            ];
            
            $existingTables = [];
            foreach ($tables as $table) {
                $stmt = $this->connection->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $table;
                }
            }
            
            return [
                'database' => $currentDb,
                'tables_exist' => count($existingTables),
                'tables_total' => count($tables),
                'existing_tables' => $existingTables,
                'missing_tables' => array_diff($tables, $existingTables)
            ];
            
        } catch (PDOException $e) {
            $this->log('Database check failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log messages to file
     */
    private function log($message) {
        $logFile = __DIR__ . '/../logs/database.log';
        
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
