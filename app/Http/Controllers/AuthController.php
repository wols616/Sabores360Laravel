<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\PasswordReset;
use App\Services\JwtService;

class AuthController extends Controller
{
    // POST /api/auth/login
    public function login(Request $request)
    {
        $data = $request->only(['email', 'password']);
        if (empty($data['email']) || empty($data['password'])) {
            return response()->json(['success' => false, 'message' => 'invalid_credentials'], 400);
        }
        $user = User::where('email', $data['email'])->first();
        if (!$user)
            return response()->json(['success' => false, 'message' => 'invalid_credentials'], 400);
        if (!password_verify($data['password'], $user->password_hash)) {
            return response()->json(['success' => false, 'message' => 'invalid_credentials'], 400);
        }
        // Prefer JWT_SECRET if provided so tokens match other services (Spring). Fallback to APP_KEY.
        $secret = env('JWT_SECRET', env('APP_KEY'));
        // Build payload similar to the Java service: sub is the user's email (string), include userId and email,
        // and normalize role to lowercase (e.g. 'cliente') so clients see the same values.
        $token = JwtService::generateToken([
            'sub' => $user->email,          // match Spring which uses email as 'sub'
            'userId' => $user->id,
            'email' => $user->email,
            'role' => strtolower($user->role?->name ?? 'cliente')
        ], $secret, 60 * 60 * 24);
        // Return token in body and also (attempt) set HttpOnly cookie for dev convenience.
        // SameSite=None + Secure normally required for cross-site; in local http you may need to drop SameSite or Accept that cookie is blocked.
        $json = [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $this->mapRoleName($user->role?->name ?? null),
                'address' => $user->address ?? null
            ]
        ];

