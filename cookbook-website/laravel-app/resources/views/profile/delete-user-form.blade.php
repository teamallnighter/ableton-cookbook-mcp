<x-action-section>
    <x-slot name="title">
        {{ __('Delete Account') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Permanently delete your account and all associated data.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-2xl">
            <!-- Warning Message -->
            <div class="p-4 bg-ableton-danger/20 border border-ableton-danger/30 rounded mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-ableton-danger mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h4 class="text-sm font-medium text-ableton-danger mb-2">Warning: This action is irreversible</h4>
                        <p class="text-sm text-ableton-danger/80">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. This includes:') }}
                        </p>
                        <ul class="mt-2 text-sm text-ableton-danger/80 list-disc list-inside space-y-1">
                            <li>Your profile and account information</li>
                            <li>All your posts, comments, and contributions</li>
                            <li>Your rack uploads and project data</li>
                            <li>Your social connections and followers</li>
                        </ul>
                    </div>
                </div>
            </div>

            <p class="text-sm text-ableton-light/80 mb-6">
                {{ __('Before deleting your account, please download any data or information that you wish to retain.') }}
            </p>

            <x-button 
                variant="danger" 
                wire:click="confirmUserDeletion" 
                wire:loading.attr="disabled"
                wire:loading.class="opacity-50"
            >
                <span wire:loading.remove>{{ __('Delete Account') }}</span>
                <span wire:loading>{{ __('Processing...') }}</span>
            </x-button>
        </div>

        <!-- Delete User Confirmation Modal -->
        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                <span class="text-ableton-danger">{{ __('Delete Account') }}</span>
            </x-slot>

            <x-slot name="content">
                <div class="text-ableton-light/80">
                    <p class="mb-4">
                        {{ __('Are you sure you want to delete your account? This action cannot be undone and will permanently remove all your data from our servers.') }}
                    </p>
                    
                    <p class="mb-4 text-sm">
                        {{ __('Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <div x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                        <label for="delete_password" class="block text-sm font-medium text-ableton-light mb-2">
                            {{ __('Password') }}
                        </label>
                        <x-input 
                            id="delete_password"
                            type="password" 
                            class="w-full"
                            autocomplete="current-password"
                            placeholder="Enter your password to confirm"
                            x-ref="password"
                            wire:model="password"
                            wire:keydown.enter="deleteUser"
                            :error="$errors->first('password')"
                        />
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <div class="flex items-center justify-end space-x-4">
                    <x-button 
                        variant="secondary" 
                        wire:click="$toggle('confirmingUserDeletion')" 
                        wire:loading.attr="disabled"
                    >
                        {{ __('Cancel') }}
                    </x-button>

                    <x-button 
                        variant="danger" 
                        wire:click="deleteUser" 
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50"
                    >
                        <span wire:loading.remove>{{ __('Delete Account Forever') }}</span>
                        <span wire:loading>{{ __('Deleting...') }}</span>
                    </x-button>
                </div>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
