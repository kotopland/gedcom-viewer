<?php

namespace App\Actions\Fortify;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use ProfileValidationRules;

    /**
     * Validate and create a newly registered user without requiring a password.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make(Str::random(32)),
            'is_verified' => false,
            'start_person_id' => null,
        ]);

        $superusers = User::where('is_superuser', true)->get();
        foreach ($superusers as $superuser) {
            $superuser->notify(new \App\Notifications\SuperuserRegistrationNotification($user));
        }

        return $user;
    }
}
