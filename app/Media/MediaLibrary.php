<?php

namespace App\Media;

use App\Support\Runtime;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a media object key (e.g. `media/audio/universals/activations/1-awaken-wonder.mp3`) into
 * a URL the page can play or show (platform.md §7):
 *
 * - bundled in public/: `asset()`, which the phone serves under its `_assets` prefix;
 * - downloaded on the phone: the `media.show` route, by path;
 * - otherwise the web build fetches it from `itp.media_url`, and the phone has nothing yet.
 */
class MediaLibrary
{
    /**
     * @return string|null null when the phone hasn't got the file (yet)
     */
    public function url(?string $key): ?string
    {
        if ($key === null || ! self::isSafeKey($key)) {
            return null;
        }

        if ($this->isBundled($key)) {
            return asset($key);
        }

        if (Runtime::isServer()) {
            return $this->remoteUrl($key);
        }

        return $this->isDownloaded($key) ? route('media.show', ['key' => $key], absolute: false) : null;
    }

    public function isBundled(string $key): bool
    {
        return self::isSafeKey($key) && is_file(public_path($key));
    }

    public function isDownloaded(string $key): bool
    {
        return self::isSafeKey($key) && $this->downloads()->exists($key);
    }

    /**
     * Where the file lives on R2's public domain.
     */
    public function remoteUrl(string $key): string
    {
        return rtrim((string) config('itp.media_url'), '/').'/'.implode('/', array_map(rawurlencode(...), explode('/', $key)));
    }

    /**
     * The phone's downloaded files, stored under their object keys.
     */
    public function downloads(): Filesystem
    {
        return Storage::disk('media');
    }

    /**
     * A relative path with no empty segment and none starting with a dot (`..`, hidden files,
     * unfinished downloads), so it can never leave the folder it is resolved against.
     */
    public static function isSafeKey(string $key): bool
    {
        if ($key === '' || str_contains($key, "\0") || str_contains($key, '\\')) {
            return false;
        }

        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return false;
            }
        }

        return true;
    }
}
