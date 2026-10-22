<?php
namespace Hugmart\Middleware;

use Hugmart\Helpers\Response;

class AuthMiddleware {
    public static function authenticate(): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized access. Please login first.']);
            exit();
        }
        return $_SESSION['user'];
    }

    public static function authorize(array $allowedRoles): void {
        $user = self::authenticate();
        if (!in_array($user['role'], $allowedRoles)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: You do not have permission to access this resource.']);
            exit();
        }
    }
}