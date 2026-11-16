<?php

namespace App\Livewire\Concerns;

use App\Media\MediaDownloads;
use App\Media\MediaDownloadStatus;
use App\Media\MediaLibrary;
use App\Support\GuestChoice;
use App\Support\LivingWith;
use App\Support\PlayLink;
use App\Support\Runtime;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;

/**
 * What an ITP's page shows (player contract item 4), for a universal ITP and a user's own alike:
 * whether it can be made the person's ITP, and its three pieces (the ITP, its Breathe 11, a
 * Quantum Why), each a link into the player or disabled with the reason. The words themselves are
 * in the player. Rendered by `<x-itp-page>`.
 */
trait ShowsAnItp
{
    /**
     * The ITP this page shows.
     */
    abstract protected function shownItp(): UniversalItp|Itp;

    /**
     * Whether a guest must sign in before they can play.
     */
    abstract protected function isLockedForGuest(): bool;

    /**
     * Make this the person's ITP until they change it. A guest may choose an open (preview) one,
     * kept on this device until they sign in.
     */
    public function makeThisMyItp(): void
    {
        $itp = $this->shownItp();

        abort_unless(LivingWith::canBeLivedWith($itp), 403);

        if (Auth::check()) {
            LivingWith::choose(Auth::user(), $itp);

            return;
        }

        abort_unless($itp instanceof UniversalItp && GuestChoice::isOpenTo($itp), 403);

        GuestChoice::choose($itp);
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
            'choice' => $locked ? null : $this->choice($itp),
            'pieces' => $locked ? [] : $this->pieces($itp, $media, $downloads),
            'lockedForGuest' => $locked,
        ];
    }

    /**
     * `offer` (Make this my ITP), `chosen` (This is your ITP.) or null where it can't be chosen:
     * a primer or the Introduction, or a Universal a guest can't choose before sign-in.
     *
     * @return 'offer'|'chosen'|null
     */
    protected function choice(UniversalItp|Itp $itp): ?string
    {
        $canChoose = Auth::check()
            ? LivingWith::canBeLivedWith($itp)
            : $itp instanceof UniversalItp && GuestChoice::isOpenTo($itp);

        return match (true) {
            ! $canChoose => null,
            LivingWith::is(Auth::user(), $itp) => 'chosen',
            default => 'offer',
        };
    }

    /**
     * The pieces the ITP has, in order. A piece it has nothing of is left out (a primer or the
     * Introduction has no Breathe 11 or Whys). A piece with `url` null is shown disabled, `meta`
     * saying why.
     *
     * @return list<array{piece: 'itp'|'breathe'|'why', label: string, url: string|null, meta: string}>
     */
    protected function pieces(UniversalItp|Itp $itp, MediaLibrary $media, MediaDownloads $downloads): array
    {
        $pieces = [$this->voiced($itp, 'itp', LivingWith::canBeLivedWith($itp) ? 'ITP' : 'Play', '3 min', $itp->attunement_audio_path, $itp->attunement_timings, $media, $downloads)];

        if (filled($itp->breath11_lines) || filled($itp->breath11_timings) || $itp->breath11_audio_path !== null) {
            $pieces[] = $this->voiced($itp, 'breathe', 'Breathe 11', '2 min', $itp->breath11_audio_path, $itp->breath11_timings, $media, $downloads);
        }

        if (filled($itp->quantum_whys)) {
            $pieces[] = ['piece' => 'why', 'label' => 'Quantum Why', 'url' => PlayLink::for($itp, 'why'), 'meta' => 'a moment'];
        }

        return $pieces;
    }

    /**
     * A piece played to its voice: it needs its audio on this device and the timings that keep
     * the words or the breath in step with it.
     *
     * @param  'itp'|'breathe'  $piece
     * @param  array<int, mixed>|null  $timings
     * @return array{piece: 'itp'|'breathe', label: string, url: string|null, meta: string}
     */
    protected function voiced(UniversalItp|Itp $itp, string $piece, string $label, string $length, ?string $key, ?array $timings, MediaLibrary $media, MediaDownloads $downloads): array
    {
        $reason = match (true) {
            $key === null, blank($timings) => 'no voice yet',
            $media->url($key) !== null => null,
            Auth::guest() => 'after sign-in',
            Runtime::isMobile() && $downloads->status($key) === MediaDownloadStatus::Failed => 'waiting for a connection',
            default => 'downloading…',
        };

        return [
            'piece' => $piece,
            'label' => $label,
            'url' => $reason === null ? PlayLink::for($itp, $piece) : null,
            'meta' => $reason ?? $length,
        ];
    }
}
