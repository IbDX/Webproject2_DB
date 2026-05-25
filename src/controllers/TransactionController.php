<?php
/**
 * Transaction Controller - Transaction history and beneficiary management
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Beneficiary.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class TransactionController {
    
    /**
     * Get Recent Transactions
     */
    public static function getRecent() {
        $userId = AuthMiddleware::getCurrentUserID();
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
        
        $transactions = Transaction::getRecentForUser($userId, $limit);
        
        Response::success($transactions, 'Recent transactions retrieved');
    }
    
    /**
     * Get Single Transaction
     */
    public static function getTransaction($transactionId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        $transaction = Transaction::getById($transactionId);
        
        if (!$transaction || !Transaction::verifyOwnership($userId, $transactionId)) {
            Response::notFound();
        }
        
        Response::success($transaction, 'Transaction retrieved');
    }
    
    /**
     * List Beneficiaries
     */
    public static function listBeneficiaries() {
        $userId = AuthMiddleware::getCurrentUserID();
        $beneficiaries = Beneficiary::getByUserId($userId);
        
        Response::success($beneficiaries, 'Beneficiaries retrieved');
    }
    
    /**
     * Get Single Beneficiary
     */
    public static function getBeneficiary($beneficiaryId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        if (!Beneficiary::verifyOwnership($userId, $beneficiaryId)) {
            Response::notFound();
        }
        
        $beneficiary = Beneficiary::getById($beneficiaryId);
        
        Response::success($beneficiary, 'Beneficiary retrieved');
    }
    
    /**
     * Add Beneficiary
     */
    public static function addBeneficiary() {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        Validator::clearErrors();
        Validator::required($input['account_number'] ?? '', 'Account Number');
        Validator::required($input['beneficiary_name'] ?? '', 'Beneficiary Name');
        
        if (Validator::hasErrors()) {
            Response::validationError(Validator::getErrors());
        }
        
        // Add beneficiary
        $result = Beneficiary::add(
            $userId,
            $input['account_number'],
            $input['beneficiary_name'],
            $input['bank_name'] ?? null,
            $input['relationship'] ?? null
        );
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success($result, 'Beneficiary added successfully', 201);
    }
    
    /**
     * Update Beneficiary
     */
    public static function updateBeneficiary($beneficiaryId) {
        $userId = AuthMiddleware::getCurrentUserID();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!Beneficiary::verifyOwnership($userId, $beneficiaryId)) {
            Response::notFound();
        }
        
        $result = Beneficiary::update($beneficiaryId, $input);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success(null, 'Beneficiary updated successfully');
    }
    
    /**
     * Remove Beneficiary
     */
    public static function removeBeneficiary($beneficiaryId) {
        $userId = AuthMiddleware::getCurrentUserID();
        
        if (!Beneficiary::verifyOwnership($userId, $beneficiaryId)) {
            Response::notFound();
        }
        
        $result = Beneficiary::remove($beneficiaryId, $userId);
        
        if (!$result['success']) {
            Response::error($result['message'], 400);
        }
        
        Response::success(null, 'Beneficiary removed successfully');
    }
}
?>
