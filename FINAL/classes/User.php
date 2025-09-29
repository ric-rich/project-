<?php
/**
 * User Class
 * Handles all user-related logic, including authentication, profile management, and data retrieval.
 * KSG SMI Performance System
 */

class User {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Logs in a user.
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public function login($email, $password) {
        $user = $this->db->fetch("SELECT * FROM users WHERE email = :email AND status = 'active'", [':email' => $email]);

        if ($user && password_verify($password, $user['password'])) {
            // Update last login timestamp
            $this->db->execute("UPDATE users SET last_login = NOW() WHERE id = :id", [':id' => $user['id']]);
            unset($user['password']); // Do not send password hash to client
            return $user;
        }
        return false;
    }

    /**
     * Registers a new user.
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $confirm_password
     * @return array
     */
    public function register($name, $email, $password, $confirm_password) {
        if (empty($name) || empty($email) || empty($password)) {
            return ['status' => 'error', 'message' => 'All fields are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'message' => 'Invalid email format.'];
        }
        if ($password !== $confirm_password) {
            return ['status' => 'error', 'message' => 'Passwords do not match.'];
        }
        if (strlen($password) < 8) {
            return ['status' => 'error', 'message' => 'Password must be at least 8 characters long.'];
        }

        $existing = $this->db->fetch("SELECT id FROM users WHERE email = :email", [':email' => $email]);
        if ($existing) {
            return ['status' => 'error', 'message' => 'An account with this email already exists.'];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
        $params = [':name' => $name, ':email' => $email, ':password' => $hashed_password];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'User registered successfully.'];
        }
        return ['status' => 'error', 'message' => 'Registration failed due to a server error.'];
    }

    /**
     * Retrieves a user's data by their ID.
     * @param int $user_id
     * @return array
     */
    public function getUserById($user_id) {
        $user = $this->db->fetch("SELECT id, name, email, phone, department, job_title, profile_picture, notification_preferences, settings, status, created_at, last_login FROM users WHERE id = :id", [':id' => $user_id]);
        if ($user) {
            // Handle BLOB data for profile picture
            if ($user['profile_picture']) {
                $user['profile_picture'] = 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']);
            }
            return ['status' => 'success', 'user' => $user];
        }
        return ['status' => 'error', 'message' => 'User not found.'];
    }

    /**
     * Updates a user's profile information.
     * @param int $user_id
     * @param array $data
     * @return array
     */
    public function updateProfile($user_id, $data) {
        $allowed_fields = ['name', 'phone', 'department', 'job_title', 'notification_preferences', 'settings'];
        $update_fields = [];
        $params = [':id' => $user_id];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $update_fields[] = "`$key` = :$key";
                // JSON encode if it's a JSON field
                if ($key === 'notification_preferences' || $key === 'settings') {
                    $params[":$key"] = json_encode($value);
                } else {
                    $params[":$key"] = $value;
                }
            }
        }

        if (empty($update_fields)) {
            return ['status' => 'error', 'message' => 'No valid fields to update.'];
        }

        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :id";
        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'Profile updated successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to update profile.'];
    }

    /**
     * Changes a user's password.
     * @param int $user_id
     * @param array $data
     * @return array
     */
    public function changePassword($user_id, $data) {
        $current_password = $data['current_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        $confirm_password = $data['confirm_password'] ?? '';

        $user = $this->db->fetch("SELECT password FROM users WHERE id = :id", [':id' => $user_id]);

        if (!$user || !password_verify($current_password, $user['password'])) {
            return ['status' => 'error', 'message' => 'Current password is incorrect.'];
        }
        if ($new_password !== $confirm_password) {
            return ['status' => 'error', 'message' => 'New passwords do not match.'];
        }
        if (strlen($new_password) < 8) {
            return ['status' => 'error', 'message' => 'New password must be at least 8 characters long.'];
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password = :password WHERE id = :id";

        if ($this->db->execute($sql, [':password' => $hashed_password, ':id' => $user_id])) {
            return ['status' => 'success', 'message' => 'Password changed successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to change password.'];
    }

    /**
     * Updates a user's profile picture.
     * @param int $user_id
     * @param string $image_data
     * @return array
     */
    public function updateProfilePicture($user_id, $image_data) {
        $sql = "UPDATE users SET profile_picture = :picture WHERE id = :id";
        $params = [
            ':picture' => $image_data,
            ':id' => $user_id
        ];

        if ($this->db->execute($sql, $params)) {
            return ['status' => 'success', 'message' => 'Profile picture updated successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to update profile picture.'];
    }
    /**
     * Generates a password reset token for a user.
     * In a real application, this would also trigger an email.
     * @param string $email
     * @param string $user_type
     * @return array
     */
    public function requestPasswordReset($email, $user_type = 'user') {
        $table = ($user_type === 'admin') ? 'admins' : 'users';
        $user = $this->db->fetch("SELECT id FROM {$table} WHERE email = :email", [':email' => $email]);

        if (!$user) {
            // To prevent email enumeration, we return a success message even if the user doesn't exist.
            return ['status' => 'success', 'message' => 'If an account with that email exists, a reset link has been sent.'];
        }

        // Invalidate old tokens for this email
        $this->db->execute("DELETE FROM password_resets WHERE email = :email AND user_type = :user_type", [':email' => $email, ':user_type' => $user_type]);

        // Generate a secure token
        $token = bin2hex(random_bytes(32));

        $sql = "INSERT INTO password_resets (email, token, user_type) VALUES (:email, :token, :user_type)";
        if ($this->db->execute($sql, [':email' => $email, ':token' => $token, ':user_type' => $user_type])) {
            // In a real app, you would send an email with a link like:
            // $reset_link = "http://your-domain/FINAL/INDEX.HTML#reset-password?token=" . $token;
            // For this demo, we will return the token in the response for easy testing.
            return ['status' => 'success', 'message' => 'Password reset link sent.', 'token' => $token];
        }

        return ['status' => 'error', 'message' => 'Could not generate reset token.'];
    }

    /**
     * Resets a user's password using a valid token.
     * @param string $token
     * @param string $new_password
     * @param string $confirm_password
     * @return array
     */
    public function resetPassword($token, $new_password, $confirm_password) {
        if (empty($token) || empty($new_password) || empty($confirm_password)) {
            return ['status' => 'error', 'message' => 'All fields are required.'];
        }
        if ($new_password !== $confirm_password) {
            return ['status' => 'error', 'message' => 'Passwords do not match.'];
        }

        // Find token, ensuring it's not older than 1 hour
        $reset_request = $this->db->fetch("SELECT * FROM password_resets WHERE token = :token AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)", [':token' => $token]);

        if (!$reset_request) {
            return ['status' => 'error', 'message' => 'Invalid or expired password reset token.'];
        }

        $table = ($reset_request['user_type'] === 'admin') ? 'admins' : 'users';
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $this->db->execute("UPDATE {$table} SET password = :password WHERE email = :email", [':password' => $hashed_password, ':email' => $reset_request['email']]);
        $this->db->execute("DELETE FROM password_resets WHERE token = :token", [':token' => $token]);

        return ['status' => 'success', 'message' => 'Password has been reset successfully. You can now log in.'];
    }

    /**
     * Retrieves dashboard statistics for a user.
     * This is an alias for Task::getTaskStatistics for convenience.
     * @param int $user_id
     * @return array
     */
    public function getDashboardStats($user_id) {
        $task = new Task();
        return $task->getTaskStatistics($user_id);
    }
}
?>