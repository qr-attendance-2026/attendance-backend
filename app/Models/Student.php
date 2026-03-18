<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = ['student_code','name'];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
