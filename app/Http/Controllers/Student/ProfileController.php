<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\CourseClass;

class ProfileController extends Controller
{
    /**
     * 1. GET /student/profile
     * Hiển thị thông tin chi tiết sinh viên
     */
    public function show(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if ($user) {
            // Load quan hệ để lấy thông tin lớp học của sinh viên
            $user->load(['student.courseClasses']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        }

        return view('student.profile.show', compact('user'));
    }

    /**
     * 2. PUT /student/profile
     * Cập nhật thông tin cá nhân (Số điện thoại, địa chỉ, mật khẩu...)
     */
    public function update(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        /** @var \App\Models\Student $student */
        $student = $user->student;

        $request->validate([
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Cập nhật thông tin vào bảng students
        $student->update([
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        // Nếu người dùng có nhập mật khẩu mới thì cập nhật bảng users
        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công!',
            'data' => $student
        ]);
    }

    /**
     * 3. GET /student/qr-code
     * Hiển thị mã QR cá nhân dựa trên MSSV
     */
    public function qrCode()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $student = $user->student;
        
        // Dữ liệu mã hóa vào QR cá nhân (thường dùng student_code)
        $qrData = $student->student_code; 

        return view('student.profile.qr', compact('student', 'qrData'));
    }

    public function getCourses(Request $request)
    {
        // Lấy thông tin sinh viên đang đăng nhập cùng các lớp học của họ
        $student = $request->user()->student->load('courseClasses');

        return response()->json([
            'success' => true,
            'data' => $student->courseClasses
        ]);
    }
}