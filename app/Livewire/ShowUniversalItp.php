<?php

namespace App\Livewire;

use App\Livewire\Concerns\ShowsAnItp;
use App\Media\MediaDownloads;
use App\Media\MediaLibrary;
use App\Support\LivingWith;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A universal ITP's page (brief 1 §5): its words, its Breathe 11 and Quantum Whys, and its audio
 * where the device has it. Everyone can read every one; guests can listen to the previews only.
 * The player arrives in brief 2; until then Enter plays a plain audio element.
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

    /**
     * The Universals, and Meet Your ITP Self. Not a primer (for a moment, not a day), and not the
     * other base pieces such as the Introduction.
     */
    protected function canBeLivedWith(): bool
    {
        return $this->universalItp->kind === UniversalItpKind::Universal
            || $this->universalItp->slug === LivingWith::DEFAULT_SLUG;
    }

    protected function isLockedForGuest(): bool
    {
        return Auth::guest() && ! $this->universalItp->is_preview;
    }
}
