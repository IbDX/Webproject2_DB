<?php
/**
 * Beneficiary Model - Manage transfer recipients
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Validator.php';

class Beneficiary {
    
    /**
     * Add Beneficiary
     * 
     * @param int $userId User ID
     * @param string $accountNumber Account number
     * @param string $beneficiaryName Beneficiary name
     * @param string $bankName Bank name (optional)
     * @param string $relationship Relationship description
     * @return array Result
     */
    public static function add($userId, $accountNumber, $beneficiaryName, $bankName = null, $relationship = null) {
        // Validate
        Validator::clearErrors();
        Validator::required($accountNumber, 'Account Number');
        Validator::required($beneficiaryName, 'Beneficiary Name');
        Validator::maxLength($beneficiaryName, 100, 'Beneficiary Name');
        
        if (Validator::hasErrors()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => Validator::getErrors()
            ];
        }
        
        try {
            // Check if account exists and is active
            $targetAccount = fetchOne(
                "SELECT account_id FROM accounts WHERE account_number = ? AND status = 'active'",
                [$accountNumber]
            );
            
            if (!$targetAccount) {
                return [
                    'success' => false,
                    'message' => 'Account not found or inactive'
                ];
            }
            
            // Check if already added
            $existing = fetchOne(
                "SELECT beneficiary_id FROM beneficiaries WHERE user_id = ? AND account_number = ?",
                [$userId, $accountNumber]
            );
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Beneficiary already exists'
                ];
            }
            
            // Add beneficiary
            $query = "
                INSERT INTO beneficiaries 
                (user_id, account_number, beneficiary_name, bank_name, relationship, verified)
                VALUES (?, ?, ?, ?, ?, 1)
            ";
            
            $beneficiaryId = insert($query, [
                $userId,
                $accountNumber,
                $beneficiaryName,
                $bankName,
                $relationship
            ]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log(
                $userId,
                'BENEFICIARY_ADDED',
                'beneficiary',
                $beneficiaryId,
                [],
                ['account_number' => $accountNumber, 'name' => $beneficiaryName],
                'success'
            );
            
            return [
                'success' => true,
                'message' => 'Beneficiary added successfully',
                'beneficiary_id' => $beneficiaryId
            ];
            
        } catch (Exception $e) {
            error_log("Add beneficiary failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to add beneficiary'
            ];
        }
    }
    
    /**
     * Get Beneficiary by ID
     * 
     * @param int $beneficiaryId Beneficiary ID
     * @return array|null Beneficiary data
     */
    public static function getById($beneficiaryId) {
        $query = "SELECT * FROM beneficiaries WHERE beneficiary_id = ?";
        return fetchOne($query, [$beneficiaryId]);
    }
    
    /**
     * Get All Beneficiaries for User
     * 
     * @param int $userId User ID
     * @return array User's beneficiaries
     */
    public static function getByUserId($userId) {
        $query = "SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY created_at DESC";
        return fetchAll($query, [$userId]);
    }
    
    /**
     * Update Beneficiary
     * 
     * @param int $beneficiaryId Beneficiary ID
     * @param array $data Updated data
     * @return array Result
     */
    public static function update($beneficiaryId, $data) {
        try {
            $beneficiary = self::getById($beneficiaryId);
            
            if (!$beneficiary) {
                return [
                    'success' => false,
                    'message' => 'Beneficiary not found'
                ];
            }
            
            $query = "
                UPDATE beneficiaries SET 
                beneficiary_name = COALESCE(?, beneficiary_name),
                bank_name = COALESCE(?, bank_name),
                relationship = COALESCE(?, relationship),
                updated_at = NOW()
                WHERE beneficiary_id = ?
            ";
            
            execute($query, [
                $data['beneficiary_name'] ?? null,
                $data['bank_name'] ?? null,
                $data['relationship'] ?? null,
                $beneficiaryId
            ]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log(
                $beneficiary['user_id'],
                'BENEFICIARY_UPDATED',
                'beneficiary',
                $beneficiaryId,
                [],
                $data,
                'success'
            );
            
            return [
                'success' => true,
                'message' => 'Beneficiary updated successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Update beneficiary failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update beneficiary'
            ];
        }
    }
    
    /**
     * Remove Beneficiary
     * 
     * @param int $beneficiaryId Beneficiary ID
     * @param int $userId User ID (for verification)
     * @return array Result
     */
    public static function remove($beneficiaryId, $userId) {
        try {
            $beneficiary = self::getById($beneficiaryId);
            
            if (!$beneficiary || $beneficiary['user_id'] != $userId) {
                return [
                    'success' => false,
                    'message' => 'Beneficiary not found'
                ];
            }
            
            execute("DELETE FROM beneficiaries WHERE beneficiary_id = ?", [$beneficiaryId]);
            
            require_once __DIR__ . '/../utils/AuditLogger.php';
            AuditLogger::log(
                $userId,
                'BENEFICIARY_REMOVED',
                'beneficiary',
                $beneficiaryId,
                ['name' => $beneficiary['beneficiary_name']],
                [],
                'success'
            );
            
            return [
                'success' => true,
                'message' => 'Beneficiary removed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Remove beneficiary failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to remove beneficiary'
            ];
        }
    }
    
    /**
     * Verify Beneficiary Ownership
     * 
     * @param int $userId User ID
     * @param int $beneficiaryId Beneficiary ID
     * @return bool True if user owns beneficiary
     */
    public static function verifyOwnership($userId, $beneficiaryId) {
        $query = "SELECT user_id FROM beneficiaries WHERE beneficiary_id = ?";
        $beneficiary = fetchOne($query, [$beneficiaryId]);
        
        return $beneficiary && $beneficiary['user_id'] == $userId;
    }
}
?>
