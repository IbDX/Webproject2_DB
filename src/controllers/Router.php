<?php
/**
 * API Router - Main routing handler for all endpoints
 */

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/AuthController.php';
require_once __DIR__ . '/AccountController.php';
require_once __DIR__ . '/TransactionController.php';
require_once __DIR__ . '/ProfileController.php';

class Router {
    
    private static $method;
    private static $path;
    private static $params;
    
    /**
     * Initialize and Route Request
     */
    public static function init() {
        self::$method = $_SERVER['REQUEST_METHOD'];
        self::$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        // Extract the API segment even when the app runs from a subfolder.
        $parts = self::$path === '' ? [] : explode('/', self::$path);
        $apiIndex = array_search('api', $parts, true);

        if ($apiIndex === false) {
            Response::notFound('Route not found');
        }

        $routeParts = array_slice($parts, $apiIndex + 1);
        $route = array_shift($routeParts) ?? '';
        self::$params = $routeParts;
        
        // Route to appropriate controller
        switch ($route) {
            case 'auth':
                self::routeAuth();
                break;
            case 'accounts':
                self::routeAccounts();
                break;
            case 'transactions':
                self::routeTransactions();
                break;
            case 'profile':
                self::routeProfile();
                break;
            case 'beneficiaries':
                self::routeBeneficiaries();
                break;
            default:
                Response::notFound('Route not found');
        }
    }
    
    /**
     * Route Auth Endpoints
     */
    private static function routeAuth() {
        $action = self::$params[0] ?? '';
        
        switch ($action) {
            case 'register':
                if (self::$method !== 'POST') {
                    Response::error('Method not allowed', 405);
                }
                AuthController::register();
                break;
                
            case 'login':
                if (self::$method !== 'POST') {
                    Response::error('Method not allowed', 405);
                }
                AuthController::login();
                break;
                
            case 'logout':
                if (self::$method !== 'POST') {
                    Response::error('Method not allowed', 405);
                }
                AuthMiddleware::requireAuth();
                AuthController::logout();
                break;
                
            case 'me':
                if (self::$method !== 'GET') {
                    Response::error('Method not allowed', 405);
                }
                AuthMiddleware::requireAuth();
                AuthController::getCurrentUser();
                break;
                
            default:
                Response::notFound('Auth endpoint not found');
        }
    }
    
    /**
     * Route Account Endpoints
     */
    private static function routeAccounts() {
        AuthMiddleware::requireAuth();
        
        if (empty(self::$params[0])) {
            // List accounts
            if (self::$method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            AccountController::listAccounts();
        } else {
            $accountId = self::$params[0];
            $action = self::$params[1] ?? '';
            
            switch ($action) {
                case '':
                    // Get single account
                    if (self::$method === 'GET') {
                        AccountController::getAccount($accountId);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                    break;
                    
                case 'deposit':
                    if (self::$method !== 'POST') {
                        Response::error('Method not allowed', 405);
                    }
                    AccountController::deposit($accountId);
                    break;
                    
                case 'withdraw':
                    if (self::$method !== 'POST') {
                        Response::error('Method not allowed', 405);
                    }
                    AccountController::withdraw($accountId);
                    break;
                    
                case 'transfer':
                    if (self::$method !== 'POST') {
                        Response::error('Method not allowed', 405);
                    }
                    AccountController::transfer($accountId);
                    break;
                    
                case 'close':
                    if (self::$method !== 'POST') {
                        Response::error('Method not allowed', 405);
                    }
                    AccountController::closeAccount($accountId);
                    break;
                    
                case 'history':
                    if (self::$method !== 'GET') {
                        Response::error('Method not allowed', 405);
                    }
                    AccountController::getTransactionHistory($accountId);
                    break;
                    
                default:
                    Response::notFound('Account action not found');
            }
        }
    }
    
    /**
     * Route Transaction Endpoints
     */
    private static function routeTransactions() {
        AuthMiddleware::requireAuth();
        
        if (empty(self::$params[0])) {
            // List recent transactions
            if (self::$method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            TransactionController::getRecent();
        } else {
            $transactionId = self::$params[0];
            
            if (self::$method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            TransactionController::getTransaction($transactionId);
        }
    }
    
    /**
     * Route Profile Endpoints
     */
    private static function routeProfile() {
        AuthMiddleware::requireAuth();
        
        $action = self::$params[0] ?? '';
        
        switch ($action) {
            case '':
                if (self::$method === 'GET') {
                    ProfileController::getProfile();
                } elseif (self::$method === 'PUT') {
                    ProfileController::updateProfile();
                } else {
                    Response::error('Method not allowed', 405);
                }
                break;
                
            case 'change-password':
                if (self::$method !== 'POST') {
                    Response::error('Method not allowed', 405);
                }
                ProfileController::changePassword();
                break;
                
            case 'deactivate':
                if (self::$method !== 'POST') {
                    Response::error('Method not allowed', 405);
                }
                ProfileController::deactivateAccount();
                break;
                
            default:
                Response::notFound('Profile action not found');
        }
    }
    
    /**
     * Route Beneficiary Endpoints
     */
    private static function routeBeneficiaries() {
        AuthMiddleware::requireAuth();
        
        if (empty(self::$params[0])) {
            // List beneficiaries
            if (self::$method !== 'GET') {
                Response::error('Method not allowed', 405);
            }
            TransactionController::listBeneficiaries();
        } else {
            $beneficiaryId = self::$params[0];
            $action = self::$params[1] ?? '';
            
            switch ($action) {
                case '':
                    if (self::$method === 'GET') {
                        TransactionController::getBeneficiary($beneficiaryId);
                    } elseif (self::$method === 'PUT') {
                        TransactionController::updateBeneficiary($beneficiaryId);
                    } elseif (self::$method === 'DELETE') {
                        TransactionController::removeBeneficiary($beneficiaryId);
                    } else {
                        Response::error('Method not allowed', 405);
                    }
                    break;
                    
                default:
                    Response::notFound('Beneficiary action not found');
            }
        }
    }
}

// Initialize routing
Router::init();
?>
