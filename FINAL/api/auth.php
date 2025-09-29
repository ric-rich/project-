<?php
/**
 * Authentication API Endpoint (User & Admin)
 * KSG SMI Performance System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust for production if needed
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../classes/User.php';
require_once '../classes/Admin.php';

/**
 * Logs user or admin activity.
 * @param string $action The action performed.
 * @param int|null $user_id The user's ID, if applicable.
 * @param int|null $admin_id The admin's ID, if applicable.
 * @param string $user_type The type of user ('user', 'admin', 'system').
 */
function logActivity($action, $user_id = null, $admin_id = null, $user_type = 'system') {
    try {
        $database = new Database();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $query = "CALL LogUserActivity(:user_id, :admin_id, :user_type, :action, :ip_address, :user_agent)";
        $params = [
            ':user_id' => $user_id,
            ':admin_id' => $admin_id,
            ':user_type' => $user_type,
            ':action' => $action,
            ':ip_address' => $ip_address,
            ':user_agent' => $user_agent
        ];

        $database->execute($query, $params);
    } catch (Exception $e) {
        // Log to a file; don't interrupt the main process.
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    $user = new User();
    $admin = new Admin();

    switch ($action) {
        case 'user_login':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $userData = $user->login($email, $password);

            if ($userData) {
                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['user_name'] = $userData['name'];
                $_SESSION['user_type'] = 'user';
                $_SESSION['login_time'] = time();
                logActivity('User login success', $userData['id'], null, 'user');
                echo json_encode(['status' => 'success', 'message' => 'Login successful.', 'user' => $userData]);
            } else {
                logActivity('User login failed for email: ' . $email, null, null, 'system');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
            }
            break;

        case 'user_register':
            $result = $user->register(
                $input['name'] ?? '',
                $input['email'] ?? '',
                $input['password'] ?? '',
                $input['confirm_password'] ?? ''
            );
            echo json_encode($result);
            break;

        case 'admin_login':
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            $index_code = $input['index_code'] ?? '';
            $adminData = $admin->login($email, $password, $index_code);

            if ($adminData) {
                $_SESSION['admin_id'] = $adminData['id'];
                $_SESSION['admin_name'] = $adminData['name'];
                $_SESSION['user_type'] = 'admin';
                $_SESSION['login_time'] = time();
                logActivity('Admin login success', null, $adminData['id'], 'admin');
                echo json_encode(['status' => 'success', 'message' => 'Admin login successful.', 'admin' => $adminData]);
            } else {
                logActivity('Admin login failed for email: ' . $email, null, null, 'system');
                http_response_code(401);
                echo json_encode(['status' => 'error', 'message' => 'Invalid credentials or index code.']);
            }
            break;

        case 'admin_register':
            // In a real system, this might be restricted or require a special key.
            $result = $admin->register(
                $input['name'] ?? '',
                $input['email'] ?? '',
                $input['password'] ?? '',
                $input['index_code'] ?? ''
            );
            echo json_encode($result);
            break;

        case 'change_password':
            if (!isset($_SESSION['user_type'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
                break;
            }

            if ($_SESSION['user_type'] === 'user') {
                $result = $user->changePassword($_SESSION['user_id'], $input);
            } else { // Assumes 'admin'
                // The changePassword method would need to be added to the Admin class as well.
                // For now, we'll assume only users can change their password via this endpoint.
                http_response_code(501);
                $result = ['status' => 'error', 'message' => 'Admin password change not implemented here.'];
            }
            echo json_encode($result);
            break;

        case 'request_password_reset':
            $email = $input['email'] ?? '';
            $user_type = $input['user_type'] ?? 'user'; // 'user' or 'admin'
            // We use the User class for both as the logic is similar
            $result = $user->requestPasswordReset($email, $user_type);
            // For demonstration, we return the token. In production, you'd only send an email.
            echo json_encode($result);
            break;

        case 'reset_password':
            $token = $input['token'] ?? '';
            $new_password = $input['new_password'] ?? '';
            $confirm_password = $input['confirm_password'] ?? '';
            $result = $user->resetPassword($token, $new_password, $confirm_password);
            if ($result['status'] === 'success') {
                logActivity('Password reset successful');
            }
            echo json_encode($result);
            break;

        case 'check_session':
            if (isset($_SESSION['user_type']) && (time() - $_SESSION['login_time']) < SESSION_TIMEOUT) {
                $_SESSION['login_time'] = time(); // Refresh session time
                $response = ['status' => 'success', 'user_type' => $_SESSION['user_type']];
                if ($_SESSION['user_type'] === 'user') {
                    $response['user'] = $user->getUserById($_SESSION['user_id'])['user'];
                } else {
                    // Fetch admin details if needed
                    $response['admin'] = ['id' => $_SESSION['admin_id'], 'name' => $_SESSION['admin_name']];
                }
                echo json_encode($response);
            } else {
                session_destroy();
                echo json_encode(['status' => 'error', 'message' => 'No active session.']);
            }
            break;

        case 'logout':
            if (isset($_SESSION['user_type'])) {
                $user_id = $_SESSION['user_id'] ?? null;
                $admin_id = $_SESSION['admin_id'] ?? null;
                $user_type = $_SESSION['user_type'];
                logActivity(ucfirst($user_type) . ' logout', $user_id, $admin_id, $user_type);
            }
            session_destroy();
            echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Auth API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An internal server error occurred.']);
}
?>