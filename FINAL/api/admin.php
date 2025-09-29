<?php
/**
 * Admin API Endpoints
 * KSG SMI Performance System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../classes/Admin.php';
require_once '../classes/User.php';
require_once '../classes/Task.php';

/**
 * Checks if an admin is authenticated.
 * Exits with an error message if not.
 */
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id']) || $_SESSION['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden: Administrator access required.']);
        exit;
    }

    // Check for session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    // Refresh login time on activity
    $_SESSION['login_time'] = time();
}

/**
 * Logs an admin's activity.
 * @param string $action The action performed by the admin.
 */
function logAdminActivity($action) {
    try {
        $database = new Database();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $query = "CALL LogUserActivity(NULL, :admin_id, 'admin', :action, :ip_address, :user_agent)";
        $params = [
            ':admin_id' => $_SESSION['admin_id'],
            ':action' => $action,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ];

        $database->execute($query, $params);
    } catch (Exception $e) {
        // Log to a file, as we don't want to interrupt the user's action
        error_log("Failed to log admin activity: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    checkAdminAuth();
    $admin = new Admin();
    $user = new User();
    $task = new Task();

    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'get_analytics':
                    $result = $admin->getSystemAnalytics();
                    echo json_encode($result);
                    break;

                case 'get_users':
                    $result = $admin->getAllUsers();
                    echo json_encode($result);
                    break;

                case 'get_user_stats':
                    $result = $admin->getUserStats();
                    echo json_encode($result);
                    break;

                case 'get_recent_assignments':
                    $limit = $_GET['limit'] ?? 10;
                    $result = $admin->getRecentAssignments((int)$limit);
                    echo json_encode($result);
                    break;

                case 'get_access_logs':
                    $limit = $_GET['limit'] ?? 15;
                    $result = $admin->getAccessLogs((int)$limit);
                    echo json_encode($result);
                    break;

                case 'get_backup_history':
                    $result = $admin->getBackupHistory();
                    echo json_encode($result);
                    break;

                case 'download_backup':
                    $backup_id = $_GET['id'] ?? null;
                    if (!$backup_id) {
                        throw new Exception("Backup ID is required.");
                    }
                    $admin->downloadBackup($backup_id); // This function will handle headers and exit
                    break;

                case 'get_user_details':
                    $user_id = $_GET['user_id'] ?? null;
                    if (!$user_id) throw new Exception("User ID is required.");
                    $result = $user->getUserById($user_id);
                    echo json_encode($result);
                    break;

                case 'get_user_tasks':
                    $user_id = $_GET['user_id'] ?? null;
                    if (!$user_id) throw new Exception("User ID is required.");
                    $result = $task->getTasksByUserId($user_id);
                    echo json_encode($result);
                    break;

                case 'get_task':
                    $task_id = $_GET['task_id'] ?? null;
                    if (!$task_id) throw new Exception("Task ID is required.");
                    $result = $task->getTaskById($task_id);
                    echo json_encode($result);
                    break;

                case 'get_task_uploads':
                    $task_id = $_GET['task_id'] ?? null;
                    if (!$task_id) throw new Exception("Task ID is required.");
                    $result = $task->getTaskUploads($task_id);
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid GET action specified.']);
                    break;
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);

            switch ($action) {
                case 'assign_predefined_task':
                    $result = $admin->assignPredefinedTask($input);
                    if ($result['status'] === 'success') {
                        logAdminActivity("Assigned task '{$input['title']}' to user ID {$input['user_id']}");
                    }
                    echo json_encode($result);
                    break;

                case 'create_backup':
                    $result = $admin->createBackup($_SESSION['admin_id']);
                    if ($result['status'] === 'success') {
                        logAdminActivity("Created system backup: {$result['backup_name']}");
                    }
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid POST action specified.']);
                    break;
            }
            break;

        case 'DELETE':
             switch ($action) {
                case 'delete_task':
                    $task_id = $_GET['task_id'] ?? null;
                    if (!$task_id) throw new Exception("Task ID is required for deletion.");
                    $result = $admin->deleteTask($task_id);
                     if ($result['status'] === 'success') {
                        logAdminActivity("Deleted task ID: {$task_id}");
                    }
                    echo json_encode($result);
                    break;

                case 'delete_task_upload':
                    $upload_id = $_GET['upload_id'] ?? null;
                    if (!$upload_id) throw new Exception("Upload ID is required for deletion.");
                    $result = $admin->deleteTaskUpload($upload_id);
                     if ($result['status'] === 'success') {
                        logAdminActivity("Deleted task upload ID: {$upload_id}");
                    }
                    echo json_encode($result);
                    break;

                default:
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid DELETE action specified.']);
                    break;
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Admin API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred. Please try again later.']);
}
?>