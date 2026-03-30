<?php

namespace App\Http\Controllers\Teacher;

use Illuminate\Http\Request;
use App\Models\Attendance;

class AttendanceController
{

    public function index()
    {
        return view('welcome');
    }

    public function store(Request $request)
{
    $student_code = $request->student_code;
    $today = date('Y-m-d');

    // kiểm tra đã điểm danh hôm nay chưa
    $exists = Attendance::where('student_code', $student_code)
                ->whereDate('time', $today)
                ->exists();

    if ($exists) {
        return response()->json([ 
            'status' => 'error', // đổi status
            'message' => 'Điểm danh thành công'
        ]);
    }

    // nếu chưa thì lưu
    Attendance::create([
        'student_code' => $student_code,
        'time' => now()
    ]);

    return response()->json([
        'status' => 'success',
        'message' => 'Điểm danh thành công'
    ]);
}
    public function list()
    {
        $data=Attendance::all();

        return view('list',compact('data'));
    }

}