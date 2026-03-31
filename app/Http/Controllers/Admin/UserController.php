<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
class UserController extends Controller
{
    //
    public function index(){

        $users = User::orderBy('id', 'desc')->get();
        return response()->json($users, 200);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user, 200);
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

    public function update(Request $request,$id)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $user = User::findOrFail($id);
        $user->update($validatedData);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công!',
            'data' => $user
        ], 200);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Đã xóa người dùng thành công!'
        ], 200);
    }
}
