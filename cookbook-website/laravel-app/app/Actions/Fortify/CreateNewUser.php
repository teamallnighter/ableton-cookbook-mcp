<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'username' => ['required', 'string', 'max:50', 'unique:users', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'email_consent' => ['required', 'accepted'],
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ], [
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'username.unique' => 'This username is already taken.',
            'email_consent.required' => 'You must consent to receive emails to create an account.',
            'email_consent.accepted' => 'You must consent to receive emails to create an account.',
        ])->validate();

        try {
            return User::create([
                'name' => $input['username'], // Use username as display name initially
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'email_consent' => true,
                'email_consent_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('User creation failed: ' . $e->getMessage(), [
                'input' => array_except($input, ['password', 'password_confirmation'])
            ]);
            throw $e;
        }
    }
}
