<?php

namespace App\Api;

use App\Api\Exceptions\ApiUnavailable;
use App\Api\Exceptions\AppUpdateRequired;
use App\Api\Exceptions\NotFound;
use App\Api\Exceptions\SignedOut;
use Illuminate\Validation\ValidationException;

/**
 * The generation endpoints on itp-server (platform.md §6). Generation always runs on the server
 * and always needs a connection, on both builds. Every method returns the API's Generation
 * object (or a list of them) as an array; keys that don't apply to its status are absent.
 */
class GenerationClient extends ApiClient
{
    /**
     * The user's generations, newest first, with the summary keys only.
     *
     * @return list<array{id: string, status: string, created_at: string, updated_at: string, itp_id: ?string}>
     *
     * @throws AppUpdateRequired|SignedOut|ApiUnavailable
     */
    public function index(): array
    {
        return $this->send('GET', 'generations', authenticated: true)['generations'] ?? [];
    }

    /**
     * Start a generation from the intake answers.
     *
     * @param  array<string, string|null>  $intake
     * @return array<string, mixed>
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|ApiUnavailable
     */
    public function create(array $intake): array
    {
        return $this->send('POST', 'generations', $intake, authenticated: true)['generation'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AppUpdateRequired|SignedOut|NotFound|ApiUnavailable
     */
    public function find(string $id): array
    {
        return $this->send('GET', $this->path($id), authenticated: true)['generation'];
    }

    /**
     * Answer the open round of follow-up questions.
     *
     * @param  list<array{followup_id: int, answer_text: string|null}>  $answers
     * @return array<string, mixed>
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|NotFound|ApiUnavailable
     */
    public function answerFollowups(string $id, array $answers): array
    {
        return $this->send('POST', $this->path($id, 'followups'), ['answers' => $answers], authenticated: true)['generation'];
    }

    /**
     * Say a section of the attunement doesn't resonate, optionally pointing at the line that
     * bothered the user most. The server rewrites the whole section, then offers the new version
     * next to the original. A refusal without field errors (e.g. the section already has a
     * proposal) lands on `section`.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|NotFound|ApiUnavailable
     */
    public function flagSection(string $generationId, int $part, string $explanation, ?string $requestedChange, ?int $segmentId): array
    {
        return $this->send('POST', $this->path($generationId, "sections/{$part}/flags"), [
            'user_explanation' => $explanation,
            'requested_change' => $requestedChange,
            'segment_id' => $segmentId,
        ], authenticated: true, errorField: 'section')['generation'];
    }

    /**
     * Choose between a rewritten section and the original.
     *
     * @param  'new'|'original'  $keep
     * @return array<string, mixed>
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|NotFound|ApiUnavailable
     */
    public function chooseSection(string $generationId, int $part, string $keep): array
    {
        return $this->send('POST', $this->path($generationId, "sections/{$part}/choice"), [
            'keep' => $keep,
        ], authenticated: true, errorField: 'section')['generation'];
    }

    /**
     * Accept the attunement as it is. The server then derives the companions and the title.
     * A refusal (e.g. a rewritten section still waiting for a choice) lands on `accept`.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException|AppUpdateRequired|SignedOut|NotFound|ApiUnavailable
     */
    public function accept(string $id): array
    {
        return $this->send('POST', $this->path($id, 'accept'), authenticated: true, errorField: 'accept')['generation'];
    }

    protected function path(string $id, string $suffix = ''): string
    {
        return rtrim('generations/'.rawurlencode($id).'/'.$suffix, '/');
    }
}
