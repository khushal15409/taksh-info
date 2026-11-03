<?php

namespace App\Services\Courier;

use Illuminate\Http\UploadedFile;

interface CourierPhotoStore
{
    /** Store the parcel photo under courier-bookings/items/{ddmmyy}/ and return its path. */
    public function put(UploadedFile $file): string;
}
