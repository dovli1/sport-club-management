<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\TrainingSession;
use App\Models\Attendance;
use App\Models\Matchs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        $stats = [
            'players' => [
                'total' => Player::count(),
                'active' => Player::where('status', 'active')->count(),
                'injured' => Player::where('status', 'injured')->count(),
                'suspended' => Player::where('status', 'suspended')->count(),
            ],
            
            'trainings' => [
                'total' => TrainingSession::count(),
                'scheduled' => TrainingSession::where('status', 'scheduled')->count(),
                'completed' => TrainingSession::where('status', 'completed')->count(),
                'cancelled' => TrainingSession::where('status', 'cancelled')->count(),
            ],
            
            'matches' => [
                'total' => Matchs::count(),
                'scheduled' => Matchs::where('status', 'scheduled')->count(),
                'completed' => Matchs::where('status', 'completed')->count(),
                'wins' => Matchs::where('result', 'win')->count(),
                'losses' => Matchs::where('result', 'loss')->count(),
                'draws' => Matchs::where('result', 'draw')->count(),
            ],
            
            'attendance' => [
                'overall_rate' => $this->getOverallAttendanceRate(),
                'present_today' => $this->getTodayAttendance(),
            ],
            
            'users' => [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'coaches' => User::where('role', 'coach')->count(),
                'players' => User::where('role', 'player')->count(),
            ]
        ];

        return response()->json($stats);
    }

    public function getAttendanceReport(Request $request)
    {
        $query = Attendance::with(['player', 'trainingSession']);

        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->whereHas('trainingSession', function($q) use ($request) {
                $q->whereBetween('date', [$request->from_date, $request->to_date]);
            });
        }

        $attendances = $query->get();

        // Group by player
        $report = [];
        foreach ($attendances->groupBy('player_id') as $playerId => $playerAttendances) {
            $player = $playerAttendances->first()->player;
            $total = $playerAttendances->count();
            $present = $playerAttendances->whereIn('status', ['present', 'late'])->count();
            
            $report[] = [
                'player_id' => $playerId,
                'player_name' => $player->full_name,
                'total_sessions' => $total,
                'present' => $present,
                'absent' => $total - $present,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 2) : 0,
                'average_performance' => $playerAttendances->whereNotNull('performance_score')
                                                           ->avg('performance_score')
            ];
        }

        return response()->json($report);
    }

    public function getPerformanceReport(Request $request)
    {
        $players = Player::with(['attendances' => function($query) use ($request) {
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereHas('trainingSession', function($q) use ($request) {
                    $q->whereBetween('date', [$request->from_date, $request->to_date]);
                });
            }
            $query->whereNotNull('performance_score');
        }])->get();

        $report = $players->map(function($player) {
            $performances = $player->attendances->pluck('performance_score');
            
            return [
                'player_id' => $player->id,
                'player_name' => $player->full_name,
                'position' => $player->position,
                'total_evaluations' => $performances->count(),
                'average_performance' => $performances->avg(),
                'min_performance' => $performances->min(),
                'max_performance' => $performances->max(),
                'trend' => $this->calculateTrend($performances),
            ];
        })->filter(function($item) {
            return $item['total_evaluations'] > 0;
        })->sortByDesc('average_performance')->values();

        return response()->json($report);
    }

    public function getMatchStats(Request $request)
    {
        $stats = [
            'total_matches' => Matchs::count(),
            'wins' => Matchs::where('result', 'win')->count(),
            'losses' => Matchs::where('result', 'loss')->count(),
            'draws' => Matchs::where('result', 'draw')->count(),
            'goals_scored' => Matchs::sum('our_score'),
            'goals_conceded' => Matchs::sum('opponent_score'),
            'win_rate' => $this->calculateWinRate(),
            'recent_form' => $this->getRecentForm(5),
        ];

        return response()->json($stats);
    }

    public function getTopPlayers(Request $request)
    {
        $limit = $request->get('limit', 10);

        $topByAttendance = Player::withCount(['attendances' => function($query) {
            $query->whereIn('status', ['present', 'late']);
        }])->orderBy('attendances_count', 'desc')->limit($limit)->get();

        $topByPerformance = Player::with(['attendances' => function($query) {
            $query->whereNotNull('performance_score');
        }])->get()->map(function($player) {
            return [
                'player' => $player,
                'average_performance' => $player->attendances->avg('performance_score')
            ];
        })->filter(function($item) {
            return $item['average_performance'] > 0;
        })->sortByDesc('average_performance')->take($limit)->values();

        return response()->json([
            'top_by_attendance' => $topByAttendance,
            'top_by_performance' => $topByPerformance,
        ]);
    }

    // Helper methods
    private function getOverallAttendanceRate()
    {
        $total = Attendance::count();
        if ($total === 0) return 0;
        
        $present = Attendance::whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 2);
    }

    private function getTodayAttendance()
    {
        return Attendance::whereHas('trainingSession', function($query) {
            $query->whereDate('date', today());
        })->whereIn('status', ['present', 'late'])->count();
    }

    private function calculateWinRate()
    {
        $total = Matchs::where('status', 'completed')->count();
        if ($total === 0) return 0;
        
        $wins = Matchs::where('result', 'win')->count();
        return round(($wins / $total) * 100, 2);
    }

    private function getRecentForm($count)
    {
        return Matchs::where('status', 'completed')
                    ->orderBy('match_date', 'desc')
                    ->limit($count)
                    ->get()
                    ->pluck('result')
                    ->map(function($result) {
                        return strtoupper(substr($result, 0, 1));
                    });
    }

    private function calculateTrend($performances)
    {
        if ($performances->count() < 2) return 'stable';
        
        $first = $performances->take(ceil($performances->count() / 2))->avg();
        $second = $performances->skip(ceil($performances->count() / 2))->avg();
        
        if ($second > $first + 1) return 'improving';
        if ($second < $first - 1) return 'declining';
        return 'stable';
    }
}