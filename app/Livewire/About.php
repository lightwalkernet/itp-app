<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * About It's Totally Possible (brief 1 §6): the Creative Codex's own description of ITP (§1, "The
 * Spirit of ITP"), word for word. A white room; no data.
 */
#[Layout('layouts.app')]
#[Title('About')]
class About extends Component
{
    public function render(): View
    {
        return view('livewire.about');
    }
}
