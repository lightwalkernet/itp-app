<?php

namespace App\Sync;

use Illuminate\Http\Client\PendingRequest;
use Lightwalker\Sync\Client\HttpTransport;
use Lightwalker\Sync\Exceptions\SyncFailed;
use LogicException;

/**
 * The sync package's HTTP transport, pointed at itp-server's public `content/pull`. Its answer
 * is the same as `sync/pull`'s, so the sync client applies it unchanged. Read-only: guests
 * never push.
 */
class ContentPullTransport extends HttpTransport
{
    /**
     * @throws LogicException
     */
    public function push(string $type, array $records): array
    {
        throw new LogicException('The public content pull is read-only.');
    }

    /**
     * @throws SyncFailed
     */
    public function pull(string $type, int $after, int $limit): array
    {
        $data = $this->send(fn (PendingRequest $request) => $request->get('content/pull', [
            'type' => $type,
            'after' => $after,
            'limit' => $limit,
        ]));

        if (! is_array($data['records'] ?? null) || ! isset($data['next_cursor'], $data['has_more'])) {
            throw new SyncFailed('The content server sent a malformed pull response.');
        }

        return [
            'records' => $data['records'],
            'next_cursor' => (int) $data['next_cursor'],
            'has_more' => (bool) $data['has_more'],
        ];
    }
}
