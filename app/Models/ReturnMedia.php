<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;

class ReturnMedia extends Model
{
    use HasMediaUrls;

    protected $table = 'return_media';

    protected $fillable = ['return_id', 'file_path', 'file_type', 'mime_type', 'file_size'];

    public function getFileUrlAttribute(): ?string
    {
        return $this->getImageUrl($this->file_path, 'returns');
    }
}
