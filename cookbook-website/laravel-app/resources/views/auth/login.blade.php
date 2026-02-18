<x-guest-layout>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="card max-w-md w-full p-8">
            <!-- Logo/Branding -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-black mb-2">Ableton Cookbook</h1>
                <p class="text-gray-700 text-sm">Welcome back to the community</p>
            </div>

            <!-- Status Messages -->
            @session('status')
                <div class="mb-6 p-4 bg-white border-2 border-vibrant-green rounded text-vibrant-green text-sm font-medium">
                    {{ $value }}
                </div>
            @endsession

            <!-- Validation Errors -->
            @if ($errors->any())
                <div class="mb-6 p-4 bg-white border-2 border-vibrant-red rounded">
                    <ul class="text-vibrant-red text-sm space-y-1 font-medium">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6" x-data="{ submitting: false }" @submit="submitting = true">
                @csrf

                <!-- Email Field -->
                <div>
                    <label for="email" class="block text-sm font-medium text-black mb-2">
                        {{ __('Email') }}
                    </label>
                    <input 
                        id="email" 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Enter your email address"
                        class="input-field"
                    />
                </div>

                <!-- Password Field -->
                <div>
                    <label for="password" class="block text-sm font-medium text-black mb-2">
                        {{ __('Password') }}
                    </label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="input-field @error('password') border-red-500 @enderror"
                    />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center">
                    <input 
                        id="remember_me" 
                        name="remember" 
                        type="checkbox" 
                        class="h-4 w-4 text-black bg-white border-2 border-black rounded focus:ring-2 focus:ring-black"
                    >
                    <label for="remember_me" class="ml-3 text-sm text-black">
                        {{ __('Remember me') }}
                    </label>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <button type="submit" class="btn-primary w-full text-lg">
                        {{ __('Sign In') }}
                    </button>

                    <div class="text-center">
                        @if (Route::has('password.request'))
                            <a 
                                href="{{ route('password.request') }}" 
                                class="link text-sm"
                            >
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif
                    </div>

                    <div class="text-center border-t-2 border-black pt-6">
                        <p class="text-sm text-gray-700 mb-3">Don't have an account?</p>
                        <a 
                            href="{{ route('register') }}" 
                            class="btn-secondary"
                        >
                            {{ __('Create an account') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
