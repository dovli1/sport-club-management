<?php
// app/Http/Controllers/MatchController.php

namespace App\Http\Controllers;

use App\Models\Matchs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Matchs::with('players');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by result
        if ($request->has('result')) {
            $query->where('result', $request->result);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('match_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('match_date', '<=', $request->to_date);
        }

        $matches = $query->orderBy('match_date', 'desc')->get();

        return response()->json($matches);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'opponent_team' => 'required|string',
            'match_date' => 'required|date',
            'match_time' => 'required',
            'location' => 'required|string',
            'match_type' => 'required|in:friendly,league,cup,tournament',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $match = Matchs::create($request->all());

        return response()->json([
            'message' => 'Match created successfully',
            'match' => $match
        ], 201);
    }

    public function show($id)
    {
        $match = Matchs::with('players')->findOrFail($id);
        return response()->json($match);
    }

    public function update(Request $request, $id)
    {
        $match = Matchs::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'opponent_team' => 'sometimes|string',
            'match_date' => 'sometimes|date',
            'match_time' => 'sometimes',
            'location' => 'sometimes|string',
            'match_type' => 'sometimes|in:friendly,league,cup,tournament',
            'our_score' => 'nullable|integer|min:0',
            'opponent_score' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:scheduled,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $match->update($request->all());

        // Auto-calculate result
        if ($request->has('our_score') && $request->has('opponent_score')) {
            $match->result = $match->calculateResult();
            $match->save();
        }

        return response()->json([
            'message' => 'Match updated successfully',
            'match' => $match
        ]);
    }

    public function destroy($id)
    {
        $match = Matchs::findOrFail($id);
        $match->delete();

        return response()->json([
            'message' => 'Match deleted successfully'
        ]);
    }

    public function addPlayers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'players' => 'required|array',
            'players.*.player_id' => 'required|exists:players,id',
            'players.*.is_starter' => 'boolean',
            'players.*.minutes_played' => 'integer|min:0',
            'players.*.goals' => 'integer|min:0',
            'players.*.assists' => 'integer|min:0',
            'players.*.yellow_cards' => 'integer|min:0',
            'players.*.red_cards' => 'integer|min:0',
            'players.*.rating' => 'nullable|numeric|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $match = Matchs::findOrFail($id);

        foreach ($request->players as $playerData) {
            $match->players()->syncWithoutDetaching([
                $playerData['player_id'] => [
                    'is_starter' => $playerData['is_starter'] ?? false,
                    'minutes_played' => $playerData['minutes_played'] ?? 0,
                    'goals' => $playerData['goals'] ?? 0,
                    'assists' => $playerData['assists'] ?? 0,
                    'yellow_cards' => $playerData['yellow_cards'] ?? 0,
                    'red_cards' => $playerData['red_cards'] ?? 0,
                    'rating' => $playerData['rating'] ?? null,
                ]
            ]);
        }

        $match->load('players');

        return response()->json([
            'message' => 'Players added to match successfully',
            'match' => $match
        ]);
    }

    public function getStats()
    {
        $stats = [
            'total_matches' => Matchs::count(),
            'completed_matches' => Matchs::where('status', 'completed')->count(),
            'wins' => Matchs::where('result', 'win')->count(),
            'losses' => Matchs::where('result', 'loss')->count(),
            'draws' => Matchs::where('result', 'draw')->count(),
            'total_goals_scored' => Matchs::whereNotNull('our_score')->sum('our_score'),
            'total_goals_conceded' => Matchs::whereNotNull('opponent_score')->sum('opponent_score'),
        ];

        return response()->json($stats);
    }
}