<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Player;
use App\Models\TrainingSession;
use App\Models\Attendance;
use App\Models\Match;
use App\Models\Matchs;

class DashboardController extends Controller
{
    /**
     * Get global statistics for admin dashboard
     */
    public function getGlobalStats()
    {
        try {
            $totalPlayers = Player::count();
            $totalTrainings = TrainingSession::count();
            $totalMatches = Matchs::count();
            
            // Calculate average attendance rate
            $attendanceData = Attendance::selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as presents
            ')->first();
            
            $attendanceRate = $attendanceData->total > 0 
                ? round(($attendanceData->presents / $attendanceData->total) * 100, 1)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'totalPlayers' => $totalPlayers,
                    'totalTrainings' => $totalTrainings,
                    'totalMatches' => $totalMatches,
                    'attendanceRate' => $attendanceRate
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'data' => [
                    'totalPlayers' => 0,
                    'totalTrainings' => 0,
                    'totalMatches' => 0,
                    'attendanceRate' => 0
                ]
            ], 500);
        }
    }

    /**
     * Get players by team for bar chart
     */
    public function getPerformanceReport()
    {
        try {
            $playersByTeam = Player::select('team', DB::raw('COUNT(*) as count'))
                ->whereNotNull('team')
                ->groupBy('team')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => $item->team,
                        'joueurs' => $item->count
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $playersByTeam
            ]);

        } catch (\Exception $e) {
            \Log::error('Performance report error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get attendance evolution for line chart
     */
    public function getAttendanceReport()
    {
        try {
            // Get last 6 months data
            $attendanceData = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthName = $date->locale('fr')->shortMonthName;
                
                // Simulate some data for demo
                $attendanceData[] = [
                    'mois' => $monthName,
                    'taux' => rand(75, 95) // Random data for demo
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $attendanceData
            ]);

        } catch (\Exception $e) {
            \Log::error('Attendance report error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    ['mois' => 'Jan', 'taux' => 0],
                    ['mois' => 'Fév', 'taux' => 0],
                    ['mois' => 'Mar', 'taux' => 0],
                    ['mois' => 'Avr', 'taux' => 0],
                    ['mois' => 'Mai', 'taux' => 0],
                    ['mois' => 'Juin', 'taux' => 0]
                ]
            ]);
        }
    }

    /**
     * Get roles distribution for pie chart
     */
    public function getMatchStats()
    {
        try {
            $rolesDistribution = User::select('role', DB::raw('COUNT(*) as count'))
                ->groupBy('role')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => $this->formatRoleName($item->role),
                        'value' => $item->count
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $rolesDistribution
            ]);

        } catch (\Exception $e) {
            \Log::error('Match stats error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [
                    ['name' => 'Joueurs', 'value' => 0],
                    ['name' => 'Coaches', 'value' => 0],
                    ['name' => 'Admins', 'value' => 0]
                ]
            ]);
        }
    }

    /**
     * Get top players
     */
    public function getTopPlayers()
    {
        try {
            $topPlayers = Attendance::whereNotNull('performance_score')
                ->select('player_id', DB::raw('AVG(performance_score) as avg_score'))
                ->with(['player.user'])
                ->groupBy('player_id')
                ->orderBy('avg_score', 'desc')
                ->limit(5)
                ->get()
                ->map(function($attendance) {
                    return [
                        'id' => $attendance->player_id,
                        'name' => $attendance->player->user->name ?? 'Inconnu',
                        'team' => $attendance->player->team ?? 'Non assigné',
                        'performance' => round($attendance->avg_score, 1)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $topPlayers
            ]);

        } catch (\Exception $e) {
            \Log::error('Top players error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Format role names for display
     */
    private function formatRoleName($role)
    {
        $roles = [
            'admin' => 'Admins',
            'coach' => 'Coaches', 
            'player' => 'Joueurs'
        ];
        
        return $roles[$role] ?? ucfirst($role);
    }

    /**
     * Get coach statistics
     */
    public function getCoachStats()
    {
        try {
            $coach = auth()->user();
            $team = $coach->team;

            if (!$team) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coach non assigné à une équipe'
                ], 400);
            }

            $myPlayers = Player::where('team', $team)->count();
            $recentTrainings = TrainingSession::where('coach_id', $coach->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->count();

            // Performance moyenne
            $avgPerformance = Attendance::whereHas('player', function($query) use ($team) {
                    $query->where('team', $team);
                })
                ->whereNotNull('performance_score')
                ->avg('performance_score') ?? 0;

            // Taux de présence
            $attendanceData = Attendance::whereHas('player', function($query) use ($team) {
                    $query->where('team', $team);
                })
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as presents
                ')->first();

            $attendanceRate = $attendanceData->total > 0 
                ? round(($attendanceData->presents / $attendanceData->total) * 100, 1)
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'myPlayers' => $myPlayers,
                    'recentTrainings' => $recentTrainings,
                    'avgPerformance' => round($avgPerformance, 1),
                    'attendanceRate' => $attendanceRate
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Coach stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur'
            ], 500);
        }
    }

    /**
     * Get player statistics
     */
    public function getPlayerStats()
    {
        try {
            $user = auth()->user();
            $player = Player::where('user_id', $user->id)->first();

            if (!$player) {
                return response()->json([
                    'success' => false,
                    'message' => 'Joueur non trouvé'
                ], 404);
            }

            $trainingsAttended = Attendance::where('player_id', $player->id)
                ->where('status', 'present')
                ->count();

            $totalTrainings = Attendance::where('player_id', $player->id)->count();
            $attendanceRate = $totalTrainings > 0 
                ? round(($trainingsAttended / $totalTrainings) * 100, 1)
                : 0;

            $avgPerformance = Attendance::where('player_id', $player->id)
                ->whereNotNull('performance_score')
                ->avg('performance_score') ?? 0;

            $upcomingMatches = Matchs::where('status', 'scheduled')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'trainingsAttended' => $trainingsAttended,
                    'attendanceRate' => $attendanceRate,
                    'avgPerformance' => round($avgPerformance, 1),
                    'upcomingMatches' => $upcomingMatches
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Player stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur'
            ], 500);
        }
    }
}