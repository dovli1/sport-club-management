<?php

// ========== app/Models/Attendance.php ==========
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'training_session_id',
        'player_id',
        'status',
        'performance_score',
        'remarks',
    ];

    public function trainingSession()
    {
        return $this->belongsTo(TrainingSession::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}