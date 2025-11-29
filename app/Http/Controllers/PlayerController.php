<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        $query = Player::with('user');

        // ✅ FILTRAGE PAR ÉQUIPE DU COACH
        $user = auth()->user();
        if ($user->role === 'coach' && $user->team) {
            $query->where('team', $user->team);
        }

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $players = $query->get();

        // Add stats
        $players->each(function($player) {
            $player->attendance_rate = $player->getAttendanceRate();
            $player->average_performance = $player->getAveragePerformance();
        });

        return response()->json($players);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'date_of_birth' => 'required|date',
            'position' => 'nullable|string',
            'jersey_number' => 'nullable|integer|unique:players',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
            'emergency_phone' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'cv_pdf' => 'nullable|mimes:pdf|max:5120',
            'team' => 'required|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin', // ✅ AJOUTÉ
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Create User
        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'player',
            'phone' => $request->phone,
        ]);

        // Handle file uploads
        $photoPath = null;
        $cvPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('players/photos', 'public');
        }

        if ($request->hasFile('cv_pdf')) {
            $cvPath = $request->file('cv_pdf')->store('players/cvs', 'public');
        }

        // Create Player
        $player = Player::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'date_of_birth' => $request->date_of_birth,
            'position' => $request->position,
            'jersey_number' => $request->jersey_number,
            'photo' => $photoPath,
            'cv_pdf' => $cvPath,
            'address' => $request->address,
            'emergency_contact' => $request->emergency_contact,
            'emergency_phone' => $request->emergency_phone,
            'team' => $request->team, // ✅ AJOUTÉ
        ]);

        $player->load('user');

        return response()->json([
            'message' => 'Player created successfully',
            'player' => $player
        ], 201);
    }

    public function show($id)
    {
        $player = Player::with(['user', 'attendances.trainingSession', 'matches'])
            ->findOrFail($id);

        // ✅ VÉRIFIER SI LE COACH A ACCÈS À CE JOUEUR
        $user = auth()->user();
        if ($user->role === 'coach' && $user->team && $player->team !== $user->team) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $player->attendance_rate = $player->getAttendanceRate();
        $player->average_performance = $player->getAveragePerformance();

        return response()->json($player);
    }

    public function update(Request $request, $id)
    {
        $player = Player::findOrFail($id);

        // ✅ SEUL L'ADMIN PEUT MODIFIER
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Seul l\'admin peut modifier un joueur'], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string',
            'last_name' => 'sometimes|string',
            'date_of_birth' => 'sometimes|date',
            'position' => 'nullable|string',
            'jersey_number' => 'nullable|integer|unique:players,jersey_number,'.$id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'status' => 'sometimes|in:active,injured,suspended',
            'photo' => 'nullable|image|max:2048',
            'cv_pdf' => 'nullable|mimes:pdf|max:5120',
            'team' => 'sometimes|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Handle file uploads
        if ($request->hasFile('photo')) {
            if ($player->photo) {
                Storage::disk('public')->delete($player->photo);
            }
            $player->photo = $request->file('photo')->store('players/photos', 'public');
        }

        if ($request->hasFile('cv_pdf')) {
            if ($player->cv_pdf) {
                Storage::disk('public')->delete($player->cv_pdf);
            }
            $player->cv_pdf = $request->file('cv_pdf')->store('players/cvs', 'public');
        }

        $player->update($request->except(['photo', 'cv_pdf']));

        // Update user
        if ($request->has('phone')) {
            $player->user->update(['phone' => $request->phone]);
        }

        $player->load('user');

        return response()->json([
            'message' => 'Player updated successfully',
            'player' => $player
        ]);
    }

    public function destroy($id)
    {
        // ✅ SEUL L'ADMIN PEUT SUPPRIMER
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Seul l\'admin peut supprimer un joueur'], 403);
        }

        $player = Player::findOrFail($id);

        // Delete files
        if ($player->photo) {
            Storage::disk('public')->delete($player->photo);
        }
        if ($player->cv_pdf) {
            Storage::disk('public')->delete($player->cv_pdf);
        }

        $player->user->delete(); // Cascade delete player

        return response()->json([
            'message' => 'Player deleted successfully'
        ]);
    }
}