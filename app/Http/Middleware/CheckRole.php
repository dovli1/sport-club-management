<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Vérifier si l'utilisateur est connecté
        if (!auth()->check()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }

        $user = auth()->user();

        // Vérifier si l'utilisateur a le bon rôle
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'error' => 'Forbidden - You do not have the required role',
                'required_roles' => $roles,
                'your_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}