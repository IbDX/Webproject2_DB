<?php
/**
 * User Model - User registration, authentication, and profile management
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/SecurityHelper.php';
require_once __DIR__ . '/../utils/Validator.php';

class User {
    
    /**
     * Create New User (Registration)
     * 
     * @param array $data User data
     * @return array Result
     */
    public static function create($data) {
        // Validate input
        Validator::clearErrors();
        Validator::required($data['email'] ?? '', 'Email');
        Validator::email($data['email'] ?? '', 'Email');
        Validator::required($data['password'] ?? '', 'Password');
        Validator::required($data['password_confirm'] ?? '', 'Password Confirmation');
        Validator::minLength($data['password'] ?? '', 12, 'Password');
        Validator::required($data['first_name'] ?? '', 'First Name');
        Validator::required($data['last_name'] ?? '', 'Last Name');
        Validator::required($data['date_of_birth'] ?? '', 'Date of Birth');
        
        if (!Validator::match($data['password'] ?? '', $data['password_confirm'] ?? '', 'Passwords')) {
            Validator::clearErrors();
            Validator::match($data['password'] ?? '', $data['password_confirm'] ?? '', 'Passwords');
        }
        
        $passwordStrength = SecurityHelper::validatePasswordStrength($data['password'] ?? '');
        if (!$passwordStrength['valid']) {
            foreach ($passwordStrength['errors'] as $error) {
                Validator::$errors['password'][] = $error;
            }
        }
        
        if (Validator::hasErrors()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Validator::getErrors()
            ];
        }
        
        // Check if email already exists
        $existing = fetchOne("SELECT user_id FROM users WHERE email = ?", [$data['email']]);
        if ($existing) {
            return [
                'success' => false,
                'message' => 'Email already registered',
                'code' => 'email_exists'
            ];
        }
        
        try {
            beginTransaction();
            
            // Hash password
            $passwordHash = SecurityHelper::hashPassword($data['password']);
            
            // Create user
            $query = "
                INSERT INTO users 
                (email, password_hash, first_name, last_name, phone_number, date_of_birth, address, city, state, zip_code, country)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $userId = insert($query, [
                $data['email'],
                $passwordHash,
                $data['first_name'],
                $data['last_name'],
                $data['phone_number'] ?? null,
                $data['date_of_birth'],
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['zip_code'] ?? null,
                $data['country'] ?? null
            ]);
            
            // Create default savings account
            Account::createDefault($userId);
            
            commitTransaction();
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log($userId, 'USER_CREATED', 'user', $userId, [], $data, 'success');
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            rollbackTransaction();
            error_log("User creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'code' => 'creation_failed'
            ];
        }
    }
    
    /**
     * Get User by ID
     * 
     * @param int $userId User ID
     * @return array|null User data
     */
    public static function getById($userId) {
        $query = "SELECT * FROM users WHERE user_id = ?";
        return fetchOne($query, [$userId]);
    }
    
    /**
     * Get User by Email
     * 
     * @param string $email Email address
     * @return array|null User data
     */
    public static function getByEmail($email) {
        $query = "SELECT * FROM users WHERE email = ?";
        return fetchOne($query, [$email]);
    }
    
    /**
     * Update User Profile
     * 
     * @param int $userId User ID
     * @param array $data Updated data
     * @return array Result
     */
    public static function update($userId, $data) {
        // Validate
        if (!empty($data['first_name'])) {
            Validator::required($data['first_name'], 'First Name');
        }
        if (!empty($data['phone_number'])) {
            Validator::phone($data['phone_number'], 'Phone Number');
        }
        
        if (Validator::hasErrors()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Validator::getErrors()
            ];
        }
        
        try {
            $user = self::getById($userId);
            $oldValues = [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'phone_number' => $user['phone_number'],
                'address' => $user['address'],
                'city' => $user['city']
            ];
            
            $query = "
                UPDATE users SET 
                first_name = COALESCE(?, first_name),
                last_name = COALESCE(?, last_name),
                phone_number = COALESCE(?, phone_number),
                address = COALESCE(?, address),
                city = COALESCE(?, city),
                state = COALESCE(?, state),
                zip_code = COALESCE(?, zip_code),
                country = COALESCE(?, country),
                updated_at = NOW()
                WHERE user_id = ?
            ";
            
            execute($query, [
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $data['phone_number'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['state'] ?? null,
                $data['zip_code'] ?? null,
                $data['country'] ?? null,
                $userId
            ]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log($userId, 'PROFILE_UPDATED', 'user', $userId, $oldValues, $data, 'success');
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("User update failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Update failed. Please try again.'
            ];
        }
    }
    
    /**
     * Change Password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return array Result
     */
    public static function changePassword($userId, $currentPassword, $newPassword) {
        // Validate
        Validator::clearErrors();
        Validator::required($currentPassword, 'Current Password');
        Validator::required($newPassword, 'New Password');
        Validator::minLength($newPassword, 12, 'New Password');
        
        $passwordStrength = SecurityHelper::validatePasswordStrength($newPassword);
        if (!$passwordStrength['valid']) {
            foreach ($passwordStrength['errors'] as $error) {
                Validator::$errors['password'][] = $error;
            }
        }
        
        if (Validator::hasErrors()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Validator::getErrors()
            ];
        }
        
        try {
            $user = self::getById($userId);
            
            // Verify current password
            if (!SecurityHelper::verifyPassword($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Update password
            $newHash = SecurityHelper::hashPassword($newPassword);
            execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?", [$newHash, $userId]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logPasswordChange($userId);
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Password change failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password change failed. Please try again.'
            ];
        }
    }
    
    /**
     * Deactivate Account
     * 
     * @param int $userId User ID
     * @return array Result
     */
    public static function deactivate($userId) {
        try {
            execute("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE user_id = ?", [$userId]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log($userId, 'ACCOUNT_DEACTIVATED', 'user', $userId, [], [], 'success');
            
            return [
                'success' => true,
                'message' => 'Account deactivated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Account deactivation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Deactivation failed'
            ];
        }
    }
}
?>
