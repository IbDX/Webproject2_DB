<?php
/**
 * Auth Controller - Authentication operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AuthController {

    /**
     * Read the request body as JSON first, then fall back to form-encoded input.
     */
    private static function getInput() {
        $rawInput = file_get_contents('php://input');
        $jsonInput = json_decode($rawInput, true);

        if (is_array($jsonInput)) {
            return $jsonInput;
        }

        $formInput = [];
        parse_str($rawInput, $formInput);

        if (!empty($formInput)) {
            return $formInput;
        }

        return $_POST;
    }
    
    /**
     * Register New User
     */
    public static function register() {
        // Get JSON input
        $input = self::getInput();
        
        if (!$input) {
            Response::error('Invalid request', 400);
        }
        
        // Register user
        $result = User::create($input);
        
        if (!$result['success']) {
            Response::error(
                $result['message'],
                isset($result['errors']) ? 422 : 400,
                $result['errors'] ?? []
            );
        }
        
        Response::success([
            'user_id' => $result['user_id'],
            'message' => 'Registration successful. Please log in.'
        ], 'User registered successfully', 201);
    }
    
    /**
     * Login User
     */
    public static function login() {
        // Get JSON input
        $input = self::getInput();
        
        if (!$input || empty($input['email']) || empty($input['password'])) {
            Response::validationError(['email' => 'Email and password required']);
        }
        
        // Authenticate
        $result = AuthMiddleware::authenticate($input['email'], $input['password']);
        
        if (!$result['success']) {
            Response::error($result['message'], 401);
        }
        
        Response::success([
            'user' => $result['user'],
            'token' => $result['token'],
            'session_id' => $result['session_id']
        ], 'Login successful', 200);
    }
    
    /**
     * Logout User
     */
    public static function logout() {
        AuthMiddleware::destroySession();
        Response::success(null, 'Logout successful');
    }
    
    /**
     * Get Current User
     */
    public static function getCurrentUser() {
        $user = AuthMiddleware::getCurrentUser();
        
        if (!$user) {
            Response::unauthorized();
        }
        
        // Get user accounts
        require_once __DIR__ . '/../models/Account.php';
        $accounts = Account::getByUserId($user['user_id']);
        
        Response::success([
            'user' => $user,
            'accounts' => $accounts
        ], 'User data retrieved');
    }
}
?>
