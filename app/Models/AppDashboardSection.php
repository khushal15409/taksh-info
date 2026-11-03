<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A home-screen section configured in taksh-admin (Settings > App dashboard). */
class AppDashboardSection extends Model
{
    public const DASHBOARD_STANDARD = 'standard';
    public const DASHBOARD_QUICK = 'quick';
    public const DASHBOARD_BOTH = 'both';

    /** Layout templates a section can be drawn with (key => admin label). No design = the normal row. */
    public const DESIGNS = [
        'premium' => 'Premium Items (gold, dark card)',
        'hot_deals' => 'Hot Deals (red-orange header)',
        'fresh_picks' => 'Fresh Picks (green)',
        'spotlight' => 'Spotlight (big first product)',
        'minimal' => 'Minimal Cards (clean, no frame)',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** Sections shown on one home tab; 'both' sections show on each. */
    public function scopeForDashboard($query, string $type)
    {
        return $query->whereIn('dashboard_type', [$type, self::DASHBOARD_BOTH]);
    }

    /** Layout fields sent with each section. */
    public function apiConfig(): array
    {
        return [
            'id' => $this->id,
            'design' => array_key_exists((string) $this->design, self::DESIGNS) ? $this->design : null,
            'rows' => $this->rows ? (int) $this->rows : null,
            'items_per_row' => $this->items_per_row ? (int) $this->items_per_row : null,
        ];
    }

    /** How many products the section needs: the normal 20, or more for a big rows x items layout (max 100). */
    public function productLimit(): int
    {
        return max(20, min((int) $this->rows * (int) $this->items_per_row, 100));
    }
}
