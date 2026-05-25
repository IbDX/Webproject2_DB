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
 * Account Controller - Account operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Account.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AccountController {
    
    /**
     * List User's Accounts
     */
    public static function listAccounts() {
        $userId = AuthMiddleware::getCurrentUserID();
        $accounts = Account::getByUserId($userId);
        
        Response::success($accounts, 'Accounts retrieved successfully');
    }
    
    /**
     * Get Single Account Details
     */
    public static function getAccount($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        // Verify ownership
        $account = Account::getById($accountId);
        if (!$account || $account['user_id'] != $userId) {
            Response::notFound();
        }
        
        Response::success($account, 'Account retrieved successfully');
    }
    
    /**
     * Deposit to Account
     */
    public static function deposit($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verify account ownership
        $account = Account::getById($accountId);
        if (!$account || $account['user_id'] != $userId) {
            Response::notFound();
        }
        
        // Validate input
        Validator::clearErrors();
        Validator::required($input['amount'] ?? '', 'Amount');
        Validator::amount($input['amount'] ?? 0, 0.01, 999999.99, 'Amount');
        
        if (Validator::hasErrors()) {
            Response::validationError(Validator::getErrors());
        }
        
        // Process deposit
        $result = Account::deposit($accountId, $input['amount'], $input['description'] ?? null);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success($result, 'Deposit processed successfully', 201);
    }
    
    /**
     * Withdraw from Account
     */
    public static function withdraw($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verify account ownership
        $account = Account::getById($accountId);
        if (!$account || $account['user_id'] != $userId) {
            Response::notFound();
        }
        
        // Validate input
        Validator::clearErrors();
        Validator::required($input['amount'] ?? '', 'Amount');
        Validator::amount($input['amount'] ?? 0, 0.01, 999999.99, 'Amount');
        
        if (Validator::hasErrors()) {
            Response::validationError(Validator::getErrors());
        }
        
        // Process withdrawal
        $result = Account::withdraw($accountId, $input['amount'], $input['description'] ?? null);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success($result, 'Withdrawal processed successfully', 201);
    }
    
    /**
     * Transfer Funds
     */
    public static function transfer($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Verify source account ownership
        $fromAccount = Account::getById($accountId);
        if (!$fromAccount || $fromAccount['user_id'] != $userId) {
            Response::notFound();
        }
        
        // Validate input
        Validator::clearErrors();
        Validator::required($input['to_account_id'] ?? '', 'Destination Account');
        Validator::required($input['amount'] ?? '', 'Amount');
        Validator::amount($input['amount'] ?? 0, 0.01, 999999.99, 'Amount');
        
        if (Validator::hasErrors()) {
            Response::validationError(Validator::getErrors());
        }
        
        // Verify destination account exists
        $toAccount = Account::getById($input['to_account_id']);
        if (!$toAccount) {
            Response::error('Destination account not found', 404);
        }
        
        // Process transfer
        $result = Transaction::transfer(
            $accountId,
            $input['to_account_id'],
            $input['amount'],
            $input['description'] ?? null
        );
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success($result, 'Transfer completed successfully', 201);
    }
    
    /**
     * Close Account
     */
    public static function closeAccount($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        // Verify account ownership
        $account = Account::getById($accountId);
        if (!$account || $account['user_id'] != $userId) {
            Response::notFound();
        }
        
        // Close account
        $result = Account::close($accountId);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success(null, 'Account closed successfully');
    }
    
    /**
     * Get Transaction History
     */
    public static function getTransactionHistory($accountId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        // Verify account ownership
        $account = Account::getById($accountId);
        if (!$account || $account['user_id'] != $userId) {
            Response::notFound();
        }
        
        // Get pagination params
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        
        // Get history
        $result = Transaction::getHistory($accountId, $limit, $page);
        
        Response::paginated(
            $result['transactions'],
            $result['pagination']['page'],
            $result['pagination']['per_page'] ?? $limit,
            $result['pagination']['total'],
            'Transaction history retrieved'
        );
    }
}
?>
