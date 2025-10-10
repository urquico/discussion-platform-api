<?php

namespace App\Http\Middleware;

use App\Services\UserStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    protected UserStatusService $userStatusService;

    public function __construct(UserStatusService $userStatusService)
    {
        $this->userStatusService = $userStatusService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track activity for authenticated users
        if (Auth::check()) {
            $user = Auth::user();
            
            // Use cache to avoid updating on every request (throttle to once per minute)
            $cacheKey = "user_activity_{$user->id}";
            
            if (!Cache::has($cacheKey)) {
                // Update last seen and mark as online
                $this->userStatusService->updateUserLastSeen($user);
                $this->userStatusService->markUserOnline($user);
                
                // Cache for 1 minute to prevent excessive updates
                Cache::put($cacheKey, true, 60);
            }
        }

        return $response;
    }
}
