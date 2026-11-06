<?php

namespace App\Media;

enum MediaDownloadStatus: string
{
    case Downloading = 'downloading';

    case Downloaded = 'downloaded';

    /**
     * Tried and failed; retried on the next Home load.
     */
    case Failed = 'failed';
}
