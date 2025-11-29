<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CoachController extends Controller
{
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'nullable|string',
            'speciality' => 'nullable|string',
            'team' => 'required|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin', // ✅ AJOUTÉ
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
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
            'team' => $request->team, // ✅ AJOUTÉ
            'phone' => $request->phone,
            'speciality' => $request->speciality,
            'avatar' => $photoPath,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Coach créé avec succès',
            'coach' => $coach
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $coach = User::where('role', 'coach')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable|string',
            'speciality' => 'nullable|string',
            'team' => 'sometimes|in:U18 Masculin,Seniors Féminin,Seniors Masculin,U18 Féminin,U15 Masculin,U15 Féminin', // ✅ AJOUTÉ
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
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