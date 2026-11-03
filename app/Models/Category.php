<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasMediaUrls;

    public const DELIVERY_STANDARD = 'standard_delivery';
    public const DELIVERY_EXPRESS = 'express_30';

    /**
     * Categories offered for one delivery type: a parent whose type is that type (or none = both), and the
     * sub-categories under such a parent. A Standard parent and everything under it never shows for Quick.
     */
    public function scopeForDeliveryType($query, string $type)
    {
        $offered = fn ($q, string $col) => $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, $type));

        return $query->where(function ($q) use ($offered) {
            $q->where(fn ($p) => $p->whereNull('categories.parent_id')->where(fn ($w) => $offered($w, 'categories.delivery_types')))
                ->orWhereIn('categories.parent_id', function ($sub) use ($offered) {
                    $sub->from('categories as pc')->select('pc.id')->whereNull('pc.parent_id')
                        ->where(fn ($w) => $offered($w, 'pc.delivery_types'));
                });
        });
    }

    public function getImageUrlAttribute($value): ?string
    {
        return $this->getImageUrl($value, 'category');
    }

    public function getIconUrlAttribute($value): ?string
    {
        return $this->getImageUrl($value, 'category');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sectionMappings(): HasMany
    {
        return $this->hasMany(CategorySectionMapping::class);
    }
}
