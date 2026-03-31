<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public $timestamps = false;

    // Cập nhật lại fillable để nhận được ID lớp và Trạng thái
    protected $fillable = [
        'student_id',       // Dùng ID để liên kết cho chuẩn
        'course_class_id',  // Điểm danh cho lớp nào?
        'check_in_time',    // Thời gian quét mã
        'status'            // Có mặt (present), Muộn (late)...
    ];

    // Quan hệ với Sinh viên
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Quan hệ với Lớp học (Rất quan trọng để biết điểm danh môn nào)
    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class);
    }
}