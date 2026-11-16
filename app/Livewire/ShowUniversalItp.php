<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsAnItp;
use App\Media\MediaDownloads;
use App\Media\MediaLibrary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A universal ITP's page (player contract item 4): its name and description, Make this my ITP,
 * and its pieces into the player. Everyone can open every one; guests can play and choose the
 * previews only, and are asked to sign in for the rest.
 */
#[Layout('layouts.app')]
class ShowUniversalItp extends Component
{
    use ShowsAnItp;

    public UniversalItp $universalItp;

    public function mount(UniversalItp $universalItp): void
    {
        $this->universalItp = $universalItp;
    }

    public function render(MediaLibrary $media, MediaDownloads $downloads): View
    {
        $page = $this->itpPage($media, $downloads);

        return view('livewire.show-universal-itp', [
            ...$page,
            'openToGuests' => $page['lockedForGuest']
                ? UniversalItp::query()->where('kind', UniversalItpKind::Universal)->where('is_preview', true)->count()
                : null,
        ])->title($this->universalItp->display_name);
    }

    protected function shownItp(): UniversalItp|Itp
    {
        return $this->universalItp;
    }

    protected function isLockedForGuest(): bool
    {
        return Auth::guest() && ! $this->universalItp->is_preview;
    }
}
