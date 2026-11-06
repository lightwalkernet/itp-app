<?php

namespace App\Media;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One audio file the phone has tried to download, keyed by its object key. A device-only table
 * (platform.md §4): never synced.
 *
 * @property string $key
 * @property MediaDownloadStatus $status
 * @property int|null $bytes
 * @property string|null $error
 */
#[Fillable(['key', 'status', 'bytes', 'error'])]
class MediaDownload extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MediaDownloadStatus::class,
            'bytes' => 'integer',
        ];
    }
}
