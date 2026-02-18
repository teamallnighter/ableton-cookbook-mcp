<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'soundcloud_url' => ['nullable', 'url', 'max:255'],
            'bandcamp_url' => ['nullable', 'url', 'max:255'],
            'spotify_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'bio' => $input['bio'] ?? null,
                'location' => $input['location'] ?? null,
                'website' => $input['website'] ?? null,
                'soundcloud_url' => $input['soundcloud_url'] ?? null,
                'bandcamp_url' => $input['bandcamp_url'] ?? null,
                'spotify_url' => $input['spotify_url'] ?? null,
                'youtube_url' => $input['youtube_url'] ?? null,
                'instagram_url' => $input['instagram_url'] ?? null,
                'twitter_url' => $input['twitter_url'] ?? null,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
            'bio' => $input['bio'] ?? null,
            'location' => $input['location'] ?? null,
            'website' => $input['website'] ?? null,
            'soundcloud_url' => $input['soundcloud_url'] ?? null,
            'bandcamp_url' => $input['bandcamp_url'] ?? null,
            'spotify_url' => $input['spotify_url'] ?? null,
            'youtube_url' => $input['youtube_url'] ?? null,
            'instagram_url' => $input['instagram_url'] ?? null,
            'twitter_url' => $input['twitter_url'] ?? null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
