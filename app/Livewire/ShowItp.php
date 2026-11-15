<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsAnItp;
use App\Media\MediaDownloads;
use App\Media\MediaLibrary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A personal ITP's page (brief 1 §5), in the same template as a universal one: the attunement,
 * its Breathe 11 and its Quantum Whys, and its audio where the device has it. A local row on the
 * phone, the shared database on the web. Only its owner sees it; anyone else gets a 404.
 */
#[Layout('layouts.app')]
#[Title('Your ITP')]
class ShowItp extends Component
{
    use ShowsAnItp;

    public Itp $itp;

    public function mount(Itp $itp): void
    {
        abort_unless($itp->user_id === Auth::id(), 404);

        $this->itp = $itp;
    }

    public function render(MediaLibrary $media, MediaDownloads $downloads): View
    {
        return view('livewire.show-itp', $this->itpPage($media, $downloads));
    }

    protected function shownItp(): UniversalItp|Itp
    {
        return $this->itp;
    }

    protected function canBeLivedWith(): bool
    {
        return true;
    }

    protected function isLockedForGuest(): bool
    {
        return false;
    }
}
