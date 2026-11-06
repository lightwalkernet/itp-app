<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A finished ITP, as text: the attunement, its Breathe 11 and its Quantum Whys. A local row on
 * the phone, the shared database on the web. Only its owner sees it; anyone else gets a 404.
 */
#[Layout('layouts.app')]
#[Title('Your ITP')]
class ShowItp extends Component
{
    public Itp $itp;

    public function mount(Itp $itp): void
    {
        abort_unless($itp->user_id === Auth::id(), 404);

        $this->itp = $itp;
    }

    public function render(): View
    {
        return view('livewire.show-itp', [
            'paragraphs' => array_values(array_filter(
                array_map('trim', preg_split('/\R{2,}/', (string) $this->itp->attunement_text)),
                fn (string $paragraph): bool => $paragraph !== '',
            )),
            'breathLines' => collect($this->itp->breath11_lines ?? [])->sortBy('order')->values(),
            'quantumWhys' => $this->itp->quantum_whys ?? [],
        ]);
    }
}
