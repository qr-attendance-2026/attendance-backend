<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    public $timestamps = false;


   protected $fillable = [
    'student_code',
    'time'
];


    public function student()
    {
        return $this->belongsTo(Student::class);
    }

}

