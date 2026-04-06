<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseClass extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
 
    protected $fillable = [
        'subject_id', 'teacher_id', 'class_code', 'semester', 'academic_year',
    ];
 
    //RELATIONSHIPS
    public function subject()  { return $this->belongsTo(Subject::class); }
    public function teacher()  { return $this->belongsTo(Teacher::class); }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
 
    public function students()
    {
        return $this->belongsToMany(
            Student::class,
            'class_enrollments',
            'course_class_id',
            'student_id'
        );
    }

    public function sessions()
    {
        return $this->hasMany(AttendanceSession::class, 'course_class_id');
    }


    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }



}
