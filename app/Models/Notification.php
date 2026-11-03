<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A notification written in the admin panel (shared `notifications` table). The website only reads it. */
class Notification extends Model
{
    use SoftDeletes;

    /** Categories the customer apps understand. */
    public const TYPES = [
        'offer' => 'Offer',
        'promotion' => 'Promotion',
        'order' => 'Order update',
        'delivery' => 'Delivery',
        'payment' => 'Payment',
        'review' => 'Review',
        'app' => 'App / general',
    ];

    protected $fillable = ['user_id', 'title', 'message', 'type', 'send_to', 'send_to_which_panel', 'status', 'scheduled_at', 'sent_at'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
