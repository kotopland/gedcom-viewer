<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperuser()) {
            return $next($request);
        }

        if (! $user->is_verified || empty($user->start_person_id)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Your account is in the waiting room pending administrator verification and lineage assignment.',
                    'is_verified' => (bool) $user->is_verified,
                    'has_start_person' => ! empty($user->start_person_id),
                ], 403);
            }

            if (! $request->routeIs('verification.pending')) {
                return redirect()->route('verification.pending');
            }
        }

        return $next($request);
    }
}
