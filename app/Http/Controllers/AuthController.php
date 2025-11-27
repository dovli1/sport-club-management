<?php
// app/Http\Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; // ← IMPORTANT !

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:admin,coach,player',
            'phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'phone' => $request->phone,
        ]);

        // If player, create player profile
        if ($request->role === 'player') {
            Player::create([
                'user_id' => $user->id,
                'first_name' => explode(' ', $request->name)[0],
                'last_name' => explode(' ', $request->name)[1] ?? '',
                'date_of_birth' => $request->date_of_birth ?? now(),
            ]);
        }

        $token = Auth::login($user); // ← Maintenant Auth:: au lieu de auth()

        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if (!$token = Auth::attempt($validator->validated())) { // ← Auth::attempt
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = Auth::user(); // ← Auth::user
        $user->load('player');
        
        return response()->json($user);
    }

    public function logout()
    {
        Auth::logout(); // ← Auth::logout

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh()); // ← Auth::refresh
    }

    protected function respondWithToken($token)
    {
        $user = Auth::user(); // ← Auth::user
        $user->load('player');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60, // ← Auth::factory
            'user' => $user
        ]);
    }
}