<?php

namespace App\Models;

use App\Traits\HasMediaUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Shared `banners` table (managed in taksh-admin). */
class Banner extends Model
{
    use HasMediaUrls, SoftDeletes;

    public const TYPE_STANDARD = 'standard';
    public const TYPE_QUICK = 'quick';
    public const TYPE_SERVICE = 'service';
    public const TYPES = [self::TYPE_STANDARD, self::TYPE_QUICK, self::TYPE_SERVICE];

    public const DEVICE_DESKTOP = 'desktop';
    public const DEVICE_MOBILE = 'mobile';
    public const DEVICES = [self::DEVICE_DESKTOP, self::DEVICE_MOBILE];

    /** Image size (px) set per device in taksh-admin. */
    public const SIZES = [
        self::DEVICE_DESKTOP => ['width' => 1920, 'height' => 410],
        self::DEVICE_MOBILE => ['width' => 1080, 'height' => 540],
    ];

    /** {width, height} the image of this banner should have. */
    public function recommendedSize(): array
    {
        return self::SIZES[in_array($this->device_type, self::DEVICES, true) ? $this->device_type : self::DEVICE_MOBILE];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function getImageUrlAttribute($value): ?string
    {
        return $this->getImageUrl($value, 'banner');
    }

    /** Fulfillment centers the banner is limited to; none = a global banner. */
    public function fulfillmentCenters(): BelongsToMany
    {
        return $this->belongsToMany(FulfillmentCenter::class, 'banner_fc', 'banner_id', 'fulfillment_center_id');
    }

    /** For middle banners: the dashboard section the banner is shown below. */
    public function afterSection(): BelongsTo
    {
        return $this->belongsTo(AppDashboardSection::class, 'after_section_id');
    }
}
