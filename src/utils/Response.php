<?php
/**
 * Response Handler - JSON and HTML responses
 */

class Response {
    
    private static $statusCodes = [
        200 => 'OK',
        201 => 'Created',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable'
    ];
    
    /**
     * Send JSON Response
     * 
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $headers Additional headers
     */
    public static function json($data, $statusCode = 200, $headers = []) {
        http_response_code($statusCode);
        
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send Success Response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $statusCode HTTP status code
     */
    public static function success($data = null, $message = 'Request successful', $statusCode = 200) {
        self::json([
            'success' => true,
            'status_code' => $statusCode,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('c')
        ], $statusCode);
    }
    
    /**
     * Send Error Response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $errors Additional error details
     */
    public static function error($message = 'An error occurred', $statusCode = 400, $errors = []) {
        self::json([
            'success' => false,
            'status_code' => $statusCode,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ], $statusCode);
    }
    
    /**
     * Send Validation Error Response
     * 
     * @param array $errors Validation errors
     * @param string $message Error message
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::json([
            'success' => false,
            'status_code' => 422,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('c')
        ], 422);
    }
    
    /**
     * Send Unauthorized Response
     * 
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, 401);
    }
    
    /**
     * Send Forbidden Response
     * 
     * @param string $message Error message
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, 403);
    }
    
    /**
     * Send Not Found Response
     * 
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, 404);
    }
    
    /**
     * Send Server Error Response
     * 
     * @param string $message Error message
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, 500);
    }
    
    /**
     * Send Paginated Response
     * 
     * @param array $data Data items
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param int $total Total items
     * @param string $message Success message
     */
    public static function paginated($data, $page, $perPage, $total, $message = 'Data retrieved successfully') {
        $totalPages = ceil($total / $perPage);
        
        self::json([
            'success' => true,
            'status_code' => 200,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => (int)$total,
                'total_pages' => (int)$totalPages,
                'has_more' => $page < $totalPages
            ],
            'timestamp' => date('c')
        ], 200);
    }
    
    /**
     * Send HTML Response
     * 
     * @param string $html HTML content
     * @param int $statusCode HTTP status code
     */
    public static function html($html, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    
    /**
     * Redirect to URL
     * 
     * @param string $url URL to redirect to
     * @param int $statusCode HTTP status code (301 or 302)
     */
    public static function redirect($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }
    
    /**
     * Set HTTP Status
     * 
     * @param int $statusCode HTTP status code
     */
    public static function setStatus($statusCode) {
        http_response_code($statusCode);
    }
    
    /**
     * Get Status Code Text
     * 
     * @param int $statusCode Status code
     * @return string Status text
     */
    public static function getStatusText($statusCode) {
        return self::$statusCodes[$statusCode] ?? 'Unknown';
    }
}
?>
