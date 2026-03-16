<?php
/**
 * Chatbot Builder System - JWT Authentication Class
 */

namespace Chatbot;

class JWT {
    
    /**
     * Generate JWT token
     */
    public static function generate(array $payload, int $expiry = null): string {
        $expiry = $expiry ?? Config::JWT_EXPIRY;
        
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);
        
        $time = time();
        $payload['iat'] = $time;
        $payload['exp'] = $time + $expiry;
        
        $payloadJson = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payloadJson));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, Config::JWT_SECRET, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify and decode JWT token
     */
    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        [$base64Header, $base64Payload, $base64Signature] = $parts;
        
        // Verify signature
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, Config::JWT_SECRET, true);
        $expectedSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($expectedSignature, $base64Signature)) {
            return null;
        }
        
        // Decode payload
        $payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload));
        $payload = json_decode($payloadJson, true);
        
        if (!$payload || !isset($payload['exp'])) {
            return null;
        }
        
        // Check expiration
        if ($payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Extract token from Authorization header
     */
    public static function fromHeader(): ?string {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get authenticated user ID from token
     */
    public static function getUserId(): ?int {
        $token = self::fromHeader();
        
        if (!$token) {
            return null;
        }
        
        $payload = self::verify($token);
        
        return $payload['user_id'] ?? null;
    }
    
    /**
     * Refresh token
     */
    public static function refresh(string $token, int $expiry = null): ?string {
        $payload = self::verify($token);
        
        if (!$payload) {
            return null;
        }
        
        unset($payload['iat'], $payload['exp']);
        
        return self::generate($payload, $expiry);
    }
}
