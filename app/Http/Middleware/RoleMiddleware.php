<?php

namespace App\Http\Middleware;

use Closure;

class RoleMiddleware
{
    protected $roleMap = [
        'admin' => 'Administrador',
        'seller' => 'Vendedor',
        'client' => 'Cliente',
    ];

    public function handle($request, Closure $next, $role)
    {
        $user = $request->attributes->get('auth_user');
        if (!$user)
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        $expected = $this->roleMap[$role] ?? $role;
        if (isset($user->role) && $user->role->name === $expected) {
            return $next($request);
        }
        return response()->json(['success' => false, 'message' => 'forbidden'], 403);
    }
}
