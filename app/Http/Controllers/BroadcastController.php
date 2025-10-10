<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class BroadcastController extends Controller
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        // Handle Sanctum token authentication
        if ($request->hasHeader('Authorization')) {
            $token = $request->bearerToken();
            if ($token) {
                // Find the token and authenticate the user
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($personalAccessToken) {
                    $user = $personalAccessToken->tokenable;
                    Auth::setUser($user);
                    
                    // Debug: Log the authentication
                    \Log::info('Broadcasting auth successful', [
                        'user_id' => $user->id,
                        'channel' => $request->input('channel_name')
                    ]);
                }
            }
        }

        // If no user is authenticated, return 403
        if (!Auth::check()) {
            \Log::info('Broadcasting auth failed - no user authenticated');
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Use Laravel's built-in channel authorization
        return Broadcast::auth($request);
    }
}
