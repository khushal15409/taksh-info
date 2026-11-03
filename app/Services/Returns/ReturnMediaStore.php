<?php

namespace App\Services\Returns;

use Illuminate\Http\UploadedFile;

interface ReturnMediaStore
{
    /** Store an uploaded file under returns/{ddmmyy}/ and return its path (what taksh-admin keeps in return_media.file_path). */
    public function put(UploadedFile $file): string;
}
