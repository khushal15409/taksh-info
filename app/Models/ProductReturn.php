<?php

namespace App\Models;

use App\Enums\ReturnResolution;
use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Shared `returns` table: a customer's request to send part of an order line back. */
class ProductReturn extends Model
{
    protected $table = 'returns';

    protected $fillable = ['order_id', 'order_item_id', 'product_id', 'customer_id', 'vendor_id', 'reason', 'resolution', 'status', 'requested_at'];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'resolution' => ReturnResolution::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'qc_checked_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReturnMedia::class, 'return_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ReturnStatusLog::class, 'return_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class, 'return_id');
    }

    /** How many pieces this request sends back (an older return saved none, so the whole line is assumed). */
    public function returnedQty(): int
    {
        $qty = (int) $this->items->sum('quantity');
        $line = (int) ($this->orderItem->qty ?? 0);

        return $qty > 0 ? min($qty, $line ?: $qty) : $line;
    }
}
