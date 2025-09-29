<?php
/**
 * Admin Class
 * Handles all administrative logic, data retrieval, and manipulations.
 * KSG SMI Performance System
 */

class Admin {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Logs in an administrator.
     * @param string $email
     * @param string $password
     * @param string $index_code
     * @return array|false
     */
    public function login($email, $password, $index_code) {
        $admin = $this->db->fetch("SELECT * FROM admins WHERE email = :email", [':email' => $email]);

        if ($admin && password_verify($password, $admin['password']) && password_verify($index_code, $admin['index_code'])) {
            return $admin;
        }
        return false;
    }

    /**
     * Registers a new administrator.
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $index_code
     * @return array
     */
    public function register($name, $email, $password, $index_code) {
        $existing = $this->db->fetch("SELECT id FROM admins WHERE email = :email", [':email' => $email]);
        if ($existing) {
            return ['status' => 'error', 'message' => 'An admin with this email already exists.'];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_index_code = password_hash($index_code, PASSWORD_DEFAULT);

        $sql = "INSERT INTO admins (name, email, password, index_code) VALUES (:name, :email, :password, :index_code)";
        $params = [
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashed_password,
            ':index_code' => $hashed_index_code
        ];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'Admin registered successfully.'];
        }
        return ['status' => 'error', 'message' => 'Registration failed.'];
    }

    /**
     * Retrieves all users from the database.
     * @return array
     */
    public function getAllUsers() {
        $users = $this->db->fetchAll("SELECT id, name, email, department, job_title, status, created_at FROM users ORDER BY name ASC");
        return ['status' => 'success', 'users' => $users];
    }

    /**
     * Retrieves statistics about users.
     * @return array
     */
    public function getUserStats() {
        $total_users = $this->db->fetch("SELECT COUNT(*) as count FROM users")['count'];
        $active_users = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'];
        $recent_logins = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)")['count'];
        $inactive_users = $total_users - $active_users;

        $stats = [
            'total_users' => $total_users,
            'active_users' => $active_users,
            'recent_logins' => $recent_logins,
            'inactive_users' => $inactive_users
        ];
        return ['status' => 'success', 'stats' => $stats];
    }

    /**
     * Retrieves overall system analytics for the admin dashboard.
     * @return array
     */
    public function getSystemAnalytics() {
        $total_tasks = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks")['count'];
        $completed_tasks = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks WHERE status = 'completed'")['count'];
        $completion_rate = ($total_tasks > 0) ? round(($completed_tasks / $total_tasks) * 100) : 0;
        $active_users = $this->db->fetch("SELECT COUNT(DISTINCT id) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)")['count'];
        $avg_time = $this->db->fetch("SELECT AVG(TIMESTAMPDIFF(DAY, created_date, completed_date)) as avg_days FROM user_tasks WHERE status = 'completed' AND completed_date IS NOT NULL");
        $most_active_dept = $this->db->fetch("SELECT u.department FROM user_tasks t JOIN users u ON t.user_id = u.id GROUP BY u.department ORDER BY COUNT(t.id) DESC LIMIT 1");
        $peak_hours = $this->db->fetch("SELECT HOUR(timestamp) as hour, COUNT(*) as count FROM access_logs GROUP BY HOUR(timestamp) ORDER BY count DESC LIMIT 1");

        $analytics = [
            'total_users' => $this->db->fetch("SELECT COUNT(*) as count FROM users")['count'],
            'total_tasks' => $total_tasks,
            'completion_rate' => $completion_rate,
            'active_users' => $active_users,
            'avg_completion_time' => $avg_time['avg_days'] ? round($avg_time['avg_days'], 1) . ' days' : 'N/A',
            'most_active_dept' => $most_active_dept['department'] ?? 'N/A',
            'peak_hours' => $peak_hours ? date("g A", mktime($peak_hours['hour'], 0)) : 'N/A'
        ];
        return ['status' => 'success', 'analytics' => $analytics];
    }

    /**
     * Assigns a predefined task to a user.
     * @param array $data
     * @return array
     */
    public function assignPredefinedTask($data) {
        if (empty($data['user_id']) || empty($data['title']) || empty($data['due_date'])) {
            return ['status' => 'error', 'message' => 'User, title, and due date are required.'];
        }

        $sql = "INSERT INTO user_tasks (user_id, title, category, priority, due_date, instructions, assigned_by, assigned_by_id)
                VALUES (:user_id, :title, :category, :priority, :due_date, :instructions, :assigned_by, :assigned_by_id)";
        
        $params = [
            ':user_id' => $data['user_id'],
            ':title' => $data['title'],
            ':category' => $data['category'] ?? null,
            ':priority' => $data['priority'] ?? 'medium',
            ':due_date' => $data['due_date'],
            ':instructions' => $data['instructions'] ?? null,
            ':assigned_by' => $_SESSION['admin_name'],
            ':assigned_by_id' => $_SESSION['admin_id']
        ];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'Task assigned successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to assign task.'];
    }

    /**
     * Retrieves recently assigned tasks.
     * @param int $limit
     * @return array
     */
    public function getRecentAssignments($limit = 10) {
        $sql = "SELECT t.title, t.due_date, t.status, u.name as user_name
                FROM user_tasks t
                JOIN users u ON t.user_id = u.id
                ORDER BY t.created_date DESC
                LIMIT :limit";
        $assignments = $this->db->fetchAll($sql, [':limit' => (int)$limit]);
        return ['status' => 'success', 'assignments' => $assignments];
    }

    /**
     * Retrieves recent access logs.
     * @param int $limit
     * @return array
     */
    public function getAccessLogs($limit = 15) {
        $sql = "SELECT al.action, al.user_type, al.ip_address, al.timestamp, COALESCE(u.name, a.name) as user_name
                FROM access_logs al
                LEFT JOIN users u ON al.user_id = u.id
                LEFT JOIN admins a ON al.admin_id = a.id
                ORDER BY al.timestamp DESC
                LIMIT :limit";
        $logs = $this->db->fetchAll($sql, [':limit' => (int)$limit]);
        return ['status' => 'success', 'logs' => $logs];
    }

    /**
     * Creates a system backup (database dump).
     * This is a simplified example. A robust solution would use mysqldump via shell_exec.
     * @param int $admin_id
     * @return array
     */
    public function createBackup($admin_id) {
        // In a real-world scenario, ensure this path is secure and writable.
        $backupDir = dirname(__DIR__) . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backup_name = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $file_path = $backupDir . '/' . $backup_name;

        // This is a basic implementation. Using `mysqldump` is highly recommended.
        try {
            $command = sprintf(
                'mysqldump --user=%s --password=%s --host=%s %s > %s',
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_NAME),
                escapeshellarg($file_path)
            );
            
            shell_exec($command);
            
            if (!file_exists($file_path) || filesize($file_path) === 0) {
                 throw new Exception("Backup file was not created or is empty. Check mysqldump configuration and permissions.");
            }

            $file_size = filesize($file_path);

            $sql = "INSERT INTO system_backups (backup_name, file_path, file_size, created_by) VALUES (:name, :path, :size, :by)";
            $this->db->execute($sql, [
                ':name' => $backup_name,
                ':path' => $file_path,
                ':size' => $file_size,
                ':by' => $admin_id
            ]);

            return ['status' => 'success', 'message' => 'Backup created successfully.', 'backup_name' => $backup_name];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Backup failed: ' . $e->getMessage()];
        }
    }

    /**
     * Retrieves the history of system backups.
     * @return array
     */
    public function getBackupHistory() {
        $backups = $this->db->fetchAll("SELECT id, backup_name, file_size, created_at FROM system_backups ORDER BY created_at DESC");
        return ['status' => 'success', 'backups' => $backups];
    }

    /**
     * Handles the download of a specific backup file.
     * @param int $backup_id
     */
    public function downloadBackup($backup_id) {
        $backup = $this->db->fetch("SELECT file_path, backup_name FROM system_backups WHERE id = :id", [':id' => $backup_id]);

        if ($backup && file_exists($backup['file_path'])) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backup['backup_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup['file_path']));
            readfile($backup['file_path']);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Backup file not found.']);
            exit;
        }
    }

    /**
     * Deletes a task.
     * @param int $task_id
     * @return array
     */
    public function deleteTask($task_id) {
        $this->db->execute("DELETE FROM user_tasks WHERE id = :id", [':id' => $task_id]);
        if ($this->db->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'Task deleted successfully.'];
        }
        return ['status' => 'error', 'message' => 'Task not found or could not be deleted.'];
    }

    /**
     * Deletes a task upload.
     * @param int $upload_id
     * @return array
     */
    public function deleteTaskUpload($upload_id) {
        $this->db->execute("DELETE FROM task_uploads WHERE id = :id", [':id' => $upload_id]);
        if ($this->db->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'File deleted successfully.'];
        }
        return ['status' => 'error', 'message' => 'File not found or could not be deleted.'];
    }
}
?>