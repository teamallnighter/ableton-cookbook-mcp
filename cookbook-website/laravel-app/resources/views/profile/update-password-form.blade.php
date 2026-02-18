<x-form-section submit="updatePassword">
    <x-slot name="title">
        {{ __('Update Password') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Current Password -->
        <div>
            <label for="current_password" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Current Password') }}
            </label>
            <x-input 
                id="current_password" 
                type="password" 
                wire:model="state.current_password" 
                autocomplete="current-password"
                placeholder="Enter your current password"
                :error="$errors->first('current_password')"
            />
        </div>

        <!-- New Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('New Password') }}
            </label>
            <x-input 
                id="password" 
                type="password" 
                wire:model="state.password" 
                autocomplete="new-password"
                placeholder="Choose a strong new password"
                :error="$errors->first('password')"
            />
        </div>

        <!-- Confirm New Password -->
        <div class="lg:col-span-2">
            <label for="password_confirmation" class="block text-sm font-medium text-ableton-light mb-2">
                {{ __('Confirm New Password') }}
            </label>
            <x-input 
                id="password_confirmation" 
                type="password" 
                wire:model="state.password_confirmation" 
                autocomplete="new-password"
                placeholder="Confirm your new password"
                :error="$errors->first('password_confirmation')"
            />
            
            <!-- Password Requirements -->
            <div class="mt-3 p-3 bg-ableton-light/5 border border-ableton-light/10 rounded text-sm">
                <p class="text-ableton-light/70 mb-2 font-medium">Password requirements:</p>
                <ul class="text-ableton-light/60 text-xs space-y-1">
                    <li>• At least 8 characters long</li>
                    <li>• Mix of uppercase and lowercase letters</li>
                    <li>• Include numbers and special characters</li>
                    <li>• Avoid common words or personal information</li>
                </ul>
            </div>
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-4" on="saved">
            <span class="text-ableton-success text-sm">{{ __('Password updated successfully!') }}</span>
        </x-action-message>

        <x-button variant="primary">
            {{ __('Update Password') }}
        </x-button>
    </x-slot>
</x-form-section>
