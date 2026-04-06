<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'subject_code',
        'subject_name',
    ];

    public function courseClasses()
    {
        return $this->hasMany(CourseClass::class);
    }
}   