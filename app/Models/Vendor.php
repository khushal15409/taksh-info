<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vendor extends Model
{
    public function pincodes(): BelongsToMany
    {
        return $this->belongsToMany(Pincode::class, 'vendor_pincode', 'vendor_id', 'pincode_id');
    }

    /**
     * Does this vendor deliver (standard delivery) to the pincode? The rule behind the product page's delivery
     * check, the checkout check and order placement. A vendor whose service area was never configured (no
     * pincode mapped) is not restricted; one with a service area delivers only to its active mapped pincodes.
     */
    public function servesPincode(?string $pincode): bool
    {
        if (! $this->pincodes()->exists()) {
            return true;
        }

        return $this->pincodes()
            ->where('pincodes.status', true)
            ->where('pincodes.pincode', trim((string) $pincode))
            ->exists();
    }
}
