<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Please confirm access to your account by entering the two-factor authentication code provided by your authenticator application.') }}
    </div>

    <form method="POST" action="{{ route('two-factor.confirm') }}">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication Code')" />
            <x-text-input id="code" class="block mt-1 w-full" type="text" name="code" required autofocus autocomplete="off" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Confirm') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
