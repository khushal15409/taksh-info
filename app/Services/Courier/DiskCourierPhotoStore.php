<?php

namespace App\Services\Courier;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Same folder scheme as taksh-admin's FileUploadService (courier-bookings/items/{ddmmyy}/item_{time}_{random}.{ext}), on the configured disk. */
class DiskCourierPhotoStore implements CourierPhotoStore
{
    public function put(UploadedFile $file): string
    {
        $disk = (string) config('taksh.media_disk');
        $name = 'item_'.time().'_'.Str::random(10).'.'.($file->guessExtension() ?: $file->getClientOriginalExtension());

        $path = Storage::disk($disk)->putFileAs('courier-bookings/items/'.now()->format('dmy'), $file, $name, in_array($disk, ['gcs', 's3'], true) ? 'public' : []);

        if (! $path) {
            throw new \RuntimeException('Could not store the file');
        }

        return $path;
    }
}
