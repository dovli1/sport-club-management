<?php

// ========== app/Models/Match.php ==========
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matchs extends Model
{
    use HasFactory;

    protected $fillable = [
        'opponent_team',
        'match_date',
        'match_time',
        'location',
        'match_type',
        'our_score',
        'opponent_score',
        'result',
        'status',
        'notes',
    ];

    protected $casts = [
        'match_date' => 'date',
    ];

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_player')
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

    // Auto-calculate result
    public function calculateResult()
    {
        if ($this->our_score === null || $this->opponent_score === null) {
            return 'pending';
        }
        
        if ($this->our_score > $this->opponent_score) return 'win';
        if ($this->our_score < $this->opponent_score) return 'loss';
        return 'draw';
    }
}