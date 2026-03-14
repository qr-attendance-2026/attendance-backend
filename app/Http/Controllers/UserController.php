<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
class UserController extends Controller
{
    //
    public function index(){
        return User::all();
    }

    public function show($id)
    {
        return User::find($id);
    }

    public function store(Request $request)
    {
        $user = User::create($request->all());
        // return response()->json($user, 201);
        return $user;
    }

    public function update(Request $request,$id)
    {
        $user = User::find($id);
        $user->update($request->all());
        return $user;
    }

    public function destroy($id)
    {
        User::destroy($id);
        return ["message" => "deleted"];
    }
}
