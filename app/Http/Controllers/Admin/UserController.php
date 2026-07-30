<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'is_superuser', 'is_verified', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Users', [
            'users' => $users,
        ]);
    }

    /**
     * Verify the specified user.
     */
    public function verify(User $user): RedirectResponse
    {
        $user->update(['is_verified' => true]);

        return back()->with('status', "User {$user->email} has been verified.");
    }

    /**
     * Unverify the specified user.
     */
    public function unverify(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot unverify yourself.']);
        }

        $user->update(['is_verified' => false]);

        return back()->with('status', "User {$user->email} has been unverified.");
    }

    /**
     * Toggle superuser status for the specified user.
     */
    public function toggleSuperuser(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id && $user->is_superuser) {
            return back()->withErrors(['error' => 'You cannot remove superuser access from yourself.']);
        }

        $newStatus = ! $user->is_superuser;

        $user->update([
            'is_superuser' => $newStatus,
            // Superusers are automatically verified
            'is_verified' => $newStatus ? true : $user->is_verified,
        ]);

        $statusMessage = $newStatus
            ? "User {$user->email} is now a superuser."
            : "Superuser privileges removed for {$user->email}.";

        return back()->with('status', $statusMessage);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['error' => 'You cannot delete your own account from user management.']);
        }

        $user->delete();

        return back()->with('status', "User {$user->email} has been deleted.");
    }
}
