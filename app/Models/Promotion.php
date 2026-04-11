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
        return $query->where('is_active', true);
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
        return (bool) $this->is_active;
    }

    public function getEffectiveIsActiveAttribute(): bool
    {
        return $this->isEffectivelyActive();
    }
}
