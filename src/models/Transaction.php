<?php
/**
 * Transaction Model - Transfer operations and transaction history
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/SecurityHelper.php';
require_once __DIR__ . '/Account.php';

class Transaction {
    
    /**
     * Transfer Funds Between Accounts
     * 
     * @param int $fromAccountId Source account ID
     * @param int $toAccountId Destination account ID
     * @param float $amount Transfer amount
     * @param string $description Transfer description
     * @return array Result
     */
    public static function transfer($fromAccountId, $toAccountId, $amount, $description = null) {
        if ($fromAccountId == $toAccountId) {
            return [
                'success' => false,
                'message' => 'Cannot transfer to the same account'
            ];
        }
        
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Transfer amount must be greater than zero'
            ];
        }
        
        if ($amount > 999999.99) {
            return [
                'success' => false,
                'message' => 'Transfer amount exceeds maximum limit'
            ];
        }
        
        try {
            beginTransaction();
            
            // Verify accounts
            $fromAccount = Account::getById($fromAccountId);
            $toAccount = Account::getById($toAccountId);
            
            if (!$fromAccount || $fromAccount['status'] !== 'active') {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Source account not available'
                ];
            }
            
            if (!$toAccount || $toAccount['status'] !== 'active') {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Destination account not available'
                ];
            }
            
            // Check balance
            if ((float)$fromAccount['balance'] < $amount) {
                rollbackTransaction();
                return [
                    'success' => false,
                    'message' => 'Insufficient funds',
                    'available_balance' => (float)$fromAccount['balance']
                ];
            }
            
            // Create transaction record
            $referenceNumber = SecurityHelper::generateReferenceNumber();
            $query = "
                INSERT INTO transactions 
                (from_account_id, to_account_id, transaction_type, amount, description, reference_number, status, metadata)
                VALUES (?, ?, 'transfer', ?, ?, ?, 'completed', ?)
            ";
            
            $metadata = json_encode([
                'from_account_number' => $fromAccount['account_number'],
                'to_account_number' => $toAccount['account_number'],
                'from_user_id' => $fromAccount['user_id'],
                'to_user_id' => $toAccount['user_id']
            ]);
            
            $transactionId = insert($query, [
                $fromAccountId,
                $toAccountId,
                $amount,
                $description ?? 'Transfer',
                $referenceNumber,
                $metadata
            ]);
            
            // Deduct from source
            Account::updateBalance($fromAccountId, -$amount);
            
            // Add to destination
            Account::updateBalance($toAccountId, $amount);
            
            commitTransaction();
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::logTransaction(
                $fromAccount['user_id'],
                $transactionId,
                'transfer',
                [
                    'amount' => $amount,
                    'from_account' => $fromAccountId,
                    'to_account' => $toAccountId
                ],
                'completed'
            );
            
            return [
                'success' => true,
                'message' => 'Transfer successful',
                'transaction_id' => $transactionId,
                'reference_number' => $referenceNumber,
                'from_balance' => (float)$fromAccount['balance'] - $amount,
                'to_balance' => (float)$toAccount['balance'] + $amount
            ];
            
        } catch (Exception $e) {
            rollbackTransaction();
            error_log("Transfer failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Transfer failed. Please try again.'
            ];
        }
    }
    
    /**
     * Get Transaction by ID
     * 
     * @param int $transactionId Transaction ID
     * @return array|null Transaction data
     */
    public static function getById($transactionId) {
        $query = "SELECT * FROM transactions WHERE transaction_id = ?";
        return fetchOne($query, [$transactionId]);
    }
    
    /**
     * Get Transaction by Reference Number
     * 
     * @param string $referenceNumber Reference number
     * @return array|null Transaction data
     */
    public static function getByReference($referenceNumber) {
        $query = "SELECT * FROM transactions WHERE reference_number = ?";
        return fetchOne($query, [$referenceNumber]);
    }
    
    /**
     * Get Transaction History for Account
     * 
     * @param int $accountId Account ID
     * @param int $limit Limit number of records
     * @param int $page Page number
     * @return array Transactions and pagination info
     */
    public static function getHistory($accountId, $limit = 20, $page = 1) {
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countQuery = "
            SELECT COUNT(*) as total FROM transactions
            WHERE (from_account_id = ? OR to_account_id = ?)
            AND status != 'failed'
        ";
        $countResult = fetchOne($countQuery, [$accountId, $accountId]);
        $total = $countResult['total'] ?? 0;
        
        // Get paginated transactions
        $query = "
            SELECT 
                transaction_id,
                from_account_id,
                to_account_id,
                transaction_type,
                amount,
                description,
                status,
                reference_number,
                created_at,
                completed_at
            FROM transactions
            WHERE (from_account_id = ? OR to_account_id = ?)
            AND status != 'failed'
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $transactions = fetchAll($query, [$accountId, $accountId, $limit, $offset]);
        
        return [
            'success' => true,
            'transactions' => $transactions,
            'pagination' => [
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Get Recent Transactions for User
     * 
     * @param int $userId User ID
     * @param int $limit Number of transactions
     * @return array Recent transactions
     */
    public static function getRecentForUser($userId, $limit = 10) {
        $query = "
            SELECT 
                t.transaction_id,
                t.from_account_id,
                t.to_account_id,
                t.transaction_type,
                t.amount,
                t.description,
                t.status,
                t.reference_number,
                t.created_at,
                a.account_number as from_account_number
            FROM transactions t
            LEFT JOIN accounts a ON t.from_account_id = a.account_id
            WHERE t.from_account_id IN (
                SELECT account_id FROM accounts WHERE user_id = ?
            )
            OR t.to_account_id IN (
                SELECT account_id FROM accounts WHERE user_id = ?
            )
            ORDER BY t.created_at DESC
            LIMIT ?
        ";
        
        return fetchAll($query, [$userId, $userId, $limit]);
    }
    
    /**
     * Get Monthly Transaction Summary
     * 
     * @param int $accountId Account ID
     * @param int $year Year
     * @param int $month Month
     * @return array Transaction summary
     */
    public static function getMonthlySummary($accountId, $year, $month) {
        $query = "
            SELECT 
                transaction_type,
                COUNT(*) as count,
                SUM(amount) as total
            FROM transactions
            WHERE (from_account_id = ? OR to_account_id = ?)
            AND YEAR(created_at) = ?
            AND MONTH(created_at) = ?
            AND status = 'completed'
            GROUP BY transaction_type
        ";
        
        return fetchAll($query, [$accountId, $accountId, $year, $month]);
    }
    
    /**
     * Verify Transaction Ownership
     * 
     * @param int $userId User ID
     * @param int $transactionId Transaction ID
     * @return bool True if user owns transaction
     */
    public static function verifyOwnership($userId, $transactionId) {
        $query = "
            SELECT COUNT(*) as count FROM transactions t
            JOIN accounts a ON (t.from_account_id = a.account_id OR t.to_account_id = a.account_id)
            WHERE t.transaction_id = ? AND a.user_id = ?
        ";
        
        $result = fetchOne($query, [$transactionId, $userId]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Get Transaction Statistics
     * 
     * @param int $accountId Account ID
     * @param int $days Days to analyze
     * @return array Statistics
     */
    public static function getStatistics($accountId, $days = 30) {
        $query = "
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals,
                SUM(CASE WHEN transaction_type = 'transfer' AND from_account_id = ? THEN amount ELSE 0 END) as total_sent,
                SUM(CASE WHEN transaction_type = 'transfer' AND to_account_id = ? THEN amount ELSE 0 END) as total_received,
                AVG(amount) as average_transaction,
                MAX(amount) as largest_transaction
            FROM transactions
            WHERE (from_account_id = ? OR to_account_id = ?)
            AND status = 'completed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $stats = fetchOne($query, [$accountId, $accountId, $accountId, $accountId, $days]);
        
        return [
            'total_transactions' => (int)($stats['total_transactions'] ?? 0),
            'total_deposits' => (float)($stats['total_deposits'] ?? 0),
            'total_withdrawals' => (float)($stats['total_withdrawals'] ?? 0),
            'total_sent' => (float)($stats['total_sent'] ?? 0),
            'total_received' => (float)($stats['total_received'] ?? 0),
            'average_transaction' => (float)($stats['average_transaction'] ?? 0),
            'largest_transaction' => (float)($stats['largest_transaction'] ?? 0)
        ];
    }
}
?>
