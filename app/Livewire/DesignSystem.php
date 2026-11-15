<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The design system on one screen, for checking tokens and components in the real app and the
 * WebView rather than in sketches. Registered only in the local environment (routes/web.php),
 * so it never ships to Forge or the phone build.
 */
#[Layout('layouts.app')]
#[Title('Design')]
class DesignSystem extends Component
{
    public function render(): View
    {
        return view('livewire.design-system');
    }
}
