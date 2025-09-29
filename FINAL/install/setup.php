
I reviewed your snippet and fixed the issues (truncated line, unescaped DB selection and messaging) and produced a corrected, hardened setup.php installer file. The key fixes:

- Restored the missing/garbled connection error check and ensured mysqli connection handling is correct.
- Escaped the database name before creating and selecting the database and used the escaped value for select_db().
- Kept safe use of real_escape_string for values that are interpolated into SQL.
- Retained password_hash() for admin password handling and improved error messages.
- Preserved the SQL import fallback logic and safety checks.

Below is the full corrected setup.php you can drop into your project (replace the existing file). It includes the changes above and the same UI/form you had.

```php name=setup.php
<?php
/**
 * setup.php
 * KSG SMI Performance System - Web installer
 *
 * - Creates the MySQL database (if not exists)
 * - Imports SQL schema from ../database/ksg_smi_performance.sql
 * - Creates ../config/database.php with safe, exported constants
 * - Optionally seeds an admin user (admin email + password from form)
 *
 * Security note: remove this file after successful installation.
 */

declare(strict_types=1);

$page_title = "SMI Performance System - Installer";
$message = '';
$message_type = 'info'; // info | success | error

// Paths
$config_path = __DIR__ . '/../config/database.php';
$default_sql_path = __DIR__ . '/../database/ksg_smi_performance.sql';

// Helpers
function is_config_writable(string $config_path): bool {
    $dir = dirname($config_path);
    if (!is_dir($dir)) {
        // try create directory
        @mkdir($dir, 0750, true);
    }
    return is_dir($dir) && is_writable($dir);
}

/**
 * Import SQL file via mysqli multi_query.
 * Preprocess: remove DELIMITER lines and client-only statements; strip CREATE DATABASE / USE.
 */
function import_sql_file(mysqli $conn, string $sql_file): bool {
    if (!file_exists($sql_file)) {
        throw new Exception("SQL file not found: {$sql_file}");
    }

    $size = filesize($sql_file);
    if ($size === false) $size = 0;
    // prevent importing extremely large files accidentally
    if ($size > 150 * 1024 * 1024) { // 150MB
        throw new Exception("SQL file too large ({$size} bytes). Please import manually or use a smaller dump.");
    }

    $sql = file_get_contents($sql_file);
    if ($sql === false) {
        throw new Exception("Unable to read SQL file: {$sql_file}");
    }

    // Normalize newlines
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);

    // Remove mysql client specific commands that mysqli can't handle:
    // - DELIMITER lines
    // - CREATE DATABASE / USE statements (installer handles DB)
    $lines = explode("\n", $sql);
    $filtered = [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '') {
            $filtered[] = $line;
            continue;
        }
        if (stripos($trim, 'DELIMITER ') === 0) {
            continue;
        }
        if (preg_match('/^CREATE\s+DATABASE\s+/i', $trim)) continue;
        if (preg_match('/^USE\s+/i', $trim)) continue;
        $filtered[] = $line;
    }
    $sql = implode("\n", $filtered);

    // Try multi_query; if it fails, attempt split-by-semicolon fallback
    if ($conn->multi_query($sql)) {
        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        return true;
    }

    // Fallback: split statements by semicolon followed by newline (best-effort)
    $stmts = preg_split('/;\s*\n/', $sql);
    if ($stmts === false) {
        throw new Exception("Failed to parse SQL file for fallback import.");
    }

    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        if (!$conn->query($stmt)) {
            throw new Exception("Error executing SQL statement: " . $conn->error . " -- Statement: " . substr($stmt, 0, 200) . '...');
        }
    }

    return true;
}

/**
 * Build config file content safely.
 */
function build_config_content(array $db): string {
    $host = var_export($db['host'], true);
    $name = var_export($db['name'], true);
    $user = var_export($db['user'], true);
    $pass = var_export($db['pass'], true);
    $charset = var_export($db['charset'], true);
    $now = date('c');

    $content = <<<PHP
<?php
/**
 * Database Configuration and Connection
 * Generated on: {$now}
 * NOTE: delete the installer after setup.
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', {$host});
define('DB_NAME', {$name});
define('DB_USER', {$user});
define('DB_PASS', {$pass});
define('DB_CHARSET', {$charset});

define('SESSION_TIMEOUT', 1800);
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', [
    'pdf','doc','docx','xls','xlsx','ppt','pptx',
    'jpg','jpeg','png','gif','txt','csv'
]);

class Database {
    private \$pdo;
    private \$stmt;

    public function __construct() {
        \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            \$this->pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            throw new PDOException(\$e->getMessage(), (int)\$e->getCode());
        }
    }

    public function execute(\$sql, \$params = []) {
        \$this->stmt = \$this->pdo->prepare(\$sql);
        return \$this->stmt->execute(\$params);
    }

    public function fetchAll(\$sql, \$params = []) {
        \$this->execute(\$sql, \$params);
        return \$this->stmt->fetchAll();
    }

    public function fetch(\$sql, \$params = []) {
        \$this->execute(\$sql, \$params);
        return \$this->stmt->fetch();
    }

    public function lastInsertId() {
        return \$this->pdo->lastInsertId();
    }

    public function rowCount() {
        return \$this->stmt->rowCount();
    }

    public function __destruct() {
        \$this->pdo = null;
    }
}

PHP;

    return $content;
}

// Form handling
$is_config_writable = is_config_writable($config_path);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? 'localhost');
    $db_name = trim($_POST['db_name'] ?? 'ksg_smi_performance');
    $db_user = trim($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    $sql_file = trim($_POST['sql_file'] ?? $default_sql_path);

    // validate sql_file path: restrict to ../database directory for safety
    $allowed_dir = realpath(__DIR__ . '/../database');
    $sql_real = realpath($sql_file);
    if ($sql_real === false || $allowed_dir === false || strpos($sql_real, $allowed_dir) !== 0) {
        $sql_file = $default_sql_path; // fallback to default
    }

    // optional admin seeding
    $admin_name = trim($_POST['admin_name'] ?? 'System Administrator');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_index_code = trim($_POST['admin_index_code'] ?? '');

    if ($db_host === '' || $db_name === '' || $db_user === '') {
        $message = 'Please provide database host, name and user.';
        $message_type = 'error';
    } elseif (!is_config_writable($config_path)) {
        $message = 'Config directory is not writable: ' . htmlspecialchars(dirname($config_path));
        $message_type = 'error';
    } else {
        try {
            // 1. Connect to MySQL server (no db selected)
            $conn = new mysqli($db_host, $db_user, $db_pass);
            if ($conn->connect_error) {
                throw new Exception("MySQL connection failed: " . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');

            // 2. Create database if not exists (escape name, use escaped for creation and selection)
            $db_name_esc = $conn->real_escape_string($db_name);
            $create_sql = "CREATE DATABASE IF NOT EXISTS `{$db_name_esc}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
            if (!$conn->query($create_sql)) {
                throw new Exception("Failed to create database " . htmlspecialchars($db_name) . ": " . $conn->error);
            }

            // 3. Select the database (use escaped name)
            if (!$conn->select_db($db_name_esc)) {
                throw new Exception("Failed to select database " . htmlspecialchars($db_name) . ": " . $conn->error);
            }

            // 4. Import schema
            import_sql_file($conn, $sql_file);

            // 5. Optionally create admin user (if provided)
            if ($admin_email !== '' && $admin_password !== '') {
                $res = $conn->query("SHOW TABLES LIKE 'users'");
                if ($res && $res->num_rows > 0) {
                    $email_esc = $conn->real_escape_string($admin_email);
                    $check = $conn->query("SELECT id FROM users WHERE email = '{$email_esc}' LIMIT 1");
                    if ($check && $check->num_rows > 0) {
                        $row = $check->fetch_assoc();
                        $uid = (int)$row['id'];
                        $hash = password_hash($admin_password, PASSWORD_DEFAULT);
                        $hash_esc = $conn->real_escape_string($hash);
                        $name_esc = $conn->real_escape_string($admin_name);
                        $code_esc = $conn->real_escape_string($admin_index_code);
                        $update_sql = "UPDATE users SET name = '{$name_esc}', password = '{$hash_esc}', role = 'admin', index_code = '{$code_esc}', is_active = 1 WHERE id = {$uid}";
                        if (!$conn->query($update_sql)) {
                            throw new Exception("Failed to update existing admin user: " . $conn->error);
                        }
                    } else {
                        $hash = password_hash($admin_password, PASSWORD_DEFAULT);
                        $name_esc = $conn->real_escape_string($admin_name);
                        $email_esc = $conn->real_escape_string($admin_email);
                        $hash_esc = $conn->real_escape_string($hash);
                        $code_esc = $conn->real_escape_string($admin_index_code);
                        $insert_sql = "INSERT INTO users (name, email, password, role, index_code, is_active, created_at) VALUES ('{$name_esc}', '{$email_esc}', '{$hash_esc}', 'admin', '{$code_esc}', 1, NOW())";
                        if (!$conn->query($insert_sql)) {
                            throw new Exception("Failed to insert admin user: " . $conn->error);
                        }
                    }
                } else {
                    $message .= 'Warning: users table not found; admin user not created. ';
                }
            }

            // 6. Create config file
            $config_content = build_config_content([
                'host' => $db_host,
                'name' => $db_name,
                'user' => $db_user,
                'pass' => $db_pass,
                'charset' => 'utf8mb4'
            ]);

            if (file_put_contents($config_path, $config_content) === false) {
                throw new Exception("Failed to write config file to {$config_path}. Check filesystem permissions.");
            }

            // tighten permissions if possible
            @chmod($config_path, 0640);

            $message = 'Installation completed successfully. Database imported and configuration file created.';
            if ($admin_email !== '' && $admin_password !== '') {
                $message .= ' Admin account created/updated: ' . htmlspecialchars($admin_email);
            }
            $message_type = 'success';

            $conn->close();
        } catch (Exception $e) {
            $message = 'Installation error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">
  <div class="w-full max-w-2xl p-6">
    <div class="bg-white shadow rounded-lg p-6">
      <h1 class="text-2xl font-bold mb-4">SMI Performance System — Installer</h1>

      <?php if (!empty($message)): ?>
        <div class="mb-4 p-4 rounded <?php echo $message_type === 'success' ? 'bg-green-50 border-l-4 border-green-500 text-green-700' : 'bg-red-50 border-l-4 border-red-500 text-red-700'; ?>">
          <?php echo nl2br(htmlspecialchars($message)); ?>
        </div>
      <?php endif; ?>

      <form method="post" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">DB Host</label>
            <input name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? 'localhost'); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">DB Name</label>
            <input name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? 'ksg_smi_performance'); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">DB User</label>
            <input name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? 'root'); ?>" required class="mt-1 block w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">DB Password</label>
            <input type="password" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? ''); ?>" class="mt-1 block w-full border rounded px-3 py-2">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">SQL Schema File Path</label>
          <input name="sql_file" value="<?php echo htmlspecialchars($_POST['sql_file'] ?? $default_sql_path); ?>" class="mt-1 block w-full border rounded px-3 py-2">
          <p class="text-xs text-gray-500 mt-1">Path to SQL file to import (default: ../database/ksg_smi_performance.sql). For safety the path is restricted to the project's /database directory.</p>
        </div>

        <hr class="my-4">

        <h2 class="text-lg font-semibold">Admin account (optional)</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Admin Name</label>
            <input name="admin_name" value="<?php echo htmlspecialchars($_POST['admin_name'] ?? 'System Administrator'); ?>" class="mt-1 block w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Admin Email</label>
            <input name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" class="mt-1 block w-full border rounded px-3 py-2">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Admin Password</label>
            <input type="password" name="admin_password" value="" class="mt-1 block w-full border rounded px-3 py-2">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Admin Index Code (optional)</label>
          <input name="admin_index_code" value="<?php echo htmlspecialchars($_POST['admin_index_code'] ?? ''); ?>" class="mt-1 block w-full border rounded px-3 py-2">
          <p class="text-xs text-gray-500 mt-1">If your admin workflow uses index codes to validate registration you can set it here.</p>
        </div>

        <div class="flex items-center justify-between">
          <div>
            <?php if (!$is_config_writable): ?>
              <p class="text-sm text-red-600">Warning: config directory is not writable. Please grant write permissions to <?php echo htmlspecialchars(dirname($config_path)); ?> before installing.</p>
            <?php else: ?>
              <p class="text-sm text-green-700">Config directory is writable: <?php echo htmlspecialchars(dirname($config_path)); ?></p>
            <?php endif; ?>
          </div>
          <div>
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700" <?php if (!$is_config_writable) echo 'disabled'; ?>>Install</button>
          </div>
        </div>
      </form>

      <div class="mt-6 text-sm text-gray-600">
        <p><strong>Important:</strong> After successful installation delete this file (setup.php) and any installer folders to prevent unauthorized reinstallation.</p>
      </div>
    </div>
  </div>
</body>
</html>