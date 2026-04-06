<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    
    public $timestamps = false;

    protected $fillable = [
        'user_id', 
        'student_code',              
        'cohort_class',   
        'date_of_birth',     
        'gender',            
        'phone_number',             
        'face_id', 
        'qr_code_path',
    ];
    //RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courseClasses()
    {
        return $this->belongsToMany(
            CourseClass::class,
            'class_enrollments',
            'student_id',
            'course_class_id'
        );
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }



    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
