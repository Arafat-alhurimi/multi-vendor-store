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
        return $query->where('is_active', true);
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
