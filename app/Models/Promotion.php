<?php

namespace App\Models;

use Carbon\CarbonInterface;
use App\Models\PromotionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $appends = [
        'effective_is_active',
    ];

    protected $fillable = [
        'title',
        'image',
        'level',
        'store_id',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
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

    public function scopeAppLevel(Builder $query): Builder
    {
        return $query->where('level', 'app');
    }

    public function scopeStoreLevel(Builder $query): Builder
    {
        return $query->where('level', 'store');
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
