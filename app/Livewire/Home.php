<?php

namespace App\Livewire;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\GenerationClient;
use App\Api\GenerationStatus;
use App\Livewire\Concerns\CallsApi;
use App\Media\MediaDownloads;
use App\Media\MediaLibrary;
use App\Support\Runtime;
use App\Sync\GuestContentPull;
use App\Sync\SyncNow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The first tab, and the app's start URL, so guests land here too.
 *
 * Everyone sees the universal ITPs. Guests can open the previews; the others are locked behind
 * sign-in. Signed in, the user also sees their own ITPs, a way to create one, and a way back
 * into a generation that is still running or waiting on them. Rows are local on the phone and
 * the shared database on the web. Syncing (or, signed out, the public content pull) and asking
 * the API happen after the page shows (`wire:init`), and again from the refresh control; when
 * the API can't be reached the page simply shows what it already has. On the phone, signed in,
 * missing universal audio then downloads one file per poll.
 */
#[Layout('layouts.app')]
#[Title('Home')]
class Home extends Component
{
    use CallsApi;

    /**
     * The newest generation still running or waiting on the user, if any.
     *
     * @var array{id: string, status: string}|null
     */
    #[Locked]
    public ?array $openGeneration = null;

    public function refresh(SyncNow $sync, GuestContentPull $content, MediaDownloads $downloads, GenerationClient $generations): void
    {
        if (Auth::guest()) {
            $content->run();
            $downloads->prune();

            return;
        }

        $sync->run(forceGlobal: true);
        $downloads->prune();
        $downloads->retryFailed();

        $this->callApi(function () use ($generations): void {
            try {
                $list = $generations->index();
            } catch (ApiUnavailable) {
                return;
            }

            $open = collect($list)->first(function (array $generation): bool {
                $status = GenerationStatus::tryFrom($generation['status'] ?? '');

                return $status !== null && ($status->isInProgress() || $status->isWaitingOnUser());
            });

            $this->openGeneration = $open === null ? null : ['id' => $open['id'], 'status' => $open['status']];
        });
    }

    /**
     * Fetch the next missing audio file. Polled while any are pending, so each request stays
     * short. Guests download nothing: the previews' audio is bundled.
     */
    public function downloadNext(MediaDownloads $downloads): void
    {
        if (Auth::check()) {
            $downloads->downloadNext();
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.home', [
            'user' => $user,
            'itps' => $user?->itps()->latest()->orderByDesc('id')->get(['id', 'title', 'description', 'created_at']),
            'openStatus' => GenerationStatus::tryFrom($this->openGeneration['status'] ?? ''),
            'universals' => $this->universals(),
            'media' => app(MediaLibrary::class),
            'downloadsPending' => Runtime::isMobile() && $user !== null && app(MediaDownloads::class)->hasPending(),
        ]);
    }

    /**
     * Base pieces (the Introduction) first, then the universals in their Studio order.
     *
     * @return Collection<int, UniversalItp>
     */
    protected function universals(): Collection
    {
        return UniversalItp::query()
            ->orderByRaw('case when kind = ? then 0 else 1 end', [UniversalItpKind::Base->value])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'kind', 'sort_order', 'title', 'short_name', 'description', 'image_path', 'is_preview']);
    }
}
