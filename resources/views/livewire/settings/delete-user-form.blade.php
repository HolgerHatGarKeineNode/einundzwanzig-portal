<?php

use App\Attributes\SeoDataAttribute;
use App\Livewire\Actions\Logout;
use App\Traits\SeoTrait;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new
#[SeoDataAttribute(key: 'settings_delete_user_form')]
class extends Component {
    use SeoTrait;

    /**
     * The untranslated confirmation word. Always accepted, so a locale switch
     * between rendering the dialog and submitting it cannot lock anyone out.
     */
    private const CONFIRMATION_WORD = 'DELETE';

    /**
     * Accounts sign in through Nostr or LNURL and never with a password; a value in
     * `users.password` is only a placeholder for Laravel's auth. Every account
     * therefore confirms by typing the confirmation word (issue #150).
     */
    public string $confirmation = '';

    #[Computed]
    public function confirmationWord(): string
    {
        return __(self::CONFIRMATION_WORD);
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'confirmation' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->isConfirmationWord($value)) {
                    $fail(__('Please type :word to confirm.', ['word' => $this->confirmationWord]));
                }
            }],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }

    private function isConfirmationWord(string $value): bool
    {
        $typed = mb_strtoupper(trim($value));

        return $typed === mb_strtoupper(self::CONFIRMATION_WORD)
            || $typed === mb_strtoupper($this->confirmationWord);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete account') }}</flux:heading>
        <flux:subheading>{{ __('Permanently delete your account') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Once your account is deleted, your profile and personal settings are permanently removed. Meetups, events, cities and other content you created stay in the portal without an author.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="confirmation" :label="__('Confirm')" autocomplete="off"
                        :description="__('Type :word to confirm you would like to permanently delete your account.', ['word' => $this->confirmationWord])"/>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit">{{ __('Delete account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
