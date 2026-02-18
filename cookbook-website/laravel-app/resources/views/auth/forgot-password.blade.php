<x-guest-layout>
    <div class="min-h-screen bg-ableton-black flex items-center justify-center px-4">
        <div class="card max-w-md w-full p-8 animate-fade-in">
            <!-- Logo/Branding -->
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-ableton-light mb-2">Reset Password</h1>
                <p class="text-ableton-light/70 text-sm">Ableton Cookbook</p>
            </div>

            <!-- Description -->
            <div class="mb-6 text-sm text-ableton-light/80 text-center">
                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </div>

            <!-- Status Messages -->
            @session('status')
                <div class="mb-6 p-4 bg-ableton-success/20 border border-ableton-success/30 rounded text-ableton-success text-sm">
                    {{ $value }}
                </div>
            @endsession

            <!-- Validation Errors -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-ableton-danger/20 border border-ableton-danger/30 rounded">
                    <ul class="text-ableton-danger text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
                @csrf

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-ableton-light mb-2">
                        {{ __('Email') }}
                    </label>
                    <x-input 
                        id="email" 
                        type="email" 
                        name="email" 
                        :value="old('email')" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Enter your email address"
                        :error="$errors->first('email')"
                    />
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <x-button variant="primary" size="lg" class="w-full">
                        {{ __('Send Reset Link') }}
                    </x-button>

                    <div class="text-center border-t border-ableton-light/10 pt-6">
                        <p class="text-sm text-ableton-light/70 mb-3">Remember your password?</p>
                        <a 
                            href="{{ route('login') }}" 
                            class="text-sm text-ableton-accent hover:text-ableton-accent/80 transition-colors focus:outline-none focus:ring-2 focus:ring-ableton-accent focus:ring-offset-2 focus:ring-offset-ableton-black rounded"
                        >
                            {{ __('Back to sign in') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
