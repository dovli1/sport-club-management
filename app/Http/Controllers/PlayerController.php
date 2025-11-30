<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PlayerController extends Controller
{
    // ✅ INDEX - Liste des joueurs avec données complètes
    public function index(Request $request)
    {
        $query = Player::with('user');

        $user = auth()->user();
        if ($user->role === 'coach' && $user->team) {
            $query->where('team', $user->team);
        }

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

        // ✅ MAPPER les données correctement pour le frontend
        $playersFormatted = $players->map(function($player) {
            return [
                'id' => $player->id,
                'name' => $player->first_name . ' ' . $player->last_name,
                'email' => $player->user->email ?? '',
                'phone' => $player->user->phone ?? '',
                'age' => $player->date_of_birth ? now()->diffInYears($player->date_of_birth) : null,
                'team' => $player->team,
                'position' => $player->position,
                'number' => $player->jersey_number,
                'photo' => $player->photo ? Storage::url($player->photo) : null,
                'cv' => $player->cv_pdf ? Storage::url($player->cv_pdf) : null,
                'status' => $player->status,
                'attendance_rate' => $player->getAttendanceRate(),
                'average_performance' => $player->getAveragePerformance(),
            ];
        });

        return response()->json($playersFormatted);
    }

    // ✅ SHOW - Détails d'un joueur spécifique
    public function show($id)
    {
        $player = Player::with(['user', 'attendances.trainingSession', 'matches'])
            ->findOrFail($id);

        $user = auth()->user();
        if ($user->role === 'coach' && $user->team && $player->team !== $user->team) {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        // ✅ Formatter les données complètes
        $playerData = [
            'id' => $player->id,
            'name' => $player->first_name . ' ' . $player->last_name,
            'email' => $player->user->email ?? '',
            'phone' => $player->user->phone ?? '',
            'age' => $player->date_of_birth ? now()->diffInYears($player->date_of_birth) : null,
            'team' => $player->team,
            'position' => $player->position,
            'number' => $player->jersey_number,
            'photo' => $player->photo ? Storage::url($player->photo) : null,
            'cv' => $player->cv_pdf ? Storage::url($player->cv_pdf) : null,
            'status' => $player->status,
            'joinDate' => $player->created_at->format('Y-m-d'),
            'attendance_rate' => $player->getAttendanceRate(),
            'avgPerformance' => $player->getAveragePerformance(),
            
            // ✅ Historique de présence
            'attendances' => $player->attendances->map(function($att) {
                return [
                    'date' => $att->created_at->format('d/m/Y'),
                    'training' => $att->trainingSession->title ?? 'N/A',
                    'status' => $att->status,
                    'performance' => $att->performance_score,
                ];
            }),

            // ✅ Matchs joués
            'matches' => $player->matches->map(function($match) {
                return [
                    'date' => $match->match_date->format('d/m/Y'),
                    'opponent' => $match->opponent_team,
                    'result' => $match->result,
                    'goals' => $match->pivot->goals ?? 0,
                ];
            }),
        ];

        return response()->json($playerData);
    }

    // ✅ STORE - Créer un joueur (SEULEMENT age)
    public function store(Request $request)
    {
        try {
            Log::info('Player Creation Request:', $request->all());

            // ✅ VALIDATION AVEC age SEULEMENT
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'name' => 'required|string|min:2',
                'age' => 'required|integer|min:10|max:100', // ✅ SEULEMENT age
                'position' => 'required|string',
                'number' => 'required|integer|unique:players,jersey_number',
                'phone' => 'nullable|string',
                'team' => 'required|string|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin',
            ]);

            if ($validator->fails()) {
                Log::error('Validation Failed:', $validator->errors()->toArray());
                return response()->json([
                    'error' => 'Erreur de validation',
                    'details' => $validator->errors()
                ], 422);
            }

            // ✅ SÉPARER LE NOM
            $nameParts = explode(' ', $request->name, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? $nameParts[0];

            // ✅ CRÉER L'UTILISATEUR
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'player',
                'phone' => $request->phone,
                'is_active' => true,
            ]);

            // ✅ CALCULER date_of_birth À PARTIR DE age (pour la base de données)
            $dateOfBirth = now()->subYears($request->age);

            // ✅ CRÉER LE JOUEUR
            $player = Player::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'date_of_birth' => $dateOfBirth, // ← Stocké en base mais pas demandé à l'utilisateur
                'position' => $request->position,
                'jersey_number' => $request->number,
                'team' => $request->team,
                'status' => 'active',
            ]);

            Log::info('Player Created Successfully:', ['player_id' => $player->id]);

            // ✅ RETOURNER age (pas date_of_birth)
            return response()->json([
                'message' => 'Joueur créé avec succès',
                'player' => [
                    'id' => $player->id,
                    'name' => $player->first_name . ' ' . $player->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'age' => $request->age, // ✅ Retourner l'âge donné
                    'team' => $player->team,
                    'position' => $player->position,
                    'number' => $player->jersey_number,
                    'status' => $player->status,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Player Creation Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la création du joueur',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ UPDATE - Mettre à jour un joueur
    public function update(Request $request, $id)
    {
        try {
            $player = Player::findOrFail($id);

            if (auth()->user()->role !== 'admin') {
                return response()->json(['error' => 'Accès refusé'], 403);
            }

            Log::info('Player Update Request:', $request->all());

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|min:2',
                'age' => 'sometimes|integer|min:10|max:100',
                'position' => 'nullable|string',
                'number' => 'nullable|integer|unique:players,jersey_number,'.$id,
                'phone' => 'nullable|string',
                'status' => 'sometimes|in:active,injured,suspended',
                'team' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Erreur de validation',
                    'details' => $validator->errors()
                ], 422);
            }

            // Mettre à jour le nom
            if ($request->has('name')) {
                $nameParts = explode(' ', $request->name, 2);
                $player->first_name = $nameParts[0];
                $player->last_name = $nameParts[1] ?? $nameParts[0];
                $player->user->update(['name' => $request->name]);
            }

            // Mettre à jour l'âge
            if ($request->has('age')) {
                $player->date_of_birth = now()->subYears($request->age);
            }

            // Mettre à jour les autres champs
            $player->position = $request->position ?? $player->position;
            $player->jersey_number = $request->number ?? $player->jersey_number;
            $player->team = $request->team ?? $player->team;
            $player->status = $request->status ?? $player->status;
            $player->save();

            if ($request->has('phone')) {
                $player->user->update(['phone' => $request->phone]);
            }

            return response()->json([
                'message' => 'Joueur modifié avec succès',
                'player' => [
                    'id' => $player->id,
                    'name' => $player->first_name . ' ' . $player->last_name,
                    'email' => $player->user->email,
                    'phone' => $player->user->phone,
                    'team' => $player->team,
                    'position' => $player->position,
                    'number' => $player->jersey_number,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Player Update Error:', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Erreur lors de la modification',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ DELETE - Supprimer un joueur
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Accès refusé'], 403);
        }

        $player = Player::findOrFail($id);

        if ($player->photo) {
            Storage::disk('public')->delete($player->photo);
        }
        if ($player->cv_pdf) {
            Storage::disk('public')->delete($player->cv_pdf);
        }

        $player->user->delete();

        return response()->json([
            'message' => 'Joueur supprimé avec succès'
        ]);
    }
}