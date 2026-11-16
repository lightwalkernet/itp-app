<?php

namespace App\Livewire;

use App\Support\PlayLink;
use App\Support\Welcome as FirstLaunch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The welcome on first launch (player contract), from itpmobile's `sections/welcome`: "It's /
 * totally / possible" and Enter the Field, which plays the Introduction and then opens Today. A
 * light room with the bar away. Never for a signed-in user, and never once it has been seen.
 */
#[Layout('layouts.app')]
#[Title('Welcome')]
class Welcome extends Component
{
    #[Locked]
    public string $enterUrl = '';

    public function mount(): void
    {
        if (Auth::check() || FirstLaunch::seen()) {
            $this->redirectRoute('home');

            return;
        }

        $introduction = FirstLaunch::introduction();

        if ($introduction === null) {
            FirstLaunch::markSeen();
            $this->redirectRoute('home');

            return;
        }

        $this->enterUrl = PlayLink::for($introduction, 'itp', 'welcome');
    }

    public function render(): View
    {
        return view('livewire.welcome');
    }
}
