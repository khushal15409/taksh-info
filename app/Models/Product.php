<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected function casts(): array
    {
        return ['is_trending' => 'boolean', 'is_latest' => 'boolean', 'is_express_30' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function sectionMappings(): HasMany
    {
        return $this->hasMany(ProductSectionMapping::class);
    }

    /** Primary image, else the first one. */
    public function getImageAttribute(): ?string
    {
        return ($this->images->where('is_primary', true)->first() ?? $this->images->first())?->image_url;
    }
}
