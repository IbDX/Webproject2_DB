<?php
/**
 * Audit Logger - Compliance and security tracking
 */

require_once __DIR__ . '/../../config/database.php';

class AuditLogger {
    
    /**
     * Log User Action
     * 
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $entityType Entity type (user, account, transaction, etc.)
     * @param int $entityId Entity ID
     * @param array $oldValues Previous values (for updates)
     * @param array $newValues New values (for updates)
     * @param string $status Action status
     */
    public static function log($userId, $action, $entityType, $entityId, $oldValues = [], $newValues = [], $status = 'success') {
        try {
            $ip = self::getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $query = "
                INSERT INTO audit_logs 
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $params = [
                $userId,
                $action,
                $entityType,
                $entityId,
                !empty($oldValues) ? json_encode($oldValues) : null,
                !empty($newValues) ? json_encode($newValues) : null,
                $ip,
                $userAgent,
                $status
            ];
            
            insert($query, $params);
            
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
    
    /**
     * Log Login Attempt
     * 
     * @param int|null $userId User ID (null for failed login)
     * @param string $email Email attempted
     * @param bool $successful Login success
     */
    public static function logLogin($userId, $email, $successful = true) {
        self::log(
            $userId,
            'LOGIN_ATTEMPT',
            'user',
            $userId,
            [],
            ['email' => $email],
            $successful ? 'success' : 'failed'
        );
    }
    
    /**
     * Log Password Change
     * 
     * @param int $userId User ID
     */
    public static function logPasswordChange($userId) {
        self::log(
            $userId,
            'PASSWORD_CHANGE',
            'user',
            $userId,
            [],
            [],
            'success'
        );
    }
    
    /**
     * Log Transaction
     * 
     * @param int $userId User ID
     * @param int $transactionId Transaction ID
     * @param string $type Transaction type
     * @param array $details Transaction details
     * @param string $status Transaction status
     */
    public static function logTransaction($userId, $transactionId, $type, $details, $status) {
        self::log(
            $userId,
            'TRANSACTION_' . strtoupper($type),
            'transaction',
            $transactionId,
            [],
            $details,
            $status
        );
    }
    
    /**
     * Log Account Operation
     * 
     * @param int $userId User ID
     * @param int $accountId Account ID
     * @param string $operation Operation type
     * @param array $changes Changes made
     */
    public static function logAccountOperation($userId, $accountId, $operation, $changes = []) {
        self::log(
            $userId,
            'ACCOUNT_' . strtoupper($operation),
            'account',
            $accountId,
            [],
            $changes,
            'success'
        );
    }
    
    /**
     * Log Failed Security Event
     * 
     * @param int|null $userId User ID
     * @param string $event Event description
     * @param array $details Event details
     */
    public static function logSecurityEvent($userId, $event, $details = []) {
        self::log(
            $userId,
            'SECURITY_' . strtoupper($event),
            'security',
            $userId,
            [],
            $details,
            'alert'
        );
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
            // Handle multiple IPs
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'Unknown';
    }
    
    /**
     * Get Audit Trail for User
     * 
     * @param int $userId User ID
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @return array Audit records
     */
    public static function getUserAuditTrail($userId, $limit = 50, $offset = 0) {
        $query = "
            SELECT * FROM audit_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        return fetchAll($query, [$userId, $limit, $offset]);
    }
    
    /**
     * Get Audit Trail for Entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array Audit records
     */
    public static function getEntityAuditTrail($entityType, $entityId) {
        $query = "
            SELECT * FROM audit_logs
            WHERE entity_type = ? AND entity_id = ?
            ORDER BY created_at DESC
        ";
        
        return fetchAll($query, [$entityType, $entityId]);
    }
    
    /**
     * Clean Old Audit Logs (data retention policy)
     * 
     * @param int $daysToKeep Days to retain logs
     * @return int Number of deleted records
     */
    public static function cleanOldLogs($daysToKeep = 90) {
        $query = "
            DELETE FROM audit_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        return execute($query, [$daysToKeep]);
    }
}
?>
