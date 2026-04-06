<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;


class AuthController 

{
    // ────────────────────────────────────────────────────────────────────
    // POST /api/auth/login
    // Public — no token required.
    // Returns a Sanctum token + full user object with role-specific profile.
    // ────────────────────────────────────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
 
        // Find the user
        $user = User::where('email', $request->email)->first();
 
        // Verify password
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }
 
        // Revoke all previous tokens for this user (single-session policy)
        // Remove this line if you want multiple devices logged in simultaneously
        $user->tokens()->delete();
        // Create a new Sanctum token named after the user's role
        $token = $user->createToken('auth_token_' . $user->role)->plainTextToken;
 
        // Build the profile payload based on role
        $profile = match ($user->role) {
            'student' => $user->student,
            'teacher' => $user->teacher,
            'admin'   => null,
            default   => null,
        };
 
        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'data'    => [
                'token' => $token,
                'user'  => [
                    'id'      => $user->id,
                    'name'    => $user->name,
                    'email'   => $user->email,
                    'role'    => $user->role,
                    'profile' => $profile,
                ],
            ],
        ], 200);
    }
 
    // ────────────────────────────────────────────────────────────────────
    // POST /api/auth/logout
    // Requires: auth:sanctum middleware.
    // Revokes only the current token (the one sent in Bearer header).
    // ────────────────────────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        // currentAccessToken() is the token decoded from the Bearer header
        $request->user()->currentAccessToken()->delete();
 
        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ], 200);
    }
 
    // ────────────────────────────────────────────────────────────────────
    // GET /api/auth/me
    // Returns the authenticated user + their role-specific profile.
    // Used by the frontend on app startup to restore state.
    // ────────────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
 
        $profile = match ($user->role) {            
            'student' => $user->student,
            'teacher' => $user->teacher,
            'admin'   => null,
            default   => null,
        };
 
        return response()->json([
            'success' => true,
            'data'    => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'role'    => $user->role,
                'profile' => $profile,
            ],
        ], 200);
    }
 
    // ────────────────────────────────────────────────────────────────────
    // POST /api/auth/change-password
    // Requires: auth:sanctum middleware.
    // All three roles can use this endpoint.
    // ────────────────────────────────────────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
            // 'new_password_confirmation' must also be sent by the client
        ]);
 
        $user = $request->user();
 
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng.'],
            ]);
        }
 
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
 
        // Revoke all tokens — force re-login after password change
        $user->tokens()->delete();
 
        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
        ], 200);
    }
}



