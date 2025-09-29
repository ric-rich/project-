<?php
/**
 * User API Endpoints
 * KSG SMI Performance System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Task.php';

/**
 * Checks if a user is authenticated and their session is valid.
 * Throws an exception if authentication fails.
 * @throws Exception
 */
function checkUserAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
        http_response_code(403);
        throw new Exception('Forbidden: User access required.');
    }
    
    // Check session timeout
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
        session_destroy();
        http_response_code(401);
        throw new Exception('Session expired. Please log in again.');
    }
    // Refresh login time on activity
    $_SESSION['login_time'] = time();
}

// Helper function to log user activity
function logUserActivity($action) {
    try {
        $database = new Database();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $query = "CALL LogUserActivity(:user_id, NULL, 'user', :action, :ip_address, :user_agent)";
        $params = [
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ];
        
        $database->execute($query, $params);
    } catch (Exception $e) {
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    checkUserAuth();
    $user_id = $_SESSION['user_id'];
    $user = new User();
    $task = new Task();
    
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'get_profile':
                    $result = $user->getUserById($user_id);
                    echo json_encode($result);
                    break;
                
                case 'get_dashboard_stats':
                    $result = $user->getDashboardStats($user_id);
                    echo json_encode($result);
                    break;
                
                case 'get_tasks':
                    $status = $_GET['status'] ?? '';
                    $priority = $_GET['priority'] ?? '';
                    
                    $result = $task->getTasksByUserId($user_id, $status, $priority);
                    echo json_encode($result);
                    break;
                
                case 'get_task':
                    $task_id = $_GET['task_id'] ?? '';
                    if (empty($task_id)) throw new Exception('Task ID is required.');
                    
                    $task_result = $task->getTaskById($task_id, $user_id); // Pass user_id for ownership check
                    echo json_encode($task_result);
                    break;
                
                case 'get_task_uploads':
                    $task_id = $_GET['task_id'] ?? '';
                    if (empty($task_id)) throw new Exception('Task ID is required.');
                    
                    // Verify ownership before getting uploads
                    $task->getTaskById($task_id, $user_id);
                    
                    $result = $task->getTaskUploads($task_id);
                    echo json_encode($result);
                    break;
                
                case 'download_task_file':
                    $upload_id = $_GET['upload_id'] ?? '';
                    if (empty($upload_id)) throw new Exception('Upload ID is required.');
                    
                    $file_result = $task->downloadTaskFile($upload_id, $user_id); // Ownership check is now inside
                    
                    $file = $file_result['file'];
                    logUserActivity('Downloaded file: ' . $file['file_name']);
                    
                    header('Content-Type: ' . $file['file_type']);
                    header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
                    header('Content-Length: ' . strlen($file['file_data']));
                    
                    echo $file['file_data'];
                    exit; // Stop script execution after file download
                    break;
                
                case 'get_overdue_tasks':
                    $result = $task->getOverdueTasks($user_id);
                    echo json_encode($result);
                    break;
                
                case 'get_tasks_due_soon':
                    $days = $_GET['days'] ?? 3;
                    $result = $task->getTasksDueSoon($days, $user_id);
                    echo json_encode($result);
                    break;
                
                case 'get_task_statistics':
                    $result = $task->getTaskStatistics($user_id);
                    echo json_encode($result);
                    break;
                
                case 'export_user_report':
                    $report_type = $_GET['type'] ?? 'all';
                    
                    $tasks_result = $task->getTasksByUserId($user_id);
                    
                    if ($tasks_result['status'] !== 'success') {
                        echo json_encode($tasks_result);
                        break;
                    }
                    
                    $tasks = $tasks_result['tasks'];
                    $csv_data = [];
                    
                    if ($report_type === 'weekly' || $report_type === 'all') {
                        $csv_data[] = 'Weekly Performance Report';
                        $csv_data[] = 'Generated: ' . date('Y-m-d H:i:s');
                        $csv_data[] = '';
                        $csv_data[] = 'Task,Status,Priority,Due Date,Completed Date';
                        
                        $week_ago = date('Y-m-d', strtotime('-7 days'));
                        foreach ($tasks as $task) {
                            if (date('Y-m-d', strtotime($task['created_date'])) >= $week_ago || 
                                ($task['completed_date'] && date('Y-m-d', strtotime($task['completed_date'])) >= $week_ago)) {
                                $csv_data[] = '"' . $task['title'] . '","' . $task['status'] . '","' . 
                                            $task['priority'] . '","' . date('Y-m-d', strtotime($task['due_date'])) . '","' . 
                                            ($task['completed_date'] ? date('Y-m-d', strtotime($task['completed_date'])) : '') . '"';
                            }
                        }
                        $csv_data[] = '';
                    }
                    
                    if ($report_type === 'time' || $report_type === 'all') {
                        $csv_data[] = 'Time Tracking Report';
                        $csv_data[] = 'Task,Hours Estimated';
                        
                        foreach ($tasks as $task) {
                            if ($task['status'] === 'completed') {
                                $csv_data[] = '"' . $task['title'] . '",2'; // Estimated 2 hours per task
                            }
                        }
                        $csv_data[] = '';
                    }
                    
                    if ($report_type === 'projects' || $report_type === 'all') {
                        $csv_data[] = 'Project Status Report';
                        $csv_data[] = 'Task,Status,Priority,Due Date';
                        
                        foreach ($tasks as $task) {
                            $csv_data[] = '"' . $task['title'] . '","' . $task['status'] . '","' . 
                                        $task['priority'] . '","' . date('Y-m-d', strtotime($task['due_date'])) . '"';
                        }
                    }
                    
                    $csv_content = implode("\n", $csv_data);
                    
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="user_report_' . $report_type . '_' . date('Y-m-d') . '.csv"');
                    header('Content-Length: ' . strlen($csv_content));
                    
                    echo $csv_content;
                    logUserActivity('Exported ' . $report_type . ' report');
                    exit;
                    break;
                
                default:
                    throw new Exception('Invalid GET action specified.');
                    break;
            }
            break;
        
        case 'POST':
            switch ($action) {
                case 'upload_task_file':
                    $task_id = $_POST['task_id'] ?? '';
                    if (empty($task_id)) throw new Exception('Task ID is required.');
                    
                    // Verify task belongs to current user before proceeding
                    $task_result = $task->getTaskById($task_id, $user_id);
                    if ($task_result['status'] !== 'success') throw new Exception('Access denied or task not found.');
                    
                    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                        echo json_encode(array('status' => 'error', 'message' => 'File upload failed'));
                        break;
                    }
                    
                    $file = $_FILES['file'];
                    
                    // Validate file size
                    if ($file['size'] > MAX_FILE_SIZE) throw new Exception('File size exceeds the ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB limit.');
                    
                    // Validate file type
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, ALLOWED_FILE_TYPES)) throw new Exception('File type not allowed.');
                    
                    // Read file data
                    $file_data = [
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'size' => $file['size'],
                        'data' => file_get_contents($file['tmp_name'])
                    ];
                    
                    $result = $task->uploadTaskFile($task_id, $user_id, $file_data);
                    
                    if ($result['status'] === 'success') {
                        logUserActivity('Uploaded file: ' . $file['name'] . ' for task ID: ' . $task_id);
                    }
                    
                    echo json_encode($result);
                    break;
                
                case 'upload_profile_picture':
                    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('Profile picture upload failed or no file selected.');
                    }

                    $file = $_FILES['profile_picture'];

                    // Validate file size (e.g., 2MB limit for profile pictures)
                    if ($file['size'] > 2 * 1024 * 1024) {
                        throw new Exception('File size exceeds the 2MB limit for profile pictures.');
                    }

                    // Validate file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($file['type'], $allowed_types)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    }

                    $image_data = file_get_contents($file['tmp_name']);
                    $result = $user->updateProfilePicture($user_id, $image_data);

                    logUserActivity('Updated profile picture');
                    echo json_encode($result);
                    break;

                default:
                    throw new Exception('Invalid POST action specified.');
                    break;
            }
            break;
        
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            switch ($action) {
                case 'update_profile':
                    if (empty($input)) throw new Exception('Profile data is required.');
                    
                    $result = $user->updateProfile($user_id, $input);
                    
                    if ($result['status'] === 'success') {
                        logUserActivity('Updated profile');
                    }
                    
                    echo json_encode($result);
                    break;
                
                case 'update_task_status':
                    $task_id = $_GET['task_id'] ?? '';
                    $status = $input['status'] ?? '';
                    if (empty($task_id) || empty($status)) throw new Exception('Task ID and status are required.');
                    
                    $result = $task->updateTaskStatus($task_id, $status, $user_id);
                    
                    if ($result['status'] === 'success') {
                        logUserActivity('Updated task status for task ID: ' . $task_id . ' to ' . $status);
                    }
                    
                    echo json_encode($result);
                    break;
                
                case 'save_settings':
                    if (empty($input)) throw new Exception('Settings data is required.');
                    
                    $result = $user->updateProfile($user_id, ['settings' => $input]);
                    
                    if ($result['status'] === 'success') {
                        logUserActivity('Updated user settings');
                    }
                    
                    echo json_encode($result);
                    break;
                
                case 'save_notification_preferences':
                    if (empty($input)) throw new Exception('Notification preferences are required.');
                    
                    $result = $user->updateProfile($user_id, ['notification_preferences' => $input]);
                    
                    if ($result['status'] === 'success') {
                        logUserActivity('Updated notification preferences');
                    }
                    
                    echo json_encode($result);
                    break;
                
                default:
                    throw new Exception('Invalid PUT action specified.');
                    break;
            }
            break;
        
        case 'DELETE':
            switch ($action) {
                case 'delete_task_upload':
                    $upload_id = $_GET['upload_id'] ?? '';
                    if (empty($upload_id)) throw new Exception('Upload ID is required.');
                    
                    $result = $task->deleteTaskUpload($upload_id, $user_id); // Ownership check is inside
                    
                    if ($result['status'] === 'success') {
                        $task_id = $result['task_id'] ?? 'unknown';
                        logUserActivity('Deleted file upload ID: ' . $upload_id . ' from task ID: ' . $task_id);
                    }
                    
                    echo json_encode($result);
                    break;
                
                default:
                    throw new Exception('Invalid DELETE action specified.');
                    break;
            }
            break;
        
        default:
            throw new Exception('Method not allowed.');
            break;
    }
} catch (Exception $e) {
    // Centralized error handling
    if (http_response_code() < 400) {
        http_response_code(500); // Default to 500 if not already set
    }
    error_log("User API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>