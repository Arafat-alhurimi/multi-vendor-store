<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    private array $columnCache = [];

    public function checkAvailability(Product $product, ?ProductVariant $variant, int $quantity, bool $lockForUpdate = false): bool
    {
        if ($quantity < 1) {
            return false;
        }

        return $this->resolveCurrentStock($product, $variant, $lockForUpdate) >= $quantity;
    }

    public function decrementStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['الكمية المطلوبة غير صالحة.'],
            ]);
        }

        if ($variant) {
            $stockColumn = $this->hasColumn('product_variants', 'stock_quantity') ? 'stock_quantity' : 'stock';

            $lockedVariant = ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->first();

            if (! $lockedVariant || (int) $lockedVariant->{$stockColumn} < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['الكمية المطلوبة غير متوفرة في المخزون.'],
                ]);
            }

            $lockedVariant->{$stockColumn} = (int) $lockedVariant->{$stockColumn} - $quantity;
            $lockedVariant->save();

            return;
        }

        $stockColumn = $this->hasColumn('products', 'base_stock') ? 'base_stock' : 'stock';

        $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->first();

        if (! $lockedProduct || (int) $lockedProduct->{$stockColumn} < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['الكمية المطلوبة غير متوفرة في المخزون.'],
            ]);
        }

        $lockedProduct->{$stockColumn} = (int) $lockedProduct->{$stockColumn} - $quantity;
        $lockedProduct->save();
    }

    private function resolveCurrentStock(Product $product, ?ProductVariant $variant, bool $lockForUpdate): int
    {
        if ($variant) {
            $stockColumn = $this->hasColumn('product_variants', 'stock_quantity') ? 'stock_quantity' : 'stock';

            $query = ProductVariant::query()->whereKey($variant->id);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $freshVariant = $query->first();

            return (int) ($freshVariant?->{$stockColumn} ?? 0);
        }

        $stockColumn = $this->hasColumn('products', 'base_stock') ? 'base_stock' : 'stock';

        $query = Product::query()->whereKey($product->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $freshProduct = $query->first();

        return (int) ($freshProduct?->{$stockColumn} ?? 0);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;

        if (! array_key_exists($cacheKey, $this->columnCache)) {
            $this->columnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return $this->columnCache[$cacheKey];
    }
}
