<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Relations
    public function player()
    {
        return $this->hasOne(Player::class);
    }

    public function trainingSessions()
    {
        return $this->hasMany(TrainingSession::class, 'coach_id');
    }

    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
                    ->withPivot('read_at')
                    ->withTimestamps();
    }

    public function createdNotifications()
    {
        return $this->hasMany(Notification::class, 'created_by');
    }

    // Helper methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isCoach()
    {
        return $this->role === 'coach';
    }

    public function isPlayer()
    {
        return $this->role === 'player';
    }
}