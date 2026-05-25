<?php
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://ibdx.github.io'];
$isLocalDevOrigin = $requestOrigin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$#', $requestOrigin) === 1;

if ($requestOrigin !== '' && ($isLocalDevOrigin || in_array($requestOrigin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, ngrok-skip-browser-warning');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Authentication Middleware - Session and permission validation
 */

require_once __DIR__ . '/../utils/SecurityHelper.php';
require_once __DIR__ . '/../../config/database.php';

class AuthMiddleware {
    
    private static $currentUser = null;
    
    /**
     * Initialize Session
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', getenv('SESSION_COOKIE_SECURE') ?: 1);
            ini_set('session.cookie_samesite', getenv('SESSION_COOKIE_SAMESITE') ?: 'None');
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
            ini_set('session.use_strict_mode', 1);
            
            session_start();
        }
        
        // Validate existing session
        if (isset($_SESSION['user_id'])) {
            self::validateSession();
        }
    }
    
    /**
     * Validate Session Integrity
     * 
     * @return bool True if session is valid
     */
    public static function validateSession() {
        if (!isset($_SESSION['user_id'], $_SESSION['token'], $_SESSION['ip_address'], $_SESSION['user_agent'])) {
            self::destroySession();
            return false;
        }
        
        // Check IP address (prevent session hijacking)
        $clientIP = self::getClientIP();
        if ($_SESSION['ip_address'] !== $clientIP) {
            error_log("IP mismatch detected for user {$_SESSION['user_id']}: {$_SESSION['ip_address']} vs {$clientIP}");
            self::destroySession();
            return false;
        }
        
        // Check user agent (additional security)
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT'] ?? null) {
            error_log("User agent mismatch detected for user {$_SESSION['user_id']}");
            self::destroySession();
            return false;
        }
        
        // Check session expiration
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            self::destroySession();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Verify session in database
        $query = "SELECT * FROM sessions WHERE session_id = ? AND user_id = ? AND expires_at > NOW()";
        $session = fetchOne($query, [$_SESSION['session_id'], $_SESSION['user_id']]);
        
        if (!$session) {
            self::destroySession();
            return false;
        }
        
        return true;
    }
    
    /**
     * Authenticate User (Login)
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array Result with user data or error
     */
    public static function authenticate($email, $password) {
        // Rate limiting
        $rateLimitKey = "login_attempt:" . md5($email);
        if (!SecurityHelper::checkRateLimit($rateLimitKey, 5, 900)) { // 5 attempts per 15 minutes
            return [
                'success' => false,
                'message' => 'Too many login attempts. Please try again later.',
                'code' => 'rate_limit_exceeded'
            ];
        }
        
        // Get user from database
        $query = "SELECT * FROM users WHERE email = ? AND status = 'active'";
        $user = fetchOne($query, [$email]);
        
        if (!$user) {
            self::recordFailedLogin($email);
            return [
                'success' => false,
                'message' => 'Invalid email or password',
                'code' => 'invalid_credentials'
            ];
        }
        
        // Verify password
        if (!SecurityHelper::verifyPassword($password, $user['password_hash'])) {
            self::recordFailedLogin($email);
            return [
                'success' => false,
                'message' => 'Invalid email or password',
                'code' => 'invalid_credentials'
            ];
        }
        
        // Create session
        return self::createSession($user);
    }
    
    /**
     * Create Session for User
     * 
     * @param array $user User data
     * @return array Result with session token
     */
    private static function createSession($user) {
        self::initSession();
        
        // Generate session token
        $sessionId = SecurityHelper::generateToken();
        $token = SecurityHelper::generateToken();
        $clientIP = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Store in database
        $query = "
            INSERT INTO sessions (session_id, user_id, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
        ";
        
        $expiryHours = SESSION_LIFETIME / 3600;
        insert($query, [$sessionId, $user['user_id'], $clientIP, $userAgent, $expiryHours]);
        
        // Store in session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['token'] = $token;
        $_SESSION['ip_address'] = $clientIP;
        $_SESSION['user_agent'] = $userAgent;
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = SecurityHelper::generateCSRFToken();
        
        // Update last login timestamp
        execute("UPDATE users SET last_login = NOW() WHERE user_id = ?", [$user['user_id']]);
        
        // Log successful login
        require_once __DIR__ . '/../utils/AuditLogger.php';
        AuditLogger::logLogin($user['user_id'], $user['email'], true);
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name']
            ],
            'token' => $token,
            'session_id' => $sessionId
        ];
    }
    
    /**
     * Record Failed Login
     * 
     * @param string $email Email address
     */
    private static function recordFailedLogin($email) {
        require_once __DIR__ . '/../utils/AuditLogger.php';
        AuditLogger::logLogin(null, $email, false);
    }
    
    /**
     * Check if User is Authenticated
     * 
     * @return bool True if authenticated
     */
    public static function isAuthenticated() {
        self::initSession();
        return isset($_SESSION['user_id']) && self::validateSession();
    }
    
    /**
     * Require Authentication
     * 
     * Redirects or returns error if not authenticated
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            Response::unauthorized('Please log in to access this page');
        }
    }
    
    /**
     * Get Current User
     * 
     * @return array|null User data or null
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        if (self::$currentUser === null) {
            $query = "SELECT user_id, email, first_name, last_name, phone_number, address, city, state, zip_code, country, date_of_birth, status FROM users WHERE user_id = ?";
            self::$currentUser = fetchOne($query, [$_SESSION['user_id']]);
        }
        
        return self::$currentUser;
    }
    
    /**
     * Get Current User ID
     * 
     * @return int|null User ID or null
     */
    public static function getCurrentUserID() {
        return self::isAuthenticated() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Destroy Session (Logout)
     */
    public static function destroySession() {
        if (isset($_SESSION['session_id'])) {
            // Remove from database
            execute("DELETE FROM sessions WHERE session_id = ?", [$_SESSION['session_id']]);
        }
        
        // Clear session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Get Client IP Address
     * 
     * @return string Client IP
     */
    private static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Verify CSRF Token
     * 
     * @param string $token CSRF token to verify
     * @return bool True if valid
     */
    public static function verifyCsrf($token) {
        return SecurityHelper::verifyCSRFToken($token);
    }
    
    /**
     * Get CSRF Token
     * 
     * @return string CSRF token
     */
    public static function getCsrfToken() {
        return SecurityHelper::generateCSRFToken();
    }
}
?>
