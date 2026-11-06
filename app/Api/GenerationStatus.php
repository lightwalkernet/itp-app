<?php

namespace App\Api;

/**
 * A generation's status, as itp-server sends it (its `ItpStatus`, plain strings on the wire).
 *
 * queued → [classifying] → assessing → awaiting_followup ⇄ assessing → generating
 * → awaiting_resonance ⇄ regenerating → deriving_companions → complete.
 * complete, deferred, blocked and failed are terminal or held.
 */
enum GenerationStatus: string
{
    case Queued = 'queued';
    case Classifying = 'classifying';
    case Assessing = 'assessing';
    case AwaitingFollowup = 'awaiting_followup';
    case Generating = 'generating';
    case AwaitingResonance = 'awaiting_resonance';
    case Regenerating = 'regenerating';
    case DerivingCompanions = 'deriving_companions';
    case Complete = 'complete';
    case Deferred = 'deferred';
    case Blocked = 'blocked';
    case Failed = 'failed';

    /**
     * The server is working: the app keeps polling.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::Queued,
            self::Classifying,
            self::Assessing,
            self::Generating,
            self::Regenerating,
            self::DerivingCompanions,
        ], true);
    }

    /**
     * The server is waiting for the user's answers or their verdict on the attunement.
     */
    public function isWaitingOnUser(): bool
    {
        return in_array($this, [self::AwaitingFollowup, self::AwaitingResonance], true);
    }

    /**
     * Ended without an ITP, or held for review. The API's `message` explains it.
     */
    public function isStopped(): bool
    {
        return in_array($this, [self::Deferred, self::Blocked, self::Failed], true);
    }

    /**
     * A short, calm line for each working stage.
     */
    public function workingText(): string
    {
        return match ($this) {
            self::Queued => 'Getting started…',
            self::Classifying, self::Assessing => 'Taking in what you shared…',
            self::Generating => 'Writing your attunement…',
            self::Regenerating => 'Rewriting the part that didn’t resonate…',
            self::DerivingCompanions => 'Creating your Breathe 11 and Quantum Whys…',
            default => 'Working on it…',
        };
    }
}
