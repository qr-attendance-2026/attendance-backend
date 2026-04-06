<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
class UserController extends Controller
{
    //List users
    public function index(){

        // $users = User::orderBy('id', 'desc')->get();
        // return response()->json($users, 200);

        $users = User::with(['student', 'teacher'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // public function show(int $id)
    // {
    //     $user = User::findOrFail($id);
    //     return response()->json($user, 200);
    // }
    public function show(int $id): JsonResponse
    {
        $user = User::with(['student', 'teacher'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $user = User::create($validatedData);

        return response()->json([
            'message' => 'Thêm người dùng thành công!',
            'data' => $user
        ], 201);
    }

     public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'  => ['sometimes', 'string'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $id],
            'role'  => ['sometimes', 'in:admin,teacher,student'],
        ]);

        $user->update($request->only(['name', 'email', 'role']));

        return response()->json(['success' => true, 'data' => $user]);
    }

   public function destroy(int $id): JsonResponse
    {
        User::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'User deleted.']);
    }

     // PATCH /api/admin/users/{id}/toggle-active
    // Note: add an 'is_active' boolean column to users migration if needed
    public function toggleActive(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['success' => true, 'data' => $user]);
    }

    // POST /api/admin/users/{id}/reset-password
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->delete(); // force re-login

        return response()->json(['success' => true, 'message' => 'Password reset. User must log in again.']);
    }
}
