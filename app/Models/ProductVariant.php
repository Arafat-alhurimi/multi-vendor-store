<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (ProductVariant $variant): void {
            $variant->syncProductStockFromVariants();
        });

        static::deleted(function (ProductVariant $variant): void {
            $variant->syncProductStockFromVariants();
        });
    }

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'stock',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant')->withTimestamps();
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_variation_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'product_variation_id');
    }

    private function syncProductStockFromVariants(): void
    {
        $product = $this->product()->first();

        if (! $product) {
            return;
        }

        if (! $product->variants()->exists()) {
            return;
        }

        $product->update([
            'stock' => (int) $product->variants()->sum('stock'),
        ]);
    }
}
