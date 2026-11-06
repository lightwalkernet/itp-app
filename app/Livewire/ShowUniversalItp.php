<?php

namespace App\Livewire;

use App\Media\MediaDownloads;
use App\Media\MediaDownloadStatus;
use App\Media\MediaLibrary;
use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A universal ITP: its text, its Breathe 11 and Quantum Whys, and its audio where the device
 * has it. Guests may open the previews only; any other sends them to sign in. The player is
 * ported from itpmobile later; for now each audio is a plain audio element.
 */
#[Layout('layouts.app')]
class ShowUniversalItp extends Component
{
    public UniversalItp $universalItp;

    public function mount(UniversalItp $universalItp): void
    {
        $this->universalItp = $universalItp;

        if (Auth::guest() && ! $universalItp->is_preview) {
            $this->redirectRoute('sign-in');
        }
    }

    public function render(MediaLibrary $media, MediaDownloads $downloads): View
    {
        $itp = $this->universalItp;

        return view('livewire.show-universal-itp', [
            'imageUrl' => $media->url($itp->image_path),
            'paragraphs' => array_values(array_filter(
                array_map('trim', preg_split('/\R{2,}/', (string) $itp->attunement_text)),
                fn (string $paragraph): bool => $paragraph !== '',
            )),
            'breathLines' => collect($itp->breath11_lines ?? [])->sortBy('order')->values(),
            'quantumWhys' => $itp->quantum_whys ?? [],
            'audios' => array_values(array_filter([
                $this->audio('Attunement', $itp->attunement_audio_path, $media, $downloads),
                $this->audio('Breathe 11', $itp->breath11_audio_path, $media, $downloads),
            ])),
        ])->title($itp->display_name);
    }

    /**
     * One audio and where it stands: `available` (with its URL), `downloading` (signed in on the
     * phone, not fetched yet), `waiting` (the last download failed; retried from Home) or
     * `locked` (a guest, who downloads nothing).
     *
     * @return array{label: string, status: 'available'|'downloading'|'waiting'|'locked', url: string|null}|null
     */
    protected function audio(string $label, ?string $key, MediaLibrary $media, MediaDownloads $downloads): ?array
    {
        if ($key === null) {
            return null;
        }

        $url = $media->url($key);

        $status = match (true) {
            $url !== null => 'available',
            Auth::guest() => 'locked',
            Runtime::isMobile() && $downloads->status($key) === MediaDownloadStatus::Failed => 'waiting',
            default => 'downloading',
        };

        return ['label' => $label, 'status' => $status, 'url' => $url];
    }
}
