<?php

namespace App\Livewire;

use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Shown when itp-server answers 426. Sync stops until the app is updated; local data is
 * untouched (platform.md §4).
 */
#[Layout('layouts.guest')]
#[Title('Update required')]
class UpdateRequired extends Component
{
    public function render(): View
    {
        return view('livewire.update-required', ['isMobile' => Runtime::isMobile()]);
    }
}
