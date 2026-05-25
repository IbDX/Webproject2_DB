<?php
/**
 * Input Validation and Sanitization Utility
 */

class Validator {
    
    private static $errors = [];
    
    /**
     * Validate Required Field
     * 
     * @param string $value Field value
     * @param string $fieldName Field name for error message
     * @return bool True if not empty
     */
    public static function required($value, $fieldName) {
        if (empty(trim($value))) {
            self::$errors[$fieldName][] = "{$fieldName} is required";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Email Format
     * 
     * @param string $email Email address
     * @param string $fieldName Field name for error message
     * @return bool True if valid email
     */
    public static function email($email, $fieldName = 'Email') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::$errors[$fieldName][] = "{$fieldName} must be a valid email address";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Minimum Length
     * 
     * @param string $value Value to check
     * @param int $min Minimum length
     * @param string $fieldName Field name for error message
     * @return bool True if length is sufficient
     */
    public static function minLength($value, $min, $fieldName) {
        if (strlen($value) < $min) {
            self::$errors[$fieldName][] = "{$fieldName} must be at least {$min} characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Maximum Length
     * 
     * @param string $value Value to check
     * @param int $max Maximum length
     * @param string $fieldName Field name for error message
     * @return bool True if length is within limit
     */
    public static function maxLength($value, $max, $fieldName) {
        if (strlen($value) > $max) {
            self::$errors[$fieldName][] = "{$fieldName} must not exceed {$max} characters";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Numeric Value
     * 
     * @param mixed $value Value to check
     * @param string $fieldName Field name for error message
     * @return bool True if numeric
     */
    public static function numeric($value, $fieldName) {
        if (!is_numeric($value)) {
            self::$errors[$fieldName][] = "{$fieldName} must be a numeric value";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Amount (Money)
     * 
     * @param float $amount Amount to validate
     * @param float $min Minimum amount
     * @param float $max Maximum amount
     * @param string $fieldName Field name for error message
     * @return bool True if valid
     */
    public static function amount($amount, $min = 0.01, $max = 999999.99, $fieldName = 'Amount') {
        if (!is_numeric($amount)) {
            self::$errors[$fieldName][] = "{$fieldName} must be numeric";
            return false;
        }
        
        $amount = (float)$amount;
        
        if ($amount < $min) {
            self::$errors[$fieldName][] = "{$fieldName} must be at least " . number_format($min, 2);
            return false;
        }
        
        if ($amount > $max) {
            self::$errors[$fieldName][] = "{$fieldName} cannot exceed " . number_format($max, 2);
            return false;
        }
        
        // Check decimal places
        if (substr_count(strval($amount), '.') && strlen(substr(strval($amount), strpos(strval($amount), '.') + 1)) > 2) {
            self::$errors[$fieldName][] = "{$fieldName} must have at most 2 decimal places";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate Phone Number
     * 
     * @param string $phone Phone number
     * @param string $fieldName Field name for error message
     * @return bool True if valid
     */
    public static function phone($phone, $fieldName = 'Phone') {
        // Basic phone validation - 10-15 digits with optional hyphens/spaces/+
        if (!preg_match('/^[+]?[(]?[0-9]{3}[)]?[-\s.]?[0-9]{3}[-\s.]?[0-9]{4,6}$/', preg_replace('/\D/', '', $phone))) {
            self::$errors[$fieldName][] = "{$fieldName} must be a valid phone number";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Date Format
     * 
     * @param string $date Date string
     * @param string $format Date format (default: YYYY-MM-DD)
     * @param string $fieldName Field name for error message
     * @return bool True if valid date
     */
    public static function date($date, $format = 'Y-m-d', $fieldName = 'Date') {
        $d = DateTime::createFromFormat($format, $date);
        if (!$d || $d->format($format) !== $date) {
            self::$errors[$fieldName][] = "{$fieldName} must be in format {$format}";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Minimum Value
     * 
     * @param mixed $value Value to check
     * @param mixed $min Minimum value
     * @param string $fieldName Field name for error message
     * @return bool True if value >= min
     */
    public static function min($value, $min, $fieldName) {
        if ($value < $min) {
            self::$errors[$fieldName][] = "{$fieldName} must be at least {$min}";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Maximum Value
     * 
     * @param mixed $value Value to check
     * @param mixed $max Maximum value
     * @param string $fieldName Field name for error message
     * @return bool True if value <= max
     */
    public static function max($value, $max, $fieldName) {
        if ($value > $max) {
            self::$errors[$fieldName][] = "{$fieldName} must not exceed {$max}";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Match (for password confirmation)
     * 
     * @param string $value First value
     * @param string $match Value to match
     * @param string $fieldName Field name for error message
     * @return bool True if values match
     */
    public static function match($value, $match, $fieldName) {
        if ($value !== $match) {
            self::$errors[$fieldName][] = "{$fieldName} values do not match";
            return false;
        }
        return true;
    }
    
    /**
     * Validate Account Number Format
     * 
     * @param string $accountNumber Account number
     * @param string $fieldName Field name for error message
     * @return bool True if valid
     */
    public static function accountNumber($accountNumber, $fieldName = 'Account') {
        if (!preg_match('/^ACC[A-F0-9]{16}$/i', $accountNumber)) {
            self::$errors[$fieldName][] = "{$fieldName} has invalid format";
            return false;
        }
        return true;
    }
    
    /**
     * Get All Validation Errors
     * 
     * @return array Associative array of field names and error messages
     */
    public static function getErrors() {
        return self::$errors;
    }
    
    /**
     * Check if there are any errors
     * 
     * @return bool True if there are errors
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }
    
    /**
     * Clear errors
     */
    public static function clearErrors() {
        self::$errors = [];
    }

    /**
     * Add a custom validation error
     *
     * @param string $fieldName
     * @param string $message
     */
    public static function addError($fieldName, $message) {
        self::$errors[$fieldName][] = $message;
    }
    
    /**
     * Get error messages as array
     * 
     * @param string $fieldName Optional field name to get specific errors
     * @return array Error messages
     */
    public static function getErrorMessages($fieldName = null) {
        if ($fieldName) {
            return self::$errors[$fieldName] ?? [];
        }
        
        $messages = [];
        foreach (self::$errors as $field => $errors) {
            $messages = array_merge($messages, $errors);
        }
        return $messages;
    }
    
    /**
     * Sanitize String Input
     * 
     * @param string $input User input
     * @return string Sanitized input
     */
    public static function sanitizeString($input) {
        return trim(strip_tags($input));
    }
    
    /**
     * Sanitize Email
     * 
     * @param string $email Email address
     * @return string Sanitized email
     */
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize Integer
     * 
     * @param mixed $value Value to sanitize
     * @return int Sanitized integer
     */
    public static function sanitizeInt($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    
    /**
     * Sanitize Float
     * 
     * @param mixed $value Value to sanitize
     * @return float Sanitized float
     */
    public static function sanitizeFloat($value) {
        return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}
?>
