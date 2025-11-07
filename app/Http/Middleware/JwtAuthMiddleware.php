<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\JwtService;
use App\Models\User;

class JwtAuthMiddleware
{
    public function handle($request, Closure $next)
    {
        // Accept token from Authorization header, query param 'token', or cookie 'token'
        $auth = $request->header('Authorization') ?: $request->get('token');
        if (!$auth && $request->cookies->has('token')) {
            $auth = $request->cookies->get('token');
        }
        if (!$auth)
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        if (stripos($auth, 'Bearer ') === 0) {
            $token = substr($auth, 7);
        } else {
            $token = $auth;
        }
        // Use JWT_SECRET if provided so tokens are compatible with other services
        $secret = env('JWT_SECRET', env('APP_KEY'));
        $payload = JwtService::validateToken($token, $secret);
        if (!$payload || !isset($payload['sub']) && !isset($payload['userId']) && !isset($payload['email'])) {
            return response()->json(['success' => false, 'message' => 'invalid_token'], 401);
        }

        // Determine user from token. Support several shapes:
        // - sub as numeric id
        // - sub as email (string)
        // - userId claim
        // - email claim
        $user = null;
        if (isset($payload['sub'])) {
            $sub = $payload['sub'];
            if (is_numeric($sub)) {
                $user = User::find(intval($sub));
            } elseif (is_string($sub) && filter_var($sub, FILTER_VALIDATE_EMAIL)) {
                $user = User::where('email', $sub)->first();
            }
        }
        if (!$user && isset($payload['userId'])) {
            $user = User::find($payload['userId']);
        }
        if (!$user && isset($payload['email']) && filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $payload['email'])->first();
        }
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'user_not_found'], 401);
        }
        // attach
        $request->attributes->set('auth_user', $user);
        return $next($request);
    }
}
