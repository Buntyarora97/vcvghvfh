<?php
/**
 * Chatbot Builder System - Database Class
 * PDO Wrapper with prepared statements
 */

namespace Chatbot;

use PDO;
use PDOException;
use PDOStatement;

class Database {
    private static ?PDO $instance = null;
    private PDO $connection;
    
    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        try {
            $this->connection = new PDO(
                Config::getDSN(),
                Config::DB_USER,
                Config::DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . Config::DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed. Please try again later.");
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return new self();
    }
    
    /**
     * Get raw PDO connection
     */
    public function getConnection(): PDO {
        return $this->connection;
    }
    
    /**
     * Execute a query with parameters
     */
    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Fetch single row
     */
    public function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Fetch single column
     */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($column);
    }
    
    /**
     * Insert data and return last insert ID
     */
    public function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Update data
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = ?";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete data
     */
    public function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->connection->rollBack();
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction(): bool {
        return $this->connection->inTransaction();
    }
    
    /**
     * Execute within transaction
     */
    public function transaction(callable $callback): mixed {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get row count for table
     */
    public function count(string $table, string $where = '1', array $params = []): int {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return (int) $this->fetchColumn($sql, $params);
    }
    
    /**
     * Check if record exists
     */
    public function exists(string $table, string $where, array $params = []): bool {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Build WHERE clause from conditions
     */
    public function buildWhere(array $conditions): array {
        $where = [];
        $params = [];
        
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                $where[] = "{$key} IN (" . implode(', ', array_fill(0, count($value), '?')) . ")";
                $params = array_merge($params, $value);
            } elseif ($value === null) {
                $where[] = "{$key} IS NULL";
            } else {
                $where[] = "{$key} = ?";
                $params[] = $value;
            }
        }
        
        return [
            'clause' => implode(' AND ', $where),
            'params' => $params
        ];
    }
    
    /**
     * Paginated query
     */
    public function paginate(string $sql, array $params = [], int $page = 1, int $perPage = 20): array {
        $offset = ($page - 1) * $perPage;
        $paginatedSql = $sql . " LIMIT {$perPage} OFFSET {$offset}";
        
        // Get total count
        $countSql = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) FROM', $sql, 1);
        $countSql = preg_replace('/ORDER BY.*$/i', '', $countSql);
        $total = (int) $this->fetchColumn($countSql, $params);
        
        // Get paginated data
        $data = $this->fetchAll($paginatedSql, $params);
        
        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
                'has_next' => ($page * $perPage) < $total,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Raw SQL execution
     */
    public function raw(string $sql): int {
        return $this->connection->exec($sql);
    }
    
    /**
     * Quote identifier
     */
    public function quoteIdentifier(string $identifier): string {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    /**
     * Close connection
     */
    public function close(): void {
        $this->connection = null;
        self::$instance = null;
    }
}
