<?php
/**
 * Account Model - Account management and balance operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/SecurityHelper.php';

class Account {
    
    /**
     * Create Default Savings Account for New User
     * 
     * @param int $userId User ID
     * @return string Account ID
     */
    public static function createDefault($userId) {
        $accountNumber = SecurityHelper::generateAccountNumber();
        
        $query = "
            INSERT INTO accounts 
            (user_id, account_number, account_type, balance, status)
            VALUES (?, ?, 'savings', 0.00, 'active')
        ";
        
        return insert($query, [$userId, $accountNumber]);
    }
    
    /**
     * Create New Account
     * 
     * @param int $userId User ID
     * @param string $type Account type (savings, checking, money_market)
     * @return array Result
     */
    public static function create($userId, $type = 'savings') {
        try {
            // Check if account type exists
            $existing = fetchOne(
                "SELECT account_id FROM accounts WHERE user_id = ? AND account_type = ?",
                [$userId, $type]
            );
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Account of this type already exists'
                ];
            }
            
            $accountNumber = SecurityHelper::generateAccountNumber();
            
            $query = "
                INSERT INTO accounts 
                (user_id, account_number, account_type, balance, status)
                VALUES (?, ?, ?, 0.00, 'active')
            ";
            
            $accountId = insert($query, [$userId, $accountNumber, $type]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logAccountOperation($userId, $accountId, 'created', ['type' => $type]);
            
            return [
                'success' => true,
                'message' => 'Account created successfully',
                'account_id' => $accountId,
                'account_number' => $accountNumber
            ];
            
        } catch (Exception $e) {
            error_log("Account creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Account creation failed'
            ];
        }
    }
    
    /**
     * Get Account by ID
     * 
     * @param int $accountId Account ID
     * @return array|null Account data
     */
    public static function getById($accountId) {
        $query = "SELECT * FROM accounts WHERE account_id = ?";
        return fetchOne($query, [$accountId]);
    }
    
    /**
     * Get Account by Number
     * 
     * @param string $accountNumber Account number
     * @return array|null Account data
     */
    public static function getByNumber($accountNumber) {
        $query = "SELECT * FROM accounts WHERE account_number = ?";
        return fetchOne($query, [$accountNumber]);
    }
    
    /**
     * Get All Accounts for User
     * 
     * @param int $userId User ID
     * @return array User's accounts
     */
    public static function getByUserId($userId) {
        $query = "
            SELECT * FROM accounts 
            WHERE user_id = ? AND status != 'closed'
            ORDER BY created_at DESC
        ";
        return fetchAll($query, [$userId]);
    }
    
    /**
     * Get Account Balance
     * 
     * @param int $accountId Account ID
     * @return float|null Balance
     */
    public static function getBalance($accountId) {
        $query = "SELECT balance FROM accounts WHERE account_id = ?";
        $result = fetchOne($query, [$accountId]);
        return $result ? (float)$result['balance'] : null;
    }
    
    /**
     * Update Balance (internal use only)
     * 
     * @param int $accountId Account ID
     * @param float $amount Amount to add/subtract
     * @return bool Success
     */
    public static function updateBalance($accountId, $amount) {
        $query = "
            UPDATE accounts 
            SET balance = balance + ?, 
                last_transaction_date = NOW()
            WHERE account_id = ?
        ";
        
        return execute($query, [$amount, $accountId]) > 0;
    }
    
    /**
     * Deposit Funds
     * 
     * @param int $accountId Account ID
     * @param float $amount Amount to deposit
     * @param string $description Deposit description
     * @return array Result
     */
    public static function deposit($accountId, $amount, $description = null) {
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Deposit amount must be greater than zero'
            ];
        }
        
        if ($amount > 999999.99) {
            return [
                'success' => false,
                'message' => 'Deposit amount exceeds maximum limit'
            ];
        }
        
        try {
            beginTransaction();
            
            $account = self::getById($accountId);
            if (!$account || $account['status'] !== 'active') {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Account not available for deposits'
                ];
            }
            
            // Create transaction record
            $referenceNumber = SecurityHelper::generateReferenceNumber();
            $query = "
                INSERT INTO transactions 
                (from_account_id, to_account_id, transaction_type, amount, description, reference_number, status)
                VALUES (?, ?, 'deposit', ?, ?, ?, 'completed')
            ";
            
            $transactionId = insert($query, [
                $accountId,
                $accountId,
                $amount,
                $description ?? 'Deposit',
                $referenceNumber
            ]);
            
            // Update balance
            self::updateBalance($accountId, $amount);
            
            commitTransaction();
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logTransaction(
                $account['user_id'],
                $transactionId,
                'deposit',
                ['amount' => $amount, 'account_id' => $accountId],
                'completed'
            );
            
            return [
                'success' => true,
                'message' => 'Deposit successful',
                'transaction_id' => $transactionId,
                'reference_number' => $referenceNumber,
                'new_balance' => (float)$account['balance'] + $amount
            ];
            
        } catch (Exception $e) {
            rollbackTransaction();
            error_log("Deposit failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Deposit failed. Please try again.'
            ];
        }
    }
    
    /**
     * Withdraw Funds
     * 
     * @param int $accountId Account ID
     * @param float $amount Amount to withdraw
     * @param string $description Withdrawal description
     * @return array Result
     */
    public static function withdraw($accountId, $amount, $description = null) {
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Withdrawal amount must be greater than zero'
            ];
        }
        
        try {
            beginTransaction();
            
            $account = self::getById($accountId);
            if (!$account || $account['status'] !== 'active') {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Account not available for withdrawals'
                ];
            }
            
            if ((float)$account['balance'] < $amount) {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Insufficient funds',
                    'available_balance' => (float)$account['balance']
                ];
            }
            
            // Create transaction record
            $referenceNumber = SecurityHelper::generateReferenceNumber();
            $query = "
                INSERT INTO transactions 
                (from_account_id, to_account_id, transaction_type, amount, description, reference_number, status)
                VALUES (?, ?, 'withdrawal', ?, ?, ?, 'completed')
            ";
            
            $transactionId = insert($query, [
                $accountId,
                $accountId,
                $amount,
                $description ?? 'Withdrawal',
                $referenceNumber
            ]);
            
            // Update balance
            self::updateBalance($accountId, -$amount);
            
            commitTransaction();
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logTransaction(
                $account['user_id'],
                $transactionId,
                'withdrawal',
                ['amount' => $amount, 'account_id' => $accountId],
                'completed'
            );
            
            return [
                'success' => true,
                'message' => 'Withdrawal successful',
                'transaction_id' => $transactionId,
                'reference_number' => $referenceNumber,
                'new_balance' => (float)$account['balance'] - $amount
            ];
            
        } catch (Exception $e) {
            rollbackTransaction();
            error_log("Withdrawal failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Withdrawal failed. Please try again.'
            ];
        }
    }
    
    /**
     * Close Account
     * 
     * @param int $accountId Account ID
     * @return array Result
     */
    public static function close($accountId) {
        try {
            $account = self::getById($accountId);
            
            if (!$account) {
                return [
                    'success' => false,
                    'message' => 'Account not found'
                ];
            }
            
            if ((float)$account['balance'] != 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot close account with remaining balance'
                ];
            }
            
            execute("UPDATE accounts SET status = 'closed', updated_at = NOW() WHERE account_id = ?", [$accountId]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logAccountOperation($account['user_id'], $accountId, 'closed', []);
            
            return [
                'success' => true,
                'message' => 'Account closed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Account close failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Account close failed'
            ];
        }
    }
}
?>
