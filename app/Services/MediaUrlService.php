<?php

namespace App\Services;

/** Turns what taksh-admin stored for an image (a full URL, or a path/filename) into a URL the site can show. */
class MediaUrlService
{
    public function url(?string $value, string $folder = ''): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        $path = trim(($folder !== '' ? trim($folder, '/').'/' : '').trim($value, '/'), '/');

        return rtrim((string) config('taksh.media_url'), '/').'/'.$path;
    }
}
