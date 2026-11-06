<?php

namespace App\Http\Controllers;

use App\Media\MediaLibrary;
use App\Support\Runtime;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves audio the phone has downloaded, by object key. Bundled files are served from public/
 * through asset() instead. The web build has no downloads, so this is a 404 there.
 */
class MediaController extends Controller
{
    public function __invoke(MediaLibrary $media, string $key): BinaryFileResponse
    {
        abort_unless(Runtime::isMobile() && $media->isDownloaded($key), 404);

        return response()->file($media->downloads()->path($key));
    }
}
