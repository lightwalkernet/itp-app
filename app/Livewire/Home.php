<?php

namespace App\Livewire;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\GenerationClient;
use App\Api\GenerationStatus;
use App\Livewire\Concerns\CallsApi;
use App\Sync\SyncNow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The first tab: the user's ITPs, a way to create one, and a way back into a generation that is
 * still running or waiting on them. The ITPs are local rows on the phone and the shared
 * database on the web. Syncing and asking the API happen after the page shows (`wire:init`),
 * and again from the refresh control; when the API can't be reached the page simply shows what
 * it already has.
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

    public function refresh(SyncNow $sync, GenerationClient $generations): void
    {
        $sync->run();

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

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.home', [
            'user' => $user,
            'itps' => $user->itps()->latest()->orderByDesc('id')->get(['id', 'title', 'description', 'created_at']),
            'openStatus' => GenerationStatus::tryFrom($this->openGeneration['status'] ?? ''),
        ]);
    }
}
