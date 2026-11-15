<?php

namespace App\Livewire;

use App\Api\AccountClient;
use App\Auth\AppSession;
use App\Livewire\Concerns\CallsApi;
use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Deleting an account (platform.md §5, rooms contract): web only, for a guest or anyone signed
 * in. A fresh email code proves it's theirs, then itp-server deletes the account and everything
 * in it for good. A browser signed in as that account is signed out here; a phone finds out on
 * its next API call (401). Property names match the API's fields, so its errors land on them.
 */
#[Layout('layouts.app')]
#[Title('Delete your account')]
class DeleteAccount extends Component
{
    use CallsApi;

    public string $email = '';

    public string $code = '';

    public bool $codeSent = false;

    public bool $deleted = false;

    public function mount(): void
    {
        abort_if(Runtime::isMobile(), 404);

        $this->email = (string) Auth::user()?->email;
    }

    public function sendCode(AccountClient $accounts): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $this->callApi(function () use ($accounts): void {
            $accounts->sendDeletionCode($this->email);

            $this->reset('code');
            $this->codeSent = true;
        }, 'email');
    }

    public function deleteAccount(AccountClient $accounts, AppSession $session): void
    {
        $this->validate(['code' => ['required', 'string', 'digits:6']]);

        $done = $this->callApi(function () use ($accounts): bool {
            $accounts->delete($this->email, $this->code);

            return true;
        }, 'code');

        if ($done !== true) {
            return;
        }

        if (Auth::check() && Str::lower((string) Auth::user()->email) === $this->email) {
            $session->endLocally();
        }

        $this->reset('code');
        $this->deleted = true;
    }

    public function useDifferentEmail(): void
    {
        $this->reset('code', 'codeSent');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.delete-account');
    }
}
