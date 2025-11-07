<?php

namespace App\Services;

class JwtService
{
    // Simple JWT HS256 implementation (no external deps)
    public static function generateToken(array $payload, string $secret, int $expSeconds = 3600): string
    {
        $secret = self::normalizeSecret($secret);
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now = time();
        $payload = array_merge(['iat' => $now, 'exp' => $now + $expSeconds], $payload);
        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);
        return implode('.', $segments);
    }

    public static function validateToken(string $jwt, string $secret): ?array
    {
        $secret = self::normalizeSecret($secret);
        $parts = explode('.', $jwt);
        if (count($parts) !== 3)
            return null;
        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::base64UrlDecode($h64), true);
        $payload = json_decode(self::base64UrlDecode($p64), true);
        $sig = self::base64UrlDecode($s64);
        $check = hash_hmac('sha256', $h64 . '.' . $p64, $secret, true);
        if (!hash_equals($check, $sig))
            return null;
        if (isset($payload['exp']) && time() > $payload['exp'])
            return null;
        return $payload;
    }

    private static function normalizeSecret(string $secret): string
    {
        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            if ($decoded !== false) {
                return $decoded;
            }
        }
        return $secret;
    }

    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
