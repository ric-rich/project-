<?php
/**
 * Task Class
 * Handles all logic related to tasks, including creation, updates, and file management.
 * KSG SMI Performance System
 */

class Task {
    private $db;

    // Public properties for task creation
    public $user_id;
    public $title;
    public $description;
    public $category;
    public $priority;
    public $due_date;
    public $assigned_by;
    public $assigned_by_id;
    public $instructions;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Retrieves tasks for a specific user, with optional filtering.
     * @param int $user_id
     * @param string $status
     * @param string $priority
     * @return array
     */
    public function getTasksByUserId($user_id, $status = '', $priority = '') {
        $sql = "SELECT * FROM user_tasks WHERE user_id = :user_id";
        $params = [':user_id' => $user_id];

        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        if (!empty($priority)) {
            $sql .= " AND priority = :priority";
            $params[':priority'] = $priority;
        }

        $sql .= " ORDER BY due_date ASC";
        $tasks = $this->db->fetchAll($sql, $params);
        return ['status' => 'success', 'tasks' => $tasks];
    }

    /**
     * Retrieves a single task by its ID.
     * @param int $task_id
     * @param int|null $user_id_to_check Optional. If provided, checks if the task belongs to this user.
     * @return array
     */
    public function getTaskById($task_id, $user_id_to_check = null) {
        $sql = "SELECT t.*, u.name as user_name FROM user_tasks t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = :task_id";
        $task = $this->db->fetch($sql, [':task_id' => $task_id]);

        if ($task) {
            // If a user ID is provided, perform an ownership check
            if ($user_id_to_check !== null && $task['user_id'] != $user_id_to_check) {
                http_response_code(403);
                return ['status' => 'error', 'message' => 'Access denied to this task.'];
            }
            return ['status' => 'success', 'task' => $task];
        }

        http_response_code(404);
        return ['status' => 'error', 'message' => 'Task not found.'];
    }

