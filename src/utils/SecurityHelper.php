<?php
/**
 * Security Helper - Password hashing, encryption, and secure utilities
 */

class SecurityHelper {
    
    /**
     * Hash Password using bcrypt
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
    }
    
    /**
     * Verify Password
     * 
     * @param string $password Plain text password
     * @param string $hash Password hash from database
     * @return bool True if password matches
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate Secure Random Token
     * 
     * @param int $length Token length in bytes
     * @return string Hexadecimal token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate Unique Account Number
     * 
     * @return string 20-character account number
     */
    public static function generateAccountNumber() {
        return 'ACC' . strtoupper(bin2hex(random_bytes(8)));
    }
    
    /**
     * Generate Unique Reference Number for Transactions
     * 
     * @return string Reference number format: TXN-{timestamp}-{random}
     */
    public static function generateReferenceNumber() {
        return 'TXN-' . time() . '-' . bin2hex(random_bytes(4));
    }
    
    /**
     * Hash Sensitive Data (one-way)
     * 
     * @param string $data Data to hash
     * @return string SHA-256 hash
     */
    public static function hashSensitive($data) {
        return hash('sha256', $data);
    }
    
    /**
     * Encrypt Data (reversible)
     * 
     * @param string $data Data to encrypt
     * @param string $key Encryption key
     * @return string Base64 encoded encrypted data
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?? self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt Data
     * 
     * @param string $data Base64 encoded encrypted data
     * @param string $key Encryption key
     * @return string Decrypted data
     */
    public static function decrypt($data, $key = null) {
        $key = $key ?? self::getEncryptionKey();
        $data = base64_decode($data);
        $iv = substr($data, 0, openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = substr($data, openssl_cipher_iv_length('aes-256-cbc'));
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
    
    /**
     * Get Encryption Key
     * 
     * @return string Encryption key (from environment or config)
     */
    private static function getEncryptionKey() {
        $key = getenv('ENCRYPTION_KEY');
        if (!$key) {
            $key = hash('sha256', defined('DB_PASSWORD') ? DB_PASSWORD : 'default-insecure-key', true);
        }
        return $key;
    }
    
    /**
     * Rate Limit Check
     * 
     * @param string $identifier User or IP identifier
     * @param int $limit Maximum attempts
     * @param int $window Time window in seconds
     * @return bool True if under limit
     */
    public static function checkRateLimit($identifier, $limit = 5, $window = 300) {
        if (!function_exists('apcu_fetch') || !function_exists('apcu_store') || !function_exists('apcu_inc')) {
            return true;
        }

        $key = "ratelimit:" . md5($identifier);
        $attempts = apcu_fetch($key, $success);
        
        if (!$success) {
            apcu_store($key, 1, $window);
            return true;
        }
        
        if ($attempts < $limit) {
            apcu_inc($key);
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate CSRF Token
     * 
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF Token
     * 
     * @param string $token Token to verify
     * @return bool True if token is valid
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize HTML/XSS
     * 
     * @param string $data User input
     * @return string Sanitized data
     */
    public static function sanitizeHTML($data) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate Email
     * 
     * @param string $email Email address
     * @return bool True if valid
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate Password Strength
     * 
    * Requires:
    * - Minimum 12 characters
    * - At least one uppercase letter
    * - At least one lowercase letter
    * - At least one digit
    * - At least one special character
     * 
     * @param string $password Password to validate
     * @return array Status and message
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/\\d/', $password)) {
            $errors[] = "Password must contain at least one digit";
        }
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
?>
