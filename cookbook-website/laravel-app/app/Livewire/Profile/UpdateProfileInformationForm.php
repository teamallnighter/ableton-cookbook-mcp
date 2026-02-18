<?php

namespace App\Livewire\Profile;

use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Livewire\Component;
use Livewire\WithFileUploads;

class UpdateProfileInformationForm extends Component
{
    use WithFileUploads;

    /**
     * The component's state.
     */
    public $state = [];

    /**
     * The new avatar for the user.
     */
    public $photo;

    /**
     * Determine if the verification email was sent.
     */
    public $verificationLinkSent = false;

    /**
     * Prepare the component.
     */
    public function mount(): void
    {
        $this->state = array_merge([
            'name' => auth()->user()->name,
            'email' => auth()->user()->email,
            'bio' => auth()->user()->bio,
            'location' => auth()->user()->location,
            'website' => auth()->user()->website,
            'soundcloud_url' => auth()->user()->soundcloud_url,
            'bandcamp_url' => auth()->user()->bandcamp_url,
            'spotify_url' => auth()->user()->spotify_url,
            'youtube_url' => auth()->user()->youtube_url,
            'instagram_url' => auth()->user()->instagram_url,
            'twitter_url' => auth()->user()->twitter_url,
        ], $this->state);
    }

    /**
     * Update the user's profile information.
     */
    public function updateProfileInformation(UpdateUserProfileInformation $updater): void
    {
        $this->resetErrorBag();

        $updater->update(
            auth()->user(),
            $this->photo
                ? array_merge($this->state, ['photo' => $this->photo])
                : $this->state
        );

        if (isset($this->photo)) {
            return;
        }

        $this->dispatch('saved');

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Delete user's profile photo.
     */
    public function deleteProfilePhoto(): void
    {
        auth()->user()->deleteProfilePhoto();

        $this->dispatch('refresh-navigation-menu');
    }

    /**
     * Sent the email verification.
     */
    public function sendEmailVerification(): void
    {
        auth()->user()->sendEmailVerificationNotification();

        $this->verificationLinkSent = true;
    }

    /**
     * Get the current user of the application.
     */
    public function getUserProperty()
    {
        return auth()->user();
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.profile.update-profile-information-form');
    }
}
