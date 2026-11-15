<?php

namespace App\Livewire\Concerns;

use App\Media\MediaDownloads;
use App\Media\MediaDownloadStatus;
use App\Media\MediaLibrary;
use App\Support\LivingWith;
use App\Support\Runtime;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;

/**
 * What an ITP's page shows (brief 1 §5), for a universal ITP and a user's own alike: the words,
 * the eleven lines and the Whys, where its audio stands on this device, and whether it can be
 * chosen to live with. Rendered by `<x-itp-page>`.
 */
trait ShowsAnItp
{
    /**
     * The ITP this page shows.
     */
    abstract protected function shownItp(): UniversalItp|Itp;

    /**
     * Whether a person can choose this one to live with at all.
     */
    abstract protected function canBeLivedWith(): bool;

    /**
     * Whether a guest must sign in before they can listen.
     */
    abstract protected function isLockedForGuest(): bool;

    /**
     * Live with this one from now on. Never for a guest, nor for a piece that isn't lived with.
     */
    public function liveWithThis(): void
    {
        abort_unless(Auth::check() && $this->canBeLivedWith(), 403);

        LivingWith::choose(Auth::user(), $this->shownItp());
    }

    /**
     * @return array<string, mixed> the `<x-itp-page>` attributes
     */
    protected function itpPage(MediaLibrary $media, MediaDownloads $downloads): array
    {
        $itp = $this->shownItp();
        $locked = Auth::guest() && $this->isLockedForGuest();

        return [
            'itp' => $itp,
            'imageUrl' => $media->url($itp->image_path),
            'paragraphs' => array_values(array_filter(
                array_map('trim', preg_split('/\R{2,}/', (string) $itp->attunement_text)),
                fn (string $paragraph): bool => $paragraph !== '',
            )),
            'breathLines' => collect($itp->breath11_lines ?? [])->sortBy('order')->pluck('text')->values()->all(),
            'quantumWhys' => array_values($itp->quantum_whys ?? []),
            'enter' => $this->audio($itp->attunement_audio_path, $locked, $media, $downloads),
            'breathAudio' => $this->audio($itp->breath11_audio_path, $locked, $media, $downloads),
            'livingWith' => match (true) {
                Auth::guest(), ! $this->canBeLivedWith() => null,
                LivingWith::is(Auth::user(), $itp) => 'living',
                default => 'offer',
            },
            'lockedForGuest' => $locked,
        ];
    }

    /**
     * One audio and where it stands: `available` (with its URL), `downloading` (signed in on the
     * phone, not fetched yet), `waiting` (the last download failed; retried from Today) or
     * `locked` (a guest, who downloads nothing and plays only the previews).
     *
     * @return array{status: 'available'|'downloading'|'waiting'|'locked', url: string|null}|null
     */
    protected function audio(?string $key, bool $locked, MediaLibrary $media, MediaDownloads $downloads): ?array
    {
        if ($key === null) {
            return null;
        }

        $url = $locked ? null : $media->url($key);

        $status = match (true) {
            $url !== null => 'available',
            Auth::guest() => 'locked',
            Runtime::isMobile() && $downloads->status($key) === MediaDownloadStatus::Failed => 'waiting',
            default => 'downloading',
        };

        return ['status' => $status, 'url' => $url];
    }
}
