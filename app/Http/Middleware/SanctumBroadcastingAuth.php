<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SanctumBroadcastingAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle Sanctum token authentication for broadcasting
        if ($request->hasHeader('Authorization')) {
            $token = $request->bearerToken();
            if ($token) {
                // Find the token and authenticate the user
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken) {
                    $user = $personalAccessToken->tokenable;
                    Auth::setUser($user);
                    
                    // Debug: Log the authentication
                    \Log::info('Sanctum broadcasting auth successful', [
                        'user_id' => $user->id,
                        'channel' => $request->input('channel_name')
                    ]);
                }
            }
        }

        return $next($request);
    }
}
