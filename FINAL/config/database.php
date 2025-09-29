<?php
/**
 * Database Configuration and Connection
 * KSG SMI Performance System
 */

// --- Session Management ---
// Start the session if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Database Credentials ---
// These values are typically set by the installer (install/setup.php)
// and should be secured.
define('DB_HOST', 'localhost');
define('DB_NAME', 'ksg_smi_performance');
define('DB_USER', 'root');
define('DB_PASS', ''); // Use a strong password in production
define('DB_CHARSET', 'utf8mb4');

// --- Application Settings ---

// Session timeout in seconds (e.g., 30 minutes = 1800)
define('SESSION_TIMEOUT', 1800);

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_FILE_TYPES', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
    'jpg', 'jpeg', 'png', 'gif', 'txt', 'csv'
]);


/**
 * Class Database
 * A wrapper for PDO to handle database connections and queries securely.
 */
class Database {
    private $pdo;
    private $stmt;

    /**
     * Database constructor.
     * Establishes a new PDO database connection.
     */
    public function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In a production environment, you would log this error and show a generic message.
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    /**
     * Prepares and executes a SQL query with bound parameters.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An associative array of parameters to bind.
     * @return bool True on success, false on failure.
     */
    public function execute($sql, $params = []) {
        $this->stmt = $this->pdo->prepare($sql);
        return $this->stmt->execute($params);
    }

    /**
     * Fetches all rows from a result set.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An associative array of parameters to bind.
     * @return array An array of result rows.
     */
    public function fetchAll($sql, $params = []) {
        $this->execute($sql, $params);
        return $this->stmt->fetchAll();
    }

    /**
     * Fetches a single row from a result set.
     *
     * @param string $sql The SQL query to execute.
     * @param array $params An associative array of parameters to bind.
     * @return mixed A single result row or false if no row is found.
     */
    public function fetch($sql, $params = []) {
        $this->execute($sql, $params);
        return $this->stmt->fetch();
    }

    /**
     * Returns the ID of the last inserted row.
     *
     * @return string The last insert ID.
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    /**
     * Returns the number of rows affected by the last SQL statement.
     *
     * @return int The number of affected rows.
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }

    /**
     * Closes the database connection.
     */
    public function __destruct() {
        $this->pdo = null;
    }
}
?>