    /**
     * Creates a new task using the public properties of the class.
     * @return array
     */
    public function createTask() {
        $sql = "INSERT INTO user_tasks (user_id, title, description, category, priority, due_date, assigned_by, assigned_by_id, instructions)
                VALUES (:user_id, :title, :description, :category, :priority, :due_date, :assigned_by, :assigned_by_id, :instructions)";
        
        $params = [
            ':user_id' => $this->user_id,
            ':title' => $this->title,
            ':description' => $this->description,
            ':category' => $this->category,
            ':priority' => $this->priority,
            ':due_date' => $this->due_date,
            ':assigned_by' => $this->assigned_by,
            ':assigned_by_id' => $this->assigned_by_id,
            ':instructions' => $this->instructions
        ];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'Task created successfully.', 'task_id' => $this->db->lastInsertId()];
        }
        return ['status' => 'error', 'message' => 'Failed to create task.'];
    }

    /**
     * Updates the status of a task, verifying user ownership.
     * @param int $task_id
     * @param string $status
     * @param int $user_id
     * @return array
     */
    public function updateTaskStatus($task_id, $status, $user_id) {
        // First, verify the task belongs to the user
        $task = $this->db->fetch("SELECT user_id FROM user_tasks WHERE id = :id", [':id' => $task_id]);
        if (!$task || $task['user_id'] != $user_id) {
            return ['status' => 'error', 'message' => 'Access denied or task not found.'];
        }

        $completed_date_sql = ($status === 'completed') ? ", completed_date = NOW()" : "";
        $sql = "UPDATE user_tasks SET status = :status $completed_date_sql WHERE id = :id";
        
        if ($this->db->execute($sql, [':status' => $status, ':id' => $task_id])) {
            return ['status' => 'success', 'message' => 'Task status updated.'];
        }
        return ['status' => 'error', 'message' => 'Failed to update task status.'];
    }

    /**
     * Uploads a file for a specific task.
     * @param int $task_id
     * @param int $user_id
     * @param array $file_data
     * @return array
     */
    public function uploadTaskFile($task_id, $user_id, $file_data) {
        $sql = "INSERT INTO task_uploads (task_id, user_id, file_name, file_type, file_size, file_data)
                VALUES (:task_id, :user_id, :file_name, :file_type, :file_size, :file_data)";
        
        $params = [
            ':task_id' => $task_id,
            ':user_id' => $user_id,
            ':file_name' => $file_data['name'],
            ':file_type' => $file_data['type'],
            ':file_size' => $file_data['size'],
            ':file_data' => $file_data['data']
        ];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'File uploaded successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to upload file.'];
    }

    /**
     * Retrieves all file uploads for a given task.
     * @param int $task_id
     * @return array
     */
    public function getTaskUploads($task_id) {
        $sql = "SELECT id, file_name, file_type, file_size, uploaded_at FROM task_uploads WHERE task_id = :task_id ORDER BY uploaded_at DESC";
        $uploads = $this->db->fetchAll($sql, [':task_id' => $task_id]);
        return ['status' => 'success', 'uploads' => $uploads];
    }

    /**
     * Retrieves a file's data for download.
     * @param int $upload_id
     * @param int $user_id
     * @return array
     */
    public function downloadTaskFile($upload_id, $user_id) {
        $sql = "SELECT file_name, file_type, file_data, user_id FROM task_uploads WHERE id = :id";
        $file = $this->db->fetch($sql, [':id' => $upload_id]);

        if ($file) {
            // Security Check: Verify the file belongs to the user requesting it.
            if ($file['user_id'] != $user_id) {
                http_response_code(403);
                return ['status' => 'error', 'message' => 'Access denied to this file.'];
            }
            unset($file['user_id']); // Don't need to return this
            return ['status' => 'success', 'file' => $file];
        }

        http_response_code(404);
        return ['status' => 'error', 'message' => 'File not found.'];
    }

    /**
     * Deletes a file upload, verifying ownership.
     * @param int $upload_id
     * @param int $user_id
     * @return array
     */
    public function deleteTaskUpload($upload_id, $user_id) {
        // Verify the upload belongs to the user
        $upload = $this->db->fetch("SELECT user_id, task_id FROM task_uploads WHERE id = :id", [':id' => $upload_id]);
        if (!$upload || $upload['user_id'] != $user_id) {
            http_response_code(403);
            return ['status' => 'error', 'message' => 'Access denied or file not found.'];
        }

        $this->db->execute("DELETE FROM task_uploads WHERE id = :id", [':id' => $upload_id]);
        if ($this->db->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'File deleted successfully.', 'task_id' => $upload['task_id']];
        }
        return ['status' => 'error', 'message' => 'Could not delete file.'];
    }

    /**
     * Retrieves overdue tasks for a user.
     * @param int $user_id
     * @return array
     */
    public function getOverdueTasks($user_id) {
        $sql = "SELECT * FROM user_tasks WHERE user_id = :user_id AND status = 'pending' AND due_date < CURDATE() ORDER BY due_date ASC";
        $tasks = $this->db->fetchAll($sql, [':user_id' => $user_id]);
        return ['status' => 'success', 'tasks' => $tasks];
    }

    /**
     * Retrieves tasks due within a specified number of days.
     * @param int $days
     * @param int $user_id
     * @return array
     */
    public function getTasksDueSoon($days, $user_id) {
        $sql = "SELECT * FROM user_tasks WHERE user_id = :user_id AND status = 'pending' AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY) ORDER BY due_date ASC";
        $tasks = $this->db->fetchAll($sql, [':user_id' => $user_id, ':days' => (int)$days]);
        return ['status' => 'success', 'tasks' => $tasks];
    }

    /**
     * Retrieves task statistics for a user dashboard.
     * @param int $user_id
     * @return array
     */
    public function getTaskStatistics($user_id) {
        $pending_count = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks WHERE user_id = :user_id AND status = 'pending'", [':user_id' => $user_id])['count'];
        $completed_this_week = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks WHERE user_id = :user_id AND status = 'completed' AND completed_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [':user_id' => $user_id])['count'];
        $overdue_count = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks WHERE user_id = :user_id AND status = 'pending' AND due_date < CURDATE()", [':user_id' => $user_id])['count'];
        $total_tasks = $this->db->fetch("SELECT COUNT(*) as count FROM user_tasks WHERE user_id = :user_id", [':user_id' => $user_id])['count'];

        $stats = [
            'pending_count' => $pending_count,
            'completed_this_week' => $completed_this_week,
            'overdue_count' => $overdue_count,
            'total_tasks' => $total_tasks
        ];
        return ['status' => 'success', 'stats' => $stats];
    }
}
?>