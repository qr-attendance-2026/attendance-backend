<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    public $timestamps = false;
 
    protected $fillable = [
        'session_id', 'student_id', 'status', 'method', 'checked_at', 'is_makeup',
    ];
 
    protected $casts = [
        'checked_at' => 'datetime',
        'is_makeup'  => 'boolean',
    ];
 

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

}

