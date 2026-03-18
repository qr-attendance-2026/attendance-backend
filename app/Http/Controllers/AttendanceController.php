<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attendance;

class AttendanceController extends Controller
{

    public function index()
    {
        return view('welcome');
    }

    public function store(Request $request)
    {

        Attendance::create([
            'student_code'=>$request->student_code,
            'time'=>now()
        ]);

        return response()->json([
            'message'=>'Điểm danh thành công'
        ]);
    }

    public function list()
    {
        $data=Attendance::all();

        return view('list',compact('data'));
    }

}