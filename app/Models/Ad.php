<?php

namespace App\Models;

use App\Models\Scopes\ActiveAdScope;
use App\Models\VendorAdSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'vendor_ad_subscription_id',
        'media_type',
        'media_path',
        'click_action',
        'action_id',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ActiveAdScope());
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VendorAdSubscription::class, 'vendor_ad_subscription_id');
    }
}
