<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'course_class_id',
        'day_of_week',
        'start_period',
        'end_period',
        'room',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'day_of_week' => 'integer',
        'start_period' => 'integer',
        'end_period' => 'integer',
    ];

    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class);
    }
}