        $response = response()->json($json);
        try {
            // SameSite=Lax suele funcionar entre http://localhost:8888 y http://127.0.0.1:8000 por ser same-site en Chrome.
            $cookie = cookie(
                name: 'token',
                value: $token,
                minutes: 60 * 24,
                path: '/',
                domain: null,
                secure: false, // ponlo true si sirves via https
                httpOnly: true,
                raw: false,
                sameSite: 'Lax'
            );
            $response->headers->setCookie($cookie);
            // Also set a non-HttpOnly cookie named 'auth_token' for older frontends / dev tooling
            // so client-side scripts can read it (local dev only). In production prefer Authorization header or HttpOnly cookies.
            $publicCookie = cookie(
                name: 'auth_token',
                value: $token,
                minutes: 60 * 24,
                path: '/',
                domain: null,
                secure: false,
                httpOnly: false,
                raw: false,
                sameSite: 'Lax'
            );
            $response->headers->setCookie($publicCookie);
        } catch (\Throwable $e) {
            // Ignore cookie errors
        }
        return $response;
    }

    // GET /api/auth/me
    public function me(Request $request)
    {
        // Try to authenticate from Authorization header, query param or cookies.
        // This endpoint is intentionally permissive: if no valid token is present, return user:null (like the Java API).
        $auth = $request->header('Authorization') ?: $request->get('token');
        if (!$auth) {
            if ($request->cookies->has('token')) {
                $auth = $request->cookies->get('token');
            } elseif ($request->cookies->has('auth_token')) {
                $auth = $request->cookies->get('auth_token');
            } elseif ($request->cookies->has('WMF_Uniq')) {
                $auth = $request->cookies->get('WMF_Uniq');
            }
        }
        $user = null;
        if ($auth) {
            if (stripos($auth, 'Bearer ') === 0) {
                $token = substr($auth, 7);
            } else {
                $token = $auth;
            }
            $secret = env('JWT_SECRET', env('APP_KEY'));
            $payload = JwtService::validateToken($token, $secret);
            if ($payload) {
                // Determine user from payload (sub may be email or id)
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
            }
        }

        if (!$user)
            return response()->json(['success' => true, 'user' => null]);

        // Normalize role to expected API values: admin | seller | client
        $rawRole = $user->role?->name ?? null;
        $role = $this->mapRoleName($rawRole);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'address' => $user->address ?? null
            ]
        ]);
    }

    // POST /api/auth/register
    public function register(Request $request)
    {
        $data = $request->only(['name', 'email', 'password', 'address']);
        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            return response()->json(['success' => false, 'message' => 'invalid_input'], 400);
        }
        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['success' => false, 'message' => 'email_exists'], 400);
        }
        // bcrypt cost 10 as requested
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $user = User::create(['role_id' => 3, 'name' => $data['name'], 'email' => $data['email'], 'password_hash' => $hash, 'address' => $data['address'] ?? null]);
        return response()->json(['success' => true, 'userId' => $user->id]);
    }

    // POST /api/auth/forgot-password
    public function forgotPassword(Request $request)
    {
        $email = $request->input('email');
        if (!$email)
            return response()->json(['success' => false], 400);
        $token = bin2hex(random_bytes(32));
        PasswordReset::updateOrCreate(['email' => $email], ['token' => $token, 'created_at' => date('Y-m-d H:i:s')]);
        // In real app send email. Here return success and token for testing.
        return response()->json(['success' => true, 'token' => $token]);
    }

    // POST /api/auth/reset-password
    public function resetPassword(Request $request)
    {
        $token = $request->input('token');
        $password = $request->input('password');
        if (!$token || !$password)
            return response()->json(['success' => false], 400);
        $pr = PasswordReset::where('token', $token)->first();
        if (!$pr)
            return response()->json(['success' => false, 'message' => 'invalid_token'], 400);
        $user = User::where('email', $pr->email)->first();
        if (!$user)
            return response()->json(['success' => false, 'message' => 'user_not_found'], 400);
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $user->password_hash = $hash;
        $user->save();
        // delete token
        $pr->delete();
        return response()->json(['success' => true]);
    }

    // POST /api/auth/change-password (authenticated)
    public function changePassword(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $current = $request->input('currentPassword');
        $new = $request->input('newPassword');
        if (!$user)
            return response()->json(['success' => false], 401);
        if (!password_verify($current, $user->password_hash))
            return response()->json(['success' => false, 'message' => 'invalid_current_password'], 400);
        $user->password_hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 10]);
        $user->save();
        return response()->json(['success' => true]);
    }

    // PUT /api/auth/profile
    public function updateProfile(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!$user)
            return response()->json(['success' => false], 401);
        $data = $request->only(['name', 'email', 'address']);
        if (isset($data['email']) && $data['email'] !== $user->email && User::where('email', $data['email'])->exists()) {
            return response()->json(['success' => false, 'message' => 'email_exists'], 400);
        }
        foreach ($data as $k => $v)
            if ($v !== null)
                $user->$k = $v;
        $user->save();
        return response()->json(['success' => true]);
    }

    // GET/POST /api/auth/logout
    public function logout(Request $request)
    {
        // stateless JWT: nothing to do
        return response()->json(['success' => true]);
    }

    // GET /api/debug/auth
    // No auth required. Returns the headers, cookies and what token (if any) the server can find and validate.
    public function debugAuth(Request $request)
    {
        $headers = [];
        foreach ($request->headers->all() as $k => $v) {
            $headers[$k] = is_array($v) ? implode(', ', $v) : $v;
        }
        $cookies = [];
        foreach ($request->cookies->all() as $k => $v)
            $cookies[$k] = $v;

        $auth = $request->header('Authorization') ?: $request->get('token');
        if (!$auth) {
            if ($request->cookies->has('token'))
                $auth = $request->cookies->get('token');
            elseif ($request->cookies->has('auth_token'))
                $auth = $request->cookies->get('auth_token');
            elseif ($request->cookies->has('WMF_Uniq'))
                $auth = $request->cookies->get('WMF_Uniq');
        }

        $token = null;
        $payload = null;
        $secret = env('JWT_SECRET', env('APP_KEY'));
        if ($auth) {
            if (stripos($auth, 'Bearer ') === 0)
                $token = substr($auth, 7);
            else
                $token = $auth;
            try {
                $payload = \App\Services\JwtService::validateToken($token, $secret);
            } catch (\Throwable $e) {
                $payload = ['error' => 'validation_error', 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'headers' => $headers, 'cookies' => $cookies, 'detected_auth_raw' => $auth, 'token' => $token, 'payload' => $payload]);
    }

    /**
     * Map various role names from DB to canonical API roles: admin | seller | client
     */
    private function mapRoleName($raw)
    {
        if (!$raw)
            return null;
        $r = strtolower(trim($raw));
        // common spanish/english role names
        $admins = ['admin', 'administrator', 'administrador', 'administradora'];
        $sellers = ['vendedor', 'vendedora', 'seller', 'vendor'];
        $clients = ['cliente', 'client', 'customer'];
        if (in_array($r, $admins))
            return 'admin';
        if (in_array($r, $sellers))
            return 'seller';
        if (in_array($r, $clients))
            return 'client';
        // fallback: substring matching
        if (strpos($r, 'admin') !== false)
            return 'admin';
        if (strpos($r, 'vend') !== false || strpos($r, 'sell') !== false)
            return 'seller';
        if (strpos($r, 'cli') !== false || strpos($r, 'cust') !== false)
            return 'client';
        return null;
    }
}
