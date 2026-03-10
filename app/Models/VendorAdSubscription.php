<?php

namespace App\Models;

use Carbon\CarbonInterface;
use App\Models\Ad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorAdSubscription extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (VendorAdSubscription $subscription): void {
            $isTransitionToActive = $subscription->status === 'active'
                && $subscription->getOriginal('status') !== 'active';

            if (! $isTransitionToActive) {
                return;
            }

            $approvedAt = now();
            $subscription->starts_at = $approvedAt;

            $package = $subscription->relationLoaded('adPackage')
                ? $subscription->adPackage
                : $subscription->adPackage()->first();

            if ($package && (int) $package->duration_days > 0) {
                $subscription->ends_at = $approvedAt->copy()->addDays((int) $package->duration_days);
            }
        });
    }

    protected $fillable = [
        'vendor_id',
        'ad_package_id',
        'starts_at',
        'ends_at',
        'status',
        'request_type',
        'used_images',
        'used_videos',
        'used_promotions',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'request_type' => 'string',
        'used_images' => 'integer',
        'used_videos' => 'integer',
        'used_promotions' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function adPackage(): BelongsTo
    {
        return $this->belongsTo(AdPackage::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    public function scopeActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $dateTime = $at ?? now();

        return $query
            ->where('status', 'active')
            ->where(function (Builder $innerQuery) use ($dateTime): void {
                $innerQuery->whereNull('starts_at')->orWhere('starts_at', '<=', $dateTime);
            })
            ->where(function (Builder $innerQuery) use ($dateTime): void {
                $innerQuery->whereNull('ends_at')->orWhere('ends_at', '>=', $dateTime);
            });
    }
}
