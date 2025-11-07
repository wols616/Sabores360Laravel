<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Relaxed CORS: allow any origin (echo back origin) and credentials. Use only in development.
        $origin = $request->headers->get('Origin');
        $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        $allowMaxAge = '86400';

        // Preflight handling
        if ($request->getMethod() === 'OPTIONS') {
            if ($origin) {
                return response()->json('OK', 200, [
                    'Access-Control-Allow-Origin' => $origin,
                    'Vary' => 'Origin',
                    'Access-Control-Allow-Methods' => $allowMethods,
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Max-Age' => $allowMaxAge,
                    'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers') ?: 'Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-Token',
                ]);
            }
            // No Origin header, fall back to wildcard without credentials
            return response()->json('OK', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Allow-Credentials' => 'false',
                'Access-Control-Max-Age' => $allowMaxAge,
                'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers') ?: 'Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-Token',
            ]);
        }

        $response = $next($request);

        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Credentials', 'false');
        }

        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-Token');
        $response->headers->set('Access-Control-Max-Age', $allowMaxAge);

        return $response;
    }
}
