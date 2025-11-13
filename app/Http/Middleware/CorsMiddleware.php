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
        $allowHeaders = 'Content-Type, Accept, Authorization, X-Requested-With, X-CSRF-Token';

        // Preflight handling
        if ($request->getMethod() === 'OPTIONS') {
            if ($origin) {
                return response('', 200, [
                    'Access-Control-Allow-Origin' => $origin,
                    'Vary' => 'Origin',
                    'Access-Control-Allow-Methods' => $allowMethods,
                    'Access-Control-Allow-Credentials' => 'true',
                    'Access-Control-Max-Age' => $allowMaxAge,
                    'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers') ?: $allowHeaders,
                ]);
            }
            // No Origin header, fall back to wildcard without credentials
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => $allowMethods,
                'Access-Control-Max-Age' => $allowMaxAge,
                'Access-Control-Allow-Headers' => $request->header('Access-Control-Request-Headers') ?: $allowHeaders,
            ]);
        }

        $response = $next($request);

        // Always prioritize specific origin over wildcard when credentials might be involved
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        } else {
            $response->headers->set('Access-Control-Allow-Origin', '*');
            // Don't set Access-Control-Allow-Credentials when using wildcard
        }

        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);
        $response->headers->set('Access-Control-Max-Age', $allowMaxAge);

        return $response;
    }
}
