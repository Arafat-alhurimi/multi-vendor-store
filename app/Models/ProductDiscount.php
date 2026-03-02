<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDiscount extends Model
{
    use HasFactory;

    protected $appends = [
        'effective_is_active',
    ];

    protected $fillable = [
        'product_id',
        'type',
        'value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeCurrentlyActive(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $dateTime = $at ?? now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $innerQuery) use ($dateTime): void {
                $innerQuery->whereNull('starts_at')->orWhere('starts_at', '<=', $dateTime);
            })
            ->where(function (Builder $innerQuery) use ($dateTime): void {
                $innerQuery->whereNull('ends_at')->orWhere('ends_at', '>=', $dateTime);
            });
    }

    public function isEffectivelyActive(?CarbonInterface $at = null): bool
    {
        $dateTime = $at ?? now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->gt($dateTime)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($dateTime)) {
            return false;
        }

        return true;
    }

    public function getEffectiveIsActiveAttribute(): bool
    {
        return $this->isEffectivelyActive();
    }
}
