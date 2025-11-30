<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\TrainingSession;
use App\Models\Attendance;
use App\Models\Matchs;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    // ✅ STATS POUR ADMIN
    public function getAdminStats(Request $request)
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
            ],

            'users' => [
                'total' => User::count(),
                'admins' => User::where('role', 'admin')->count(),
                'coaches' => User::where('role', 'coach')->count(),
                'players' => User::where('role', 'player')->count(),
            ],

            'teams' => $this->getTeamsStats(),
        ];

        return response()->json($stats);
    }

    // ✅ STATS POUR COACH
    public function getCoachStats(Request $request)
    {
        $coach = Auth::user();
        
        if ($coach->role !== 'coach' || !$coach->team) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // Joueurs de son équipe uniquement
        $teamPlayers = Player::where('team', $coach->team)->get();
        $playerIds = $teamPlayers->pluck('id');

        // Entraînements du coach
        $trainings = TrainingSession::where('coach_id', $coach->id)->get();
        $trainingIds = $trainings->pluck('id');

        $stats = [
            'team' => $coach->team,
            
            'players' => [
                'total' => $teamPlayers->count(),
                'active' => $teamPlayers->where('status', 'active')->count(),
                'injured' => $teamPlayers->where('status', 'injured')->count(),
                'suspended' => $teamPlayers->where('status', 'suspended')->count(),
            ],

            'trainings' => [
                'total' => $trainings->count(),
                'scheduled' => $trainings->where('status', 'scheduled')->count(),
                'completed' => $trainings->where('status', 'completed')->count(),
                'cancelled' => $trainings->where('status', 'cancelled')->count(),
            ],

            'attendance' => [
                'overall_rate' => $this->getTeamAttendanceRate($playerIds, $trainingIds),
            ],

            'performance' => [
                'average' => $this->getTeamAveragePerformance($playerIds, $trainingIds),
            ],

            'top_players' => $this->getTopPlayersByTeam($coach->team, 5),
        ];

        return response()->json($stats);
    }

    // ✅ STATS POUR PLAYER
    public function getPlayerStats(Request $request)
    {
        $user = Auth::user();
        
        if ($user->role !== 'player' || !$user->player) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $player = $user->player;

        $stats = [
            'player' => [
                'id' => $player->id,
                'name' => $player->full_name,
                'team' => $player->team,
                'position' => $player->position,
                'jersey_number' => $player->jersey_number,
            ],

            'trainings_attended' => Attendance::where('player_id', $player->id)
                ->whereIn('status', ['present', 'late'])
                ->count(),

            'attendance_rate' => $player->getAttendanceRate(),

            'average_performance' => $player->getAveragePerformance(),

            'recent_performances' => $this->getRecentPerformances($player->id, 10),

            'upcoming_trainings' => $this->getUpcomingTrainings($player->team, 5),

            'upcoming_matches' => $this->getUpcomingMatches(3),
        ];

        return response()->json($stats);
    }

    // ========== HELPER METHODS ==========

    private function getOverallAttendanceRate()
    {
        $total = Attendance::count();
        if ($total === 0) return 0;

        $present = Attendance::whereIn('status', ['present', 'late'])->count();
        return round(($present / $total) * 100, 2);
    }

    private function getTeamAttendanceRate($playerIds, $trainingIds)
    {
        $total = Attendance::whereIn('player_id', $playerIds)
            ->whereIn('training_session_id', $trainingIds)
            ->count();
            
        if ($total === 0) return 0;

        $present = Attendance::whereIn('player_id', $playerIds)
            ->whereIn('training_session_id', $trainingIds)
            ->whereIn('status', ['present', 'late'])
            ->count();

        return round(($present / $total) * 100, 2);
    }

    private function getTeamAveragePerformance($playerIds, $trainingIds)
    {
        $avg = Attendance::whereIn('player_id', $playerIds)
            ->whereIn('training_session_id', $trainingIds)
            ->whereNotNull('performance_score')
            ->avg('performance_score');

        return $avg ? round($avg, 2) : 0;
    }

    private function getTeamsStats()
    {
        $teams = ['U18 Masculin', 'Seniors Féminin', 'Seniors Masculin', 'U18 Féminin', 'U15 Masculin', 'U15 Féminin'];
        $teamsData = [];

        foreach ($teams as $team) {
            $teamsData[] = [
                'name' => $team,
                'players' => Player::where('team', $team)->count(),
                'coach' => User::where('role', 'coach')->where('team', $team)->first()?->name ?? 'Non assigné',
            ];
        }

        return $teamsData;
    }

    private function getTopPlayersByTeam($team, $limit)
    {
        return Player::where('team', $team)
            ->where('status', 'active')
            ->with(['attendances' => function($query) {
                $query->whereNotNull('performance_score');
            }])
            ->get()
            ->map(function($player) {
                return [
                    'id' => $player->id,
                    'name' => $player->full_name,
                    'position' => $player->position,
                    'jersey_number' => $player->jersey_number,
                    'attendance_rate' => $player->getAttendanceRate(),
                    'average_performance' => $player->getAveragePerformance(),
                ];
            })
            ->sortByDesc('average_performance')
            ->take($limit)
            ->values();
    }

    private function getRecentPerformances($playerId, $limit)
    {
        return Attendance::where('player_id', $playerId)
            ->whereNotNull('performance_score')
            ->with('trainingSession')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($attendance) {
                return [
                    'date' => $attendance->trainingSession->date->format('d/m/Y'),
                    'score' => $attendance->performance_score,
                    'training' => $attendance->trainingSession->title,
                ];
            });
    }

    private function getUpcomingTrainings($team, $limit)
    {
        return TrainingSession::where('status', 'scheduled')
            ->whereHas('coach', function($query) use ($team) {
                $query->where('team', $team);
            })
            ->where('date', '>=', now())
            ->orderBy('date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function($training) {
                return [
                    'id' => $training->id,
                    'title' => $training->title,
                    'date' => $training->date->format('Y-m-d'),
                    'time' => $training->start_time,
                    'location' => $training->location,
                ];
            });
    }

    private function getUpcomingMatches($limit)
    {
        return Matchs::where('status', 'scheduled')
            ->where('match_date', '>=', now())
            ->orderBy('match_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function($match) {
                return [
                    'id' => $match->id,
                    'opponent_team' => $match->opponent_team,
                    'date' => $match->match_date->format('Y-m-d'),
                    'time' => $match->match_time,
                    'location' => $match->location,
                ];
            });
    }
}