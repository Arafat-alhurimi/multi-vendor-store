<?php

namespace App\Filament\Resources\Products\Widgets;

use App\Models\Product;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalProducts = Product::query()->count();
        $productsLastWeek = Product::query()->where('created_at', '>=', now()->subWeek())->count();
        $productsLastMonth = Product::query()->where('created_at', '>=', now()->subMonth())->count();

        $productsWithRelations = Product::query()
            ->with([
                'subcategory.category',
                'store',
                'variants' => fn ($query) => $query->withCount(['orders', 'cartItems']),
            ])
            ->withAvg('ratings', 'value')
            ->withCount(['orders', 'cartItems', 'favorites'])
            ->get();

        $mostOrderedProduct = $productsWithRelations
            ->sortByDesc(fn (Product $product): int =>
                (int) $product->orders_count
                + (int) $product->variants->sum('orders_count')
            )
            ->first();

        $mostCartedProduct = $productsWithRelations
            ->sortByDesc(fn (Product $product): int =>
                (int) $product->cart_items_count
                + (int) $product->variants->sum('cart_items_count')
            )
            ->first();

        $mostOrderedCount = $mostOrderedProduct
            ? (int) $mostOrderedProduct->orders_count + (int) $mostOrderedProduct->variants->sum('orders_count')
            : null;

        $mostCartedCount = $mostCartedProduct
            ? (int) $mostCartedProduct->cart_items_count + (int) $mostCartedProduct->variants->sum('cart_items_count')
            : null;

        $mostFavoritedProduct = $productsWithRelations
            ->sortByDesc(fn (Product $product): int => (int) $product->favorites_count)
            ->first();

        $highestRatedProduct = $productsWithRelations
            ->whereNotNull('ratings_avg_value')
            ->sortByDesc(fn (Product $product): float => (float) $product->ratings_avg_value)
            ->first();

        $mostProductsSubcategory = Subcategory::query()
            ->with('category')
            ->withCount('products')
            ->orderByDesc('products_count')
            ->first();

        $mostProductsStore = Store::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->first();

        $latestProduct = Product::query()->latest('created_at')->first();

        return [
            Stat::make('عدد كل المنتجات', (string) $totalProducts),
            Stat::make(
                'أكثر منتج طلبًا',
                $mostOrderedProduct
                    ? $mostOrderedProduct->name_ar.' ('.$mostOrderedCount.')'
                    : '-'
            ),
            Stat::make(
                'أكثر منتج إضافة للسلة',
                $mostCartedProduct
                    ? $mostCartedProduct->name_ar.' ('.$mostCartedCount.')'
                    : '-'
            ),
            Stat::make(
                'أكثر منتج لديه تفضيلات',
                $mostFavoritedProduct
                    ? $mostFavoritedProduct->name_ar.' ('.(int) $mostFavoritedProduct->favorites_count.')'
                    : '-'
            ),
            Stat::make(
                'أعلى منتج تقييمًا',
                $highestRatedProduct
                    ? $highestRatedProduct->name_ar.' ('.number_format((float) $highestRatedProduct->ratings_avg_value, 2).')'
                    : '-'
            ),
            Stat::make(
                'أكثر قسم فرعي فيه منتجات',
                $mostProductsSubcategory
                    ? $mostProductsSubcategory->name_ar.' ('.$mostProductsSubcategory->category?->name_ar.')'
                    : '-'
            )
                ->description($mostProductsSubcategory ? 'عدد المنتجات: '.$mostProductsSubcategory->products_count : ''),
            Stat::make(
                'أكثر متجر لديه منتجات',
                $mostProductsStore
                    ? $mostProductsStore->name.' ('.(int) $mostProductsStore->products_count.')'
                    : '-'
            ),
            Stat::make(
                'آخر منتج تم إضافته',
                $latestProduct?->name_ar ?? '-'
            )
                ->description($latestProduct?->created_at?->diffForHumans() ?? ''),
            Stat::make('عدد منتجات آخر أسبوع', (string) $productsLastWeek),
            Stat::make('عدد منتجات آخر شهر', (string) $productsLastMonth),
        ];
    }
}
