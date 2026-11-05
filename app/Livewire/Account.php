<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The account tab: who is signed in, and signing out.
 */
#[Layout('layouts.app')]
#[Title('Account')]
class Account extends Component
{
    public function render(): View
    {
        return view('livewire.account', ['user' => Auth::user()]);
    }
}
