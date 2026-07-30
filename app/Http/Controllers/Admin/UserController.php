<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GedcomParserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of all users.
     */
    public function index(Request $request, GedcomParserService $parser): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'is_superuser', 'is_verified', 'start_person_id', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $parser->getOrParseData();
        $individuals = [];
        foreach ($data['individuals'] as $ind) {
            $individuals[] = [
                'id' => $ind['id'],
                'name' => $ind['name'],
                'birth_year' => $ind['birth_year'],
            ];
        }

        usort($individuals, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'individuals' => $individuals,
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
     * Update the start person for the specified user.
     */
    public function updateStartPerson(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'start_person_id' => ['nullable', 'string'],
        ]);

        $user->update([
            'start_person_id' => $validated['start_person_id'] ?: null,
        ]);

        return back()->with('status', "Start person for {$user->email} has been updated.");
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
