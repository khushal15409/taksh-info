<?php

namespace App\Helpers;

/** asset() plus the file's modified time, so a browser never keeps running an old script/style after a deploy. */
class Asset
{
    public static function v(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
