<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CoachController extends Controller
{
    public function store(Request $request)
    {
        try {
            Log::info('Coach Creation Request:', $request->all());

            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'phone' => 'nullable|string',
                'speciality' => 'nullable|string',
                'team' => 'required|string|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin',
                'photo' => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                Log::error('Validation Failed:', $validator->errors()->toArray());
                return response()->json([
                    'error' => 'Erreur de validation',
                    'details' => $validator->errors()
                ], 422);
            }

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('coaches/photos', 'public');
            }

            $coach = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'coach',
                'team' => $request->team,
                'phone' => $request->phone,
                'speciality' => $request->speciality,
                'avatar' => $photoPath,
                'is_active' => true,
            ]);

            Log::info('Coach Created Successfully:', ['coach_id' => $coach->id]);

            return response()->json([
                'message' => 'Coach créé avec succès',
                'coach' => $coach
            ], 201);

        } catch (\Exception $e) {
            Log::error('Coach Creation Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la création du coach',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $coach = User::where('role', 'coach')->findOrFail($id);

            Log::info('Coach Update Request:', $request->all());

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'phone' => 'nullable|string',
                'speciality' => 'nullable|string',
                'team' => 'sometimes|string|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin',
                'photo' => 'nullable|image|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => 'Erreur de validation',
                    'details' => $validator->errors()
                ], 422);
            }

            if ($request->hasFile('photo')) {
                if ($coach->avatar) {
                    Storage::disk('public')->delete($coach->avatar);
                }
                $coach->avatar = $request->file('photo')->store('coaches/photos', 'public');
            }

            $coach->update($request->except(['photo', 'password']));

            return response()->json([
                'message' => 'Coach modifié avec succès',
                'coach' => $coach
            ]);

        } catch (\Exception $e) {
            Log::error('Coach Update Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la modification',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Autres méthodes inchangées
    public function index()
    {
        $coaches = User::where('role', 'coach')->get();
        return response()->json($coaches);
    }

    public function show($id)
    {
        $coach = User::where('role', 'coach')->findOrFail($id);
        return response()->json($coach);
    }

    public function destroy($id)
    {
        $coach = User::where('role', 'coach')->findOrFail($id);

        if ($coach->avatar) {
            Storage::disk('public')->delete($coach->avatar);
        }

        $coach->delete();

        return response()->json([
            'message' => 'Coach supprimé avec succès'
        ]);
    }
}