<?php

namespace App\Livewire;

use App\Api\AuthClient;
use App\Livewire\Concerns\CallsApi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Asks for a first name when the account has none. Checked on every visit by
 * EnsureProfileIsComplete. The name is saved through the API, never written here.
 */
#[Layout('layouts.guest')]
#[Title('Your name')]
class CompleteProfile extends Component
{
    use CallsApi;

    /**
     * Named like the API field, so its errors land here.
     */
    public string $first_name = '';

    public function mount(): void
    {
        if (Auth::user()->first_name !== null) {
            $this->redirectRoute('home');
        }
    }

    public function save(AuthClient $auth): void
    {
        $this->first_name = trim($this->first_name);

        $this->validate(['first_name' => ['required', 'string', 'max:255']]);

        $saved = $this->callApi(fn () => $auth->completeProfile($this->first_name), 'first_name');

        if ($saved !== null) {
            $this->redirectRoute('home');
        }
    }

    public function render(): View
    {
        return view('livewire.complete-profile');
    }
}
