<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'date_of_birth',
        'position',
        'jersey_number',
        'photo',
        'cv_pdf',
        'address',
        'emergency_contact',
        'emergency_phone',
        'status',
        'team', // ✅ AJOUTÉ
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected $appends = ['full_name', 'age'];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function matches()
    {
        return $this->belongsToMany(Matchs::class, 'match_player')
            ->withPivot([
                'is_starter',
                'minutes_played',
                'goals',
                'assists',
                'yellow_cards',
                'red_cards',
                'rating'
            ])
            ->withTimestamps();
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute()
    {
        return $this->date_of_birth ? $this->date_of_birth->age : null;
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