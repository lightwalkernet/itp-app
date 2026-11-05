<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The first tab. A placeholder until the player and the user's ITPs arrive.
 */
#[Layout('layouts.app')]
#[Title('Home')]
class Home extends Component
{
    public function render(): View
    {
        return view('livewire.home', ['user' => Auth::user()]);
    }
}
