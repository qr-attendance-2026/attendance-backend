<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
 
    protected $fillable = [
        'course_class_id', 'date', 'check_number', 'qr_expires_at', 'qr_payload',
    ];
 
    protected $casts = [
        'qr_expires_at' => 'datetime',
        'date'          => 'date',
    ];

    //RELATIONSHIPS
    public function courseClass()
    {
        return $this->belongsTo(CourseClass::class, 'course_class_id');
    }
 
    public function records()
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    //Kiểm tra QR còn hiệu lực ?
    public function isActive(): bool
    {
        return $this->qr_expires_at && now()->lte($this->qr_expires_at);
    }

}
