<?php
/**
 * Profile Controller - User profile operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class ProfileController {
    
    /**
     * Get User Profile
     */
    public static function getProfile() {
        $user = AuthMiddleware::getCurrentUser();
        
        if (!$user) {
            Response::unauthorized();
        }
        
        Response::success($user, 'Profile retrieved successfully');
    }
    
    /**
     * Update Profile
     */
    public static function updateProfile() {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        if (!empty($input['email'])) {
            // Email update not allowed (security)
            Response::error('Email cannot be changed', 400);
        }
        
        // Update profile
        $result = User::update($userId, $input);
        
        if (!$result['success']) {
            Response::error(
                $result['message'],
                isset($result['errors']) ? 422 : 400,
                $result['errors'] ?? []
            );
        }
        
        Response::success(null, 'Profile updated successfully');
    }
    
    /**
     * Change Password
     */
    public static function changePassword() {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        Validator::clearErrors();
        Validator::required($input['current_password'] ?? '', 'Current Password');
        Validator::required($input['new_password'] ?? '', 'New Password');
        Validator::required($input['confirm_password'] ?? '', 'Confirm Password');
        
        if (Validator::hasErrors()) {
            Response::validationError(Validator::getErrors());
        }
        
        // Verify passwords match
        if ($input['new_password'] !== $input['confirm_password']) {
            Response::error('New passwords do not match', 400);
        }
        
        // Change password
        $result = User::changePassword(
            $userId,
            $input['current_password'],
            $input['new_password']
        );
        
        if (!$result['success']) {
            Response::error(
                $result['message'],
                isset($result['errors']) ? 422 : 400,
                $result['errors'] ?? []
            );
        }
        
        Response::success(null, 'Password changed successfully');
    }
    
    /**
     * Deactivate Account
     */
    public static function deactivateAccount() {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Require password confirmation
        if (empty($input['password'])) {
            Response::error('Password confirmation required', 400);
        }
        
        // Verify password
        $user = User::getById($userId);
        require_once __DIR__ . '/../utils/SecurityHelper.php';
        
        if (!SecurityHelper::verifyPassword($input['password'], $user['password_hash'])) {
            Response::error('Invalid password', 401);
        }
        
        // Deactivate account
        $result = User::deactivate($userId);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        // Logout user
        AuthMiddleware::destroySession();
        
        Response::success(null, 'Account deactivated successfully');
    }
}
?>
