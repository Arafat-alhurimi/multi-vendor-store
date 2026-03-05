<?php

namespace App\Models;

use App\Models\PromotionItem;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class Store extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Store $store): void {
            if (! $store->user_id) {
                return;
            }

            $alreadyHasStore = static::query()
                ->where('user_id', $store->user_id)
                ->exists();

            if ($alreadyHasStore) {
                throw ValidationException::withMessages([
                    'user_id' => 'لا يمكن إنشاء أكثر من متجر للبائع نفسه.',
                ]);
            }
        });

        static::saving(function (Store $store): void {
            if ($store->user_id && ! $store->user?->is_active) {
                $store->is_active = false;
            }
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'city',
        'address',
        'latitude',
        'longitude',
        'logo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * Get the user that owns the store.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the categories that belong to the store.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_store')->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            Product::class,
            'store_id',
            'product_id',
            'id',
            'id'
        );
    }

    public function cartItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            CartItem::class,
            Product::class,
            'store_id',
            'product_id',
            'id',
            'id'
        );
    }

    public function ownPromotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function promotionItems(): MorphMany
    {
        return $this->morphMany(PromotionItem::class, 'promotable');
    }

    public function vendorFinancialDetail(): HasOneThrough
    {
        return $this->hasOneThrough(
            VendorFinancialDetail::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id'
        );
    }

    /**
     * Get full S3 URL for the logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($this->logo);
    }

    /**
     * Check if the store can be activated.
     * Store can only be active if the user is active.
     */
    public function canBeActivated(): bool
    {
        return $this->user && $this->user->is_active;
    }

    /**
     * Automatically deactivate store if user is deactivated.
     */
    public function syncWithUserStatus(): void
    {
        if ($this->user && !$this->user->is_active && $this->is_active) {
            $this->update(['is_active' => false]);
        }
    }
}
