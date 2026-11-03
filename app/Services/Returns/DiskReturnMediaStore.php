<?php

namespace App\Services\Returns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Same folder scheme as taksh-admin's FileUploadService (returns/{ddmmyy}/{prefix}_{time}_{random}.{ext}), on the configured disk. */
class DiskReturnMediaStore implements ReturnMediaStore
{
    public function put(UploadedFile $file): string
    {
        $disk = (string) config('taksh.media_disk');
        $name = 'return_'.time().'_'.Str::random(10).'.'.($file->guessExtension() ?: $file->getClientOriginalExtension());
        $dir = 'returns/'.now()->format('dmy');

        $path = Storage::disk($disk)->putFileAs($dir, $file, $name, in_array($disk, ['gcs', 's3'], true) ? 'public' : []);

        if (! $path) {
            throw new \RuntimeException('Could not store the file');
        }

        return $path;
    }
}
