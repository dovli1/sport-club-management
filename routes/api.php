<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\TrainingSessionController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CoachController;

// Public routes
Route::post('login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // ✅ DASHBOARD ROUTES
    Route::prefix('dashboard')->group(function () {
        // Admin Dashboard
        Route::get('admin/stats', [DashboardController::class, 'getAdminStats'])
            ->middleware('role:admin');
        
        // Coach Dashboard
        Route::get('coach/stats', [DashboardController::class, 'getCoachStats'])
            ->middleware('role:coach');
        
        // Player Dashboard
        Route::get('player/stats', [DashboardController::class, 'getPlayerStats'])
            ->middleware('role:player');
    });

    // Players
    Route::get('players', [PlayerController::class, 'index']);
    Route::get('players/{id}', [PlayerController::class, 'show']);
    
    Route::middleware('role:admin,coach')->group(function () {
        Route::post('players', [PlayerController::class, 'store']);
        Route::put('players/{id}', [PlayerController::class, 'update']);
    });
    
    Route::middleware('role:admin')->group(function () {
        Route::delete('players/{id}', [PlayerController::class, 'destroy']);
    });

    // Training Sessions  ← ✅ MAINTENANT À L'INTÉRIEUR DU GROUP !
    Route::get('trainings', [TrainingSessionController::class, 'index']);
    Route::get('trainings/{id}', [TrainingSessionController::class, 'show']);

    Route::middleware('role:admin,coach')->group(function () {
        Route::post('trainings', [TrainingSessionController::class, 'store']);
        Route::put('trainings/{id}', [TrainingSessionController::class, 'update']);
        Route::delete('trainings/{id}', [TrainingSessionController::class, 'destroy']);
        Route::post('trainings/{id}/attendance', [TrainingSessionController::class, 'markAttendance']);
    });

    // Matches
    Route::get('matches', [MatchController::class, 'index']);
    Route::get('matches/{id}', [MatchController::class, 'show']);
    Route::get('matches/stats/summary', [MatchController::class, 'getStats']);

    Route::middleware('role:admin,coach')->group(function () {
        Route::post('matches', [MatchController::class, 'store']);
        Route::put('matches/{id}', [MatchController::class, 'update']);
        Route::delete('matches/{id}', [MatchController::class, 'destroy']);
        Route::post('matches/{id}/players', [MatchController::class, 'addPlayers']);
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/{id}', [NotificationController::class, 'show']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/unread/count', [NotificationController::class, 'getUnreadCount']);

    Route::middleware('role:admin,coach')->group(function () {
        Route::post('notifications', [NotificationController::class, 'store']);
        Route::put('notifications/{id}', [NotificationController::class, 'update']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    });

    // Coaches (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('coaches', [CoachController::class, 'index']);
        Route::get('coaches/{id}', [CoachController::class, 'show']);
        Route::post('coaches', [CoachController::class, 'store']);
        Route::put('coaches/{id}', [CoachController::class, 'update']);
        Route::delete('coaches/{id}', [CoachController::class, 'destroy']);
    });

}); // ← ✅ ACCOLADE FERMANTE CORRECTE POUR LE GROUPE PRINCIPAL

// Health check
Route::get('health', function() {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});