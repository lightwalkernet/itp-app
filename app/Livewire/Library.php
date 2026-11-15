<?php

namespace App\Livewire;

use App\Media\MediaDownloads;
use App\Media\MediaLibrary;
use App\Support\LivingWith;
use App\Support\NumberWords;
use App\Support\Runtime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Itp\Core\Enums\UniversalItpKind;
use Itp\Core\Models\UniversalItp;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Your ITPs (brief 1 §4): everything a person can enter, in three tabs. Universal is every
 * universal ITP (guests see the non-previews locked; the page itself handles the lock), Personal
 * is the user's own (guests are invited to sign in), Primers are the short pieces for a moment.
 * The tab is kept in the URL. On the phone, signed in, missing audio downloads one file per poll.
 */
#[Layout('layouts.app')]
#[Title('Your ITPs')]
class Library extends Component
{
    public const array TABS = ['universal', 'personal', 'primers'];

    /**
     * The primers the design names, in order. Until a row exists for a slug its row shows as
     * coming soon, never another ITP in its place.
     *
     * @var array<string, array{name: string, meta: string}>
     */
    public const array PRIMERS = [
        'return-to-coherence' => ['name' => 'Return to Coherence', 'meta' => 'for right now · 2 min'],
        'highest-possible-outcome' => ['name' => 'Highest Possible Outcome', 'meta' => 'before something that matters · 3 min'],
    ];

    #[Url]
    public string $tab = 'universal';

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'universal';
    }

    /**
     * Fetch the next missing audio file (see Home). Guests download nothing: the previews' audio
     * is bundled.
     */
    public function downloadNext(MediaDownloads $downloads): void
    {
        if (Auth::check()) {
            $downloads->downloadNext();
        }
    }

    public function render(MediaLibrary $media, MediaDownloads $downloads): View
    {
        $user = Auth::user();
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'universal';
        $universals = $this->universals(UniversalItpKind::Universal);
        $primers = $this->universals(UniversalItpKind::Primer);

        return view('livewire.library', [
            'user' => $user,
            'currentTab' => $tab,
            'media' => $media,
            'universals' => $universals,
            'universalCount' => $universals->count(),
            'universalNote' => $this->universalNote($universals, $user === null),
            'itps' => $user?->itps()->latest()->orderByDesc('id')->get(['id', 'user_id', 'title', 'image_path', 'created_at']),
            'livingWith' => LivingWith::for($user),
            'primerRows' => $this->primerRows($primers),
            'primerCount' => $primers->count(),
            'downloadsPending' => Runtime::isMobile() && $user !== null && $downloads->hasPending(),
        ]);
    }

    /**
     * @return Collection<int, UniversalItp>
     */
    protected function universals(UniversalItpKind $kind): Collection
    {
        return UniversalItp::query()
            ->where('kind', $kind)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'slug', 'kind', 'sort_order', 'title', 'short_name', 'image_path', 'is_preview']);
    }

    /**
     * *Thirteen, for everyone.* with the count from the data, then the line for who is looking.
     *
     * @param  Collection<int, UniversalItp>  $universals
     */
    protected function universalNote(Collection $universals, bool $isGuest): string
    {
        $all = NumberWords::spellCapitalised($universals->count()).', for everyone.';

        if ($isGuest) {
            $open = NumberWords::spellCapitalised($universals->where('is_preview', true)->count());

            return "{$all} {$open} are open before you sign in; the rest wait for you after.";
        }

        return "{$all} Live with one for a while, or just listen to one today — that doesn’t change what you’re living with.";
    }

    /**
     * The named primers first (a real row when it exists, else coming soon), then any other.
     *
     * @param  Collection<int, UniversalItp>  $primers
     * @return list<array{name: string, meta: string, itp: UniversalItp|null}>
     */
    protected function primerRows(Collection $primers): array
    {
        $named = collect(self::PRIMERS)->map(function (array $primer, string $slug) use ($primers): array {
            $itp = $primers->firstWhere('slug', $slug);

            return [
                'name' => $itp?->display_name ?? $primer['name'],
                'meta' => $itp === null ? 'coming soon' : $primer['meta'],
                'itp' => $itp,
            ];
        });

        $others = $primers
            ->reject(fn (UniversalItp $itp): bool => array_key_exists($itp->slug, self::PRIMERS))
            ->map(fn (UniversalItp $itp): array => ['name' => $itp->display_name, 'meta' => $itp->title, 'itp' => $itp]);

        return $named->values()->concat($others->values())->all();
    }
}
