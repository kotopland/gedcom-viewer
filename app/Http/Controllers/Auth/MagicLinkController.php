<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class MagicLinkController extends Controller
{
    /**
     * Send a magic login link to the user's email.
     */
    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->isSuperuser()) {
            return back()->withErrors([
                'email' => 'Superusers must log in using their password. Please click "Superuser Password Login".',
            ]);
        }

        $url = URL::temporarySignedRoute(
            'magic-link.verify',
            now()->addMinutes(15),
            ['user' => $user->id]
        );

        $user->notify(new MagicLinkNotification($url));

        return back()->with('status', 'We have emailed your magic login link! Please check your inbox.');
    }

    /**
     * Verify the signed magic login link and log in the user.
     */
    public function verify(Request $request, User $user)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')->withErrors([
                'email' => 'This magic link has expired or is invalid. Please request a new login link.',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->isSuperuser()) {
            return redirect()->intended('/dashboard');
        }

        if ($user->is_verified && ! empty($user->start_person_id)) {
            return redirect()->intended('/gedcom');
        }

        return redirect()->route('verification.pending');
    }
}
