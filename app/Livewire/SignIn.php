<?php

namespace App\Livewire;

use App\Api\AuthClient;
use App\Auth\AppSession;
use App\Livewire\Concerns\CallsApi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Passwordless sign-in through itp-server: email, then the six-digit code. The first sign-in
 * creates the account. Property names match the API's fields, so its errors land on them.
 */
#[Layout('layouts.guest')]
#[Title('Sign in')]
class SignIn extends Component
{
    use CallsApi;

    public string $email = '';

    public string $code = '';

    public bool $codeSent = false;

    public function sendCode(AuthClient $auth): void
    {
        $this->email = Str::lower(trim($this->email));

        $this->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $this->callApi(function () use ($auth): void {
            $auth->sendCode($this->email);

            $this->reset('code');
            $this->codeSent = true;
        }, 'email');
    }

    public function verifyCode(AuthClient $auth, AppSession $session): void
    {
        $this->validate(['code' => ['required', 'string', 'digits:6']]);

        $signIn = $this->callApi(fn () => $auth->verifyCode($this->email, $this->code, $this->deviceName()), 'code');

        if ($signIn === null) {
            return;
        }

        $session->start($signIn);

        $signIn->needsProfile
            ? $this->redirectRoute('profile')
            : $this->redirectIntended(route('home'));
    }

    public function useDifferentEmail(): void
    {
        $this->reset('code', 'codeSent');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.sign-in');
    }

    /**
     * Labels the token on the server, so a list of devices can tell them apart.
     */
    protected function deviceName(): string
    {
        return Str::limit('Web: '.(request()->userAgent() ?: 'browser'), 255, '');
    }
}
