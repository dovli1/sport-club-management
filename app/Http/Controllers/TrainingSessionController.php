<?php
// app/Http/Controllers/TrainingSessionController.php

namespace App\Http\Controllers;

use App\Models\TrainingSession;
use App\Models\Player;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TrainingSessionController extends Controller
{
    public function index(Request $request)
    {
        $query = TrainingSession::with(['coach', 'attendances.player']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('date', '<=', $request->to_date);
        }

        // For players: only their sessions
        if (auth()->user()->role === 'player') {
            $playerId = auth()->user()->player->id;
            $query->whereHas('attendances', function($q) use ($playerId) {
                $q->where('player_id', $playerId);
            });
        }

        $trainings = $query->orderBy('date', 'desc')->get();

        // Add stats
        $trainings->each(function($training) {
            $training->attendance_rate = $training->getAttendanceRate();
            $training->average_performance = $training->getAveragePerformance();
        });

        return response()->json($trainings);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'location' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $training = TrainingSession::create([
            'coach_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'location' => $request->location,
            'status' => 'scheduled',
        ]);

        // Auto-create attendances for all active players
        $players = Player::where('status', 'active')->get();
        foreach ($players as $player) {
            Attendance::create([
                'training_session_id' => $training->id,
                'player_id' => $player->id,
                'status' => 'absent',
            ]);
        }

        $training->load(['coach', 'attendances.player']);

        return response()->json([
            'message' => 'Training session created successfully',
            'training' => $training
        ], 201);
    }

    public function show($id)
    {
        $training = TrainingSession::with(['coach', 'attendances.player'])
                                   ->findOrFail($id);

        $training->attendance_rate = $training->getAttendanceRate();
        $training->average_performance = $training->getAveragePerformance();

        return response()->json($training);
    }

    public function update(Request $request, $id)
    {
        $training = TrainingSession::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'end_time' => 'sometimes',
            'location' => 'nullable|string',
            'status' => 'sometimes|in:scheduled,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $training->update($request->all());
        $training->load(['coach', 'attendances.player']);

        return response()->json([
            'message' => 'Training session updated successfully',
            'training' => $training
        ]);
    }

    public function destroy($id)
    {
        $training = TrainingSession::findOrFail($id);
        $training->delete();

        return response()->json([
            'message' => 'Training session deleted successfully'
        ]);
    }

    public function markAttendance(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'attendances' => 'required|array',
            'attendances.*.player_id' => 'required|exists:players,id',
            'attendances.*.status' => 'required|in:present,absent,late,excused',
            'attendances.*.performance_score' => 'nullable|integer|min:1|max:10',
            'attendances.*.remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $training = TrainingSession::findOrFail($id);

        foreach ($request->attendances as $attendanceData) {
            Attendance::updateOrCreate(
                [
                    'training_session_id' => $training->id,
                    'player_id' => $attendanceData['player_id']
                ],
                [
                    'status' => $attendanceData['status'],
                    'performance_score' => $attendanceData['performance_score'] ?? null,
                    'remarks' => $attendanceData['remarks'] ?? null,
                ]
            );
        }

        $training->load(['attendances.player']);

        return response()->json([
            'message' => 'Attendance marked successfully',
            'training' => $training
        ]);
    }
}