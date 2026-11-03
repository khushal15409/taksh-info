<?php

namespace App\Traits;

use App\Services\MediaUrlService;

/** Same job as taksh-admin's HasImageUrls: expand a stored image path into a full URL. */
trait HasMediaUrls
{
    public function getImageUrl(?string $filename, string $folder): ?string
    {
        return app(MediaUrlService::class)->url($filename, $folder);
    }
}
