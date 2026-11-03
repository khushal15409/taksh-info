<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Shared `orders` table. The website places and pays orders; vendors, riders and admin move them along in their own apps. */
class Order extends Model
{
    protected $fillable = [
        'checkout_group_id', 'user_id', 'warehouse_id', 'vendor_id', 'address_id', 'order_number', 'delivery_type', 'is_express',
        'order_status', 'order_type', 'confirmed_at', 'stock_committed_at',
        // what the customer paid, kept on the order so refunds never add it up again
        'items_total', 'taksh_charge_total', 'platform_fee', 'quick_delivery_fee', 'discount_total', 'grand_total',
    ];

    protected function casts(): array
    {
        return [
            'is_express' => 'boolean',
            'estimated_delivery_time' => 'datetime',
            'confirmed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'stock_committed_at' => 'datetime',
        ];
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OrderCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(DeliveryMan::class);
    }

    public function deliveryLocation(): HasOne
    {
        return $this->hasOne(DeliveryLocation::class);
    }

    public function refundTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->where('type', 'credit');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }
}
