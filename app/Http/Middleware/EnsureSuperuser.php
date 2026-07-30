<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperuser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperuser()) {
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized. Superuser access required.');
            }

            return redirect()->route('gedcom.index');
        }

        return $next($request);
    }
}
