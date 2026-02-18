<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-ableton-light">
            {{ __('Profile Settings') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Profile Header -->
            <div class="card p-6 mb-8">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-ableton-accent rounded-lg flex items-center justify-center">
                        <span class="text-xl font-bold text-ableton-black">
                            {{ substr(Auth::user()->name, 0, 1) }}
                        </span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-ableton-light">{{ Auth::user()->name }}</h1>
                        <p class="text-ableton-light/70">{{ Auth::user()->email }}</p>
                    </div>
                </div>
            </div>

            <!-- Profile Management Sections -->
            <div class="space-y-8">
                @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                    <div class="card">
                        @livewire('profile.update-profile-information-form')
                    </div>
                @endif

                @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                    <div class="card">
                        @livewire('profile.update-password-form')
                    </div>
                @endif

                @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                    <div class="card">
                        @livewire('profile.two-factor-authentication-form')
                    </div>
                @endif

                <div class="card">
                    @livewire('profile.logout-other-browser-sessions-form')
                </div>

                @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
                    <div class="card border-ableton-danger/30">
                        @livewire('profile.delete-user-form')
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
