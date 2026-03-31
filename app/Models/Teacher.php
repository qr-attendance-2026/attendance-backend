<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'teacher_code', 'department'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function courseClasses()
    {
        return $this->hasMany(CourseClass::class);
    }


}
