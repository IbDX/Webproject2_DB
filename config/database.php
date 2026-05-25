<?php
/**
 * Database Configuration - Secure PDO Connection
 * 
 * Features:
 * - PDO with error handling
 * - Prepared statement support
 * - Connection pooling ready
 * - Secure credential storage
 */

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'sql112.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_42012860_db_scure_bank');
define('DB_USER', getenv('DB_USER') ?: 'if0_42012860');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: '13579batman');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Application Configuration
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_HASH_ALGO', 'bcrypt');

// PDO DSN
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    DB_HOST,
    DB_PORT,
    DB_NAME,
    DB_CHARSET
);

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_TIMEOUT => 10,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

/**
 * Get Database Connection
 * 
 * @return PDO Database connection object
 * @throws PDOException
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                ),
                DB_USER,
                DB_PASSWORD,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            // Set session timezone
            $pdo->exec("SET SESSION time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw $e;
            }
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    
    return $pdo;
}

/**
 * Execute Prepared Statement
 * 
 * @param string $query SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function executePrepared($query, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch Single Row
 * 
 * @param string $query SQL query
 * @param array $params Parameters
 * @return array|null
 */
function fetchOne($query, $params = []) {
    $stmt = executePrepared($query, $params);
    return $stmt->fetch() ?: null;
}

/**
 * Fetch Multiple Rows
 * 
 * @param string $query SQL query
 * @param array $params Parameters
 * @return array
 */
function fetchAll($query, $params = []) {
    $stmt = executePrepared($query, $params);
    return $stmt->fetchAll();
}

/**
 * Insert Record
 * 
 * @param string $query SQL query
 * @param array $params Parameters
 * @return string Last insert ID
 */
function insert($query, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

/**
 * Update/Delete Record
 * 
 * @param string $query SQL query
 * @param array $params Parameters
 * @return int Number of affected rows
 */
function execute($query, $params = []) {
    $stmt = executePrepared($query, $params);
    return $stmt->rowCount();
}

/**
 * Begin Transaction
 */
function beginTransaction() {
    getDBConnection()->beginTransaction();
}

/**
 * Commit Transaction
 */
function commitTransaction() {
    getDBConnection()->commit();
}

/**
 * Rollback Transaction
 */
function rollbackTransaction() {
    getDBConnection()->rollBack();
}
?>
