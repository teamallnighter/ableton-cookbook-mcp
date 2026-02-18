<x-guest-layout>
    <div class="min-h-screen bg-ableton-black flex items-center justify-center px-4">
        <div class="card max-w-md w-full p-8 animate-fade-in">
            <!-- Logo/Branding -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-ableton-light mb-2">Verify Email</h1>
                <p class="text-ableton-light/70 text-sm">Ableton Cookbook</p>
            </div>

            <!-- Description -->
            <div class="mb-6 text-sm text-ableton-light/80 text-center">
                {{ __('Before continuing, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </div>

            <!-- Status Messages -->
            @if (session('status') == 'verification-link-sent')
                <div class="mb-6 p-4 bg-ableton-success/20 border border-ableton-success/30 rounded text-ableton-success text-sm">
                    {{ __('A new verification link has been sent to the email address you provided in your profile settings.') }}
                </div>
            @endif

            <!-- Actions -->
            <div class="space-y-6">
                <!-- Resend Verification Email -->
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <x-button variant="primary" size="lg" class="w-full">
                        {{ __('Resend Verification Email') }}
                    </x-button>
                </form>

                <!-- Secondary Actions -->
                <div class="space-y-4 border-t border-ableton-light/10 pt-6">
                    <div class="flex flex-col space-y-3">
                        <a 
                            href="{{ route('profile.show') }}" 
                            class="text-center text-sm text-ableton-accent hover:text-ableton-accent/80 transition-colors focus:outline-none focus:ring-2 focus:ring-ableton-accent focus:ring-offset-2 focus:ring-offset-ableton-black rounded"
                        >
                            {{ __('Edit Profile') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="text-center">
                            @csrf
                            <button 
                                type="submit" 
                                class="text-sm text-ableton-light/70 hover:text-ableton-light transition-colors focus:outline-none focus:ring-2 focus:ring-ableton-light focus:ring-offset-2 focus:ring-offset-ableton-black rounded"
                            >
                                {{ __('Sign Out') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
