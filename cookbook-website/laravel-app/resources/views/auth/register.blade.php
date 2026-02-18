<x-guest-layout>
    <div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
        <div class="card max-w-md w-full p-8">
            <!-- Logo/Branding -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-black mb-2">Ableton Cookbook</h1>
                <p class="text-gray-700 text-sm">Join the creative community</p>
            </div>

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

            <form method="POST" action="{{ route('register') }}" class="space-y-6">
                @csrf

                <!-- Username Field -->
                <div>
                    <label for="username" class="block text-sm font-medium text-black mb-2">
                        {{ __('Username') }}
                    </label>
                    <input 
                        id="username" 
                        type="text" 
                        name="username" 
                        value="{{ old('username') }}" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Pick a unique username"
                        class="input-field"
                    />
                </div>

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
                        autocomplete="email"
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
                        autocomplete="new-password"
                        placeholder="Choose a secure password"
                        class="input-field"
                    />
                </div>

                <!-- Confirm Password Field -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-black mb-2">
                        {{ __('Confirm Password') }}
                    </label>
                    <input 
                        id="password_confirmation" 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password"
                        placeholder="Confirm your password"
                        class="input-field"
                    />
                </div>

                <!-- Email Consent -->
                <div class="flex items-start">
                    <input 
                        id="email_consent" 
                        name="email_consent" 
                        type="checkbox" 
                        required
                        class="mt-1 h-4 w-4 text-black bg-white border-2 border-black rounded focus:ring-2 focus:ring-black"
                    >
                    <label for="email_consent" class="ml-3 text-sm text-black">
                        I consent to receive emails from Ableton Cookbook including account notifications, community updates, and occasional newsletters.
                    </label>
                </div>


                <!-- Terms and Privacy Policy -->
                @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                    <div class="flex items-start">
                        <input 
                            id="terms" 
                            name="terms" 
                            type="checkbox" 
                            required
                            class="mt-1 h-4 w-4 text-black bg-white border-2 border-black rounded focus:ring-2 focus:ring-black"
                        >
                        <label for="terms" class="ml-3 text-sm text-black">
                            {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'" class="link">'.__('Terms of Service').'</a>',
                                'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'" class="link">'.__('Privacy Policy').'</a>',
                            ]) !!}
                        </label>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <button type="submit" class="btn-primary w-full text-lg">
                        {{ __('Create Account') }}
                    </button>

                    <div class="text-center border-t-2 border-black pt-6">
                        <p class="text-sm text-gray-700 mb-3">Already have an account?</p>
                        <a 
                            href="{{ route('login') }}" 
                            class="btn-secondary"
                        >
                            {{ __('Sign in to your account') }}
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
