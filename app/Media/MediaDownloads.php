<?php

namespace App\Media;

use App\Support\Runtime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Itp\Core\Models\Itp;
use Itp\Core\Models\UniversalItp;
use RuntimeException;

/**
 * The phone's audio download queue (platform.md §7). Every audio key a local ITP row references
 * that the app neither bundles nor has downloaded is fetched from `itp.media_url`, one file per
 * call, so no request runs long. An audio file is never replaced in place: a new recording
 * arrives under a new key, so a key the phone doesn't have is a new download, and a file no row
 * references any more is deleted.
 */
class MediaDownloads
{
    /**
     * Where a download is written before it is moved under its key, on the same disk.
     */
    protected const PARTIAL_DIRECTORY = '.partial';

    public function __construct(protected MediaLibrary $media) {}

    /**
     * Every audio key a local ITP references.
     *
     * @return list<string>
     */
    public function referencedKeys(): array
    {
        $columns = ['attunement_audio_path', 'breath11_audio_path'];

        return collect([UniversalItp::query()->get($columns), Itp::query()->get($columns)])
            ->flatten(1)
            ->flatMap(fn ($row): array => [$row->attunement_audio_path, $row->breath11_audio_path])
            ->filter(fn (?string $key): bool => $key !== null && MediaLibrary::isSafeKey($key))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Referenced keys still to fetch: not bundled, not downloaded, and not failed since the last
     * retry.
     *
     * @return list<string>
     */
    public function pendingKeys(): array
    {
        $failed = MediaDownload::query()->where('status', MediaDownloadStatus::Failed)->pluck('key')->all();

        return array_values(array_filter(
            $this->referencedKeys(),
            fn (string $key): bool => ! in_array($key, $failed, true)
                && ! $this->media->isBundled($key)
                && ! $this->media->isDownloaded($key),
        ));
    }

    public function hasPending(): bool
    {
        return $this->pendingKeys() !== [];
    }

    /**
     * Where a key stands, for a key the phone can't play yet: null when it has never been tried.
     */
    public function status(string $key): ?MediaDownloadStatus
    {
        return MediaDownload::query()->where('key', $key)->first()?->status;
    }

    /**
     * Download the next pending file, after clearing out files nothing references. Only one call
     * runs at a time; an overlapping call (a second poll) returns straight away.
     *
     * @return string|null the key downloaded or attempted, null when nothing ran
     */
    public function downloadNext(): ?string
    {
        if (Runtime::isServer()) {
            return null;
        }

        return $this->exclusively(function (): ?string {
            $this->removeUnreferenced();

            $key = $this->pendingKeys()[0] ?? null;

            if ($key !== null) {
                $this->download($key);
            }

            return $key;
        });
    }

    /**
     * Give failed downloads another go, e.g. when Home loads again with a connection.
     */
    public function retryFailed(): void
    {
        if (Runtime::isServer()) {
            return;
        }

        MediaDownload::query()->where('status', MediaDownloadStatus::Failed)->delete();
    }

    /**
     * Delete downloaded files, unfinished downloads and queue rows that no local row references.
     */
    public function prune(): void
    {
        if (Runtime::isServer()) {
            return;
        }

        $this->exclusively(fn () => $this->removeUnreferenced());
    }

    protected function removeUnreferenced(): void
    {
        $referenced = $this->referencedKeys();
        $disk = $this->media->downloads();

        foreach ($disk->allFiles() as $path) {
            if (! in_array($path, $referenced, true)) {
                $disk->delete($path);
            }
        }

        MediaDownload::query()->whereNotIn('key', $referenced)->delete();
    }

    /**
     * Stream the file to a temporary name, then move it under its key, so a half-written file is
     * never played.
     */
    protected function download(string $key): void
    {
        $disk = $this->media->downloads();
        $partial = self::PARTIAL_DIRECTORY.'/'.Str::random(20);
        $disk->makeDirectory(self::PARTIAL_DIRECTORY);

        $download = MediaDownload::query()->updateOrCreate(
            ['key' => $key],
            ['status' => MediaDownloadStatus::Downloading, 'bytes' => null, 'error' => null],
        );

        try {
            Http::sink($disk->path($partial))
                ->connectTimeout(10)
                ->timeout(300)
                ->get($this->media->remoteUrl($key))
                ->throw();

            $bytes = (int) $disk->size($partial);

            if ($bytes === 0) {
                throw new RuntimeException('The download was empty.');
            }

            if (! $disk->move($partial, $key)) {
                throw new RuntimeException('The download could not be saved.');
            }

            $download->update(['status' => MediaDownloadStatus::Downloaded, 'bytes' => $bytes]);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $disk->delete($partial);
            $download->update(['status' => MediaDownloadStatus::Failed, 'error' => Str::limit($exception->getMessage(), 500)]);

            Log::warning("Media download failed for [{$key}]: ".$exception->getMessage());
        }
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult|null
     */
    protected function exclusively(callable $callback): mixed
    {
        $lock = Cache::lock('media:downloads', 600);

        if (! $lock->get()) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
