<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="lg:col-span-2">
                <!-- Profile Photo File Input -->
                <input type="file" id="photo" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <label for="photo" class="block text-sm font-medium text-ableton-light mb-2">
                    {{ __('Profile Photo') }}
                </label>

                <div class="flex items-center space-x-4">
                    <!-- Current Profile Photo -->
                    <div x-show="! photoPreview">
                        <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="rounded-lg w-20 h-20 object-cover border border-ableton-light/20">
                    </div>

                    <!-- New Profile Photo Preview -->
                    <div x-show="photoPreview" style="display: none;">
                        <span class="block rounded-lg w-20 h-20 bg-cover bg-no-repeat bg-center border border-ableton-light/20"
                              x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                        </span>
                    </div>

                    <div class="space-y-2">
                        <x-button variant="secondary" size="sm" type="button" x-on:click.prevent="$refs.photo.click()">
                            {{ __('Change Photo') }}
                        </x-button>

                        @if ($this->user->profile_photo_path)
                            <x-button variant="ghost" size="sm" type="button" wire:click="deleteProfilePhoto">
                                {{ __('Remove') }}
                            </x-button>
                        @endif
                    </div>
                </div>

                @error('photo')
                    <p class="mt-2 text-sm text-ableton-danger">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <!-- Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Name') }}
            </label>
            <x-input 
                id="name" 
                type="text" 
                wire:model="state.name" 
                required 
                autocomplete="name"
                placeholder="Your full name"
                :error="$errors->first('name')"
            />
        </div>

        <!-- Email -->
        <div>
            <label for="email" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Email') }}
            </label>
            <x-input 
                id="email" 
                type="email" 
                wire:model="state.email" 
                required 
                autocomplete="username"
                placeholder="your.email@example.com"
                :error="$errors->first('email')"
            />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                <div class="mt-3 p-3 bg-ableton-warning/20 border border-ableton-warning/30 rounded text-sm">
                    <p class="text-ableton-warning mb-2">{{ __('Your email address is unverified.') }}</p>
                    
                    <button 
                        type="button" 
                        class="text-ableton-accent hover:text-ableton-accent/80 underline focus:outline-none focus:ring-2 focus:ring-ableton-accent focus:ring-offset-2 focus:ring-offset-ableton-black rounded" 
                        wire:click.prevent="sendEmailVerification"
                    >
                        {{ __('Click here to re-send the verification email.') }}
                    </button>

                    @if ($this->verificationLinkSent)
                        <p class="mt-2 font-medium text-ableton-success">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <!-- Bio -->
        <div class="lg:col-span-2">
            <label for="bio" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Bio') }}
            </label>
            <textarea 
                id="bio" 
                class="w-full px-4 py-2 text-ableton-light bg-ableton-gray border border-ableton-light/20 rounded transition-colors focus:outline-none focus:border-ableton-accent focus:ring-2 focus:ring-ableton-accent/50 placeholder-ableton-light/50" 
                wire:model="state.bio" 
                rows="4" 
                maxlength="500" 
                placeholder="Tell us about yourself, your music style, experience..."
            ></textarea>
            @error('bio')
                <p class="mt-1 text-sm text-ableton-danger">{{ $message }}</p>
            @enderror
            <p class="text-sm text-ableton-light/70 mt-1">Maximum 500 characters</p>
        </div>

        <!-- Location -->
        <div>
            <label for="location" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Location') }}
            </label>
            <x-input 
                id="location" 
                type="text" 
                wire:model="state.location" 
                placeholder="e.g., Los Angeles, CA"
                :error="$errors->first('location')"
            />
        </div>

        <!-- Website -->
        <div>
            <label for="website" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Website') }}
            </label>
            <x-input 
                id="website" 
                type="url" 
                wire:model="state.website" 
                placeholder="https://your-website.com"
                :error="$errors->first('website')"
            />
        </div>

        <!-- Social Media Links -->
        <div class="lg:col-span-2">
            <h3 class="text-lg font-medium text-ableton-light mb-4">Social Media Links</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- SoundCloud -->
                <div>
                    <label for="soundcloud_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('SoundCloud') }}
                    </label>
                    <x-input 
                        id="soundcloud_url" 
                        type="url" 
                        wire:model="state.soundcloud_url" 
                        placeholder="https://soundcloud.com/your-profile"
                        :error="$errors->first('soundcloud_url')"
                    />
                </div>

                <!-- Bandcamp -->
                <div>
                    <label for="bandcamp_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('Bandcamp') }}
                    </label>
                    <x-input 
                        id="bandcamp_url" 
                        type="url" 
                        wire:model="state.bandcamp_url" 
                        placeholder="https://your-name.bandcamp.com"
                        :error="$errors->first('bandcamp_url')"
                    />
                </div>

                <!-- Spotify -->
                <div>
                    <label for="spotify_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('Spotify') }}
                    </label>
                    <x-input 
                        id="spotify_url" 
                        type="url" 
                        wire:model="state.spotify_url" 
                        placeholder="https://open.spotify.com/artist/..."
                        :error="$errors->first('spotify_url')"
                    />
                </div>

                <!-- YouTube -->
                <div>
                    <label for="youtube_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('YouTube') }}
                    </label>
                    <x-input 
                        id="youtube_url" 
                        type="url" 
                        wire:model="state.youtube_url" 
                        placeholder="https://youtube.com/c/your-channel"
                        :error="$errors->first('youtube_url')"
                    />
                </div>

                <!-- Instagram -->
                <div>
                    <label for="instagram_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('Instagram') }}
                    </label>
                    <x-input 
                        id="instagram_url" 
                        type="url" 
                        wire:model="state.instagram_url" 
                        placeholder="https://instagram.com/your-username"
                        :error="$errors->first('instagram_url')"
                    />
                </div>

                <!-- Twitter -->
                <div>
                    <label for="twitter_url" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('Twitter') }}
                    </label>
                    <x-input 
                        id="twitter_url" 
                        type="url" 
                        wire:model="state.twitter_url" 
                        placeholder="https://twitter.com/your-username"
                        :error="$errors->first('twitter_url')"
                    />
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-4" on="saved">
            <span class="text-ableton-success text-sm">{{ __('Profile updated successfully!') }}</span>
        </x-action-message>

        <x-button 
            variant="primary" 
            wire:loading.attr="disabled" 
            wire:target="photo"
            wire:loading.class="opacity-50"
        >
            <span wire:loading.remove wire:target="photo">{{ __('Save Changes') }}</span>
            <span wire:loading wire:target="photo">{{ __('Saving...') }}</span>
        </x-button>
    </x-slot>
</x-form-section>
