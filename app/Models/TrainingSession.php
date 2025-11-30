<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'coach_id',
        'title',
        'description',
        'date',
        'start_time',
        'end_time',
        'location',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Relations
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'attendances')
            ->withPivot(['status', 'performance_score', 'remarks'])
            ->withTimestamps();
    }

    // Helper methods
    public function getAttendanceRate()
    {
        $total = $this->attendances()->count();
        if ($total === 0) return 0;

        $present = $this->attendances()
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($present / $total) * 100, 2);
    }

    public function getAveragePerformance()
    {
        return $this->attendances()
            ->whereNotNull('performance_score')
            ->avg('performance_score');
    }
}