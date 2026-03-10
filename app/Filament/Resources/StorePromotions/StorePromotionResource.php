<?php

namespace App\Filament\Resources\StorePromotions;

use App\Filament\Resources\StorePromotions\Pages\ListStorePromotions;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StorePromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $modelLabel = 'عرض متجر';

    protected static ?string $pluralModelLabel = 'عروض المتاجر';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'عروض المتاجر';

    protected static string | \UnitEnum | null $navigationGroup = 'العروض';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction('viewDetails')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('store.name')
                    ->label('المتجر')
                    ->placeholder('-')
                    ->searchable(),

                ImageColumn::make('image')
                    ->label('صورة العرض')
                    ->disk('s3')
                    ->circular()
                    ->defaultImageUrl(null),

                TextColumn::make('title')
                    ->label('اسم العرض')
                    ->searchable(),

                TextColumn::make('target_details')
                    ->label('على ماذا؟')
                    ->state(fn (Promotion $record): string => static::resolvePromotionTargetDetails($record))
                    ->wrap(),

                TextColumn::make('products_in_offer_count')
                    ->label('عدد المنتجات')
                    ->state(fn (Promotion $record): int => static::resolvePromotionTargetProductIds($record)->count()),

                TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                BadgeColumn::make('discount_type')
                    ->label('نوع الخصم')
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة مئوية' : 'قيمة ثابتة')
                    ->colors([
                        'info' => 'percentage',
                        'primary' => 'fixed',
                    ]),

                TextColumn::make('discount_value')
                    ->label('قيمة الخصم')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('currently_running')
                    ->label('ساري الآن')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->where('is_active', true)
                            ->where(function (Builder $inner): void {
                                $inner->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                            })
                            ->where(function (Builder $inner): void {
                                $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                            }),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $outer): void {
                            $outer
                                ->where('is_active', false)
                                ->orWhere('starts_at', '>', now())
                                ->orWhere('ends_at', '<', now());
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('discount_type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'قيمة ثابتة',
                    ]),
                Filter::make('created_this_week')
                    ->label('مضافة هذا الأسبوع')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])),
                Filter::make('starts_between')
                    ->label('تاريخ البدء')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('starts_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('starts_at', '<=', $date));
                    }),
                Filter::make('ends_between')
                    ->label('تاريخ الانتهاء')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('من'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('ends_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('ends_at', '<=', $date));
                    }),
            ])
            ->defaultSort('starts_at', 'desc')
            ->actions([
                Action::make('viewDetails')
                    ->label('تفاصيل العرض')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('تفاصيل عرض المتجر')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Promotion $record) => view('filament.store-promotions.promotion-details-modal', [
                        'promotion' => $record,
                        'summary' => static::resolvePromotionDetailsSummary($record),
                        'products' => static::resolvePromotionTargetProductsWithMetrics($record),
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorePromotions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('level', 'store');
    }

    private static function resolvePromotionTargetDetails(Promotion $promotion): string
    {
        $items = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->approved()
            ->with('promotable')
            ->get(['promotable_type', 'promotable_id', 'store_id']);

        if ($items->isEmpty()) {
            return 'غير محدد';
        }

        $labels = $items->map(function (PromotionItem $item): string {
            $target = $item->promotable;

            return match ($item->promotable_type) {
                Store::class => 'متجر كامل: ' . ($target?->name ?? ('#' . $item->promotable_id)),
                Category::class => 'قسم رئيسي: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                Subcategory::class => 'قسم فرعي: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                Product::class => 'منتج: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                default => class_basename((string) $item->promotable_type) . ': #' . $item->promotable_id,
            };
        })->unique()->values();

        if ($labels->count() <= 2) {
            return $labels->implode(' | ');
        }

        return $labels->take(2)->implode(' | ') . ' ... +' . ($labels->count() - 2);
    }

    private static function resolvePromotionTargetProductIds(Promotion $promotion): Collection
    {
        $items = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->approved()
            ->get(['promotable_type', 'promotable_id', 'store_id']);

        if ($items->isEmpty()) {
            return collect();
        }

        $productIds = collect();

        foreach ($items as $item) {
            if ($item->promotable_type === Product::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->whereKey($item->promotable_id)
                        ->when($item->store_id, fn (Builder $query): Builder => $query->where('store_id', $item->store_id))
                        ->pluck('id')
                );

                continue;
            }

            if ($item->promotable_type === Store::class) {
                $productIds = $productIds->merge(
                    Product::query()->where('store_id', $item->promotable_id)->pluck('id')
                );

                continue;
            }

            if ($item->promotable_type === Subcategory::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->where('subcategory_id', $item->promotable_id)
                        ->when($item->store_id, fn (Builder $query): Builder => $query->where('store_id', $item->store_id))
                        ->pluck('id')
                );

                continue;
            }

            if ($item->promotable_type === Category::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->whereHas('subcategory', fn (Builder $query): Builder => $query->where('category_id', $item->promotable_id))
                        ->when($item->store_id, fn (Builder $query): Builder => $query->where('store_id', $item->store_id))
                        ->pluck('id')
                );
            }
        }

        return $productIds->filter()->unique()->values();
    }

    private static function resolvePromotionTargetProductsWithMetrics(Promotion $promotion): Collection
    {
        $productIds = static::resolvePromotionTargetProductIds($promotion);

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with(['variants:id,product_id,price', 'subcategory:id,name_ar,category_id', 'subcategory.category:id,name_ar'])
            ->whereIn('id', $productIds->all())
            ->get(['id', 'subcategory_id', 'name_ar', 'base_price', 'stock'])
            ->map(function (Product $product) use ($promotion): array {
                $basePrice = (float) $product->base_price;
                $afterPrice = static::applyPromotionDiscount($basePrice, (string) $promotion->discount_type, (float) $promotion->discount_value);

                return [
                    'id' => $product->id,
                    'name_ar' => $product->name_ar,
                    'category_name' => $product->subcategory?->category?->name_ar,
                    'subcategory_name' => $product->subcategory?->name_ar,
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'after_price' => number_format($afterPrice, 2, '.', ''),
                    'stock' => (int) $product->stock,
                    'cart_additions_count' => static::countProductBenefitedCartAdditions($product, $promotion),
                    'orders_count' => static::countProductBenefitedOrders($product, $promotion),
                ];
            })
            ->values();
    }

    private static function resolvePromotionDetailsSummary(Promotion $promotion): array
    {
        $productIds = static::resolvePromotionTargetProductIds($promotion);

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with('variants:id,product_id,price')
            ->get(['id', 'base_price']);

        return [
            'products_count' => $products->count(),
            'cart_additions_count' => (int) $products->sum(fn (Product $product): int => static::countProductBenefitedCartAdditions($product, $promotion)),
            'orders_count' => (int) $products->sum(fn (Product $product): int => static::countProductBenefitedOrders($product, $promotion)),
        ];
    }

    private static function countProductBenefitedCartAdditions(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            static::applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    static::applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) CartItem::query()
            ->where('product_id', $product->id)
            ->where(function (Builder $query) use ($baseOfferPrice, $variantOfferPrices): void {
                $query->where(function (Builder $baseQuery) use ($baseOfferPrice): void {
                    $baseQuery
                        ->whereNull('product_variation_id')
                        ->where('price_at_add', $baseOfferPrice);
                });

                foreach ($variantOfferPrices as $variantId => $offerPrice) {
                    $query->orWhere(function (Builder $variantQuery) use ($variantId, $offerPrice): void {
                        $variantQuery
                            ->where('product_variation_id', $variantId)
                            ->where('price_at_add', $offerPrice);
                    });
                }
            })
            ->when($promotion->starts_at, fn (Builder $query): Builder => $query->where('created_at', '>=', $promotion->starts_at))
            ->when($promotion->ends_at, fn (Builder $query): Builder => $query->where('created_at', '<=', $promotion->ends_at))
            ->count();
    }

    private static function countProductBenefitedOrders(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            static::applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    static::applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) Order::query()
            ->where('product_id', $product->id)
            ->where(function (Builder $query) use ($baseOfferPrice, $variantOfferPrices): void {
                $query->where(function (Builder $baseQuery) use ($baseOfferPrice): void {
                    $baseQuery
                        ->whereNull('product_variation_id')
                        ->where('unit_price', $baseOfferPrice);
                });

                foreach ($variantOfferPrices as $variantId => $offerPrice) {
                    $query->orWhere(function (Builder $variantQuery) use ($variantId, $offerPrice): void {
                        $variantQuery
                            ->where('product_variation_id', $variantId)
                            ->where('unit_price', $offerPrice);
                    });
                }
            })
            ->when($promotion->starts_at, fn (Builder $query): Builder => $query->where('created_at', '>=', $promotion->starts_at))
            ->when($promotion->ends_at, fn (Builder $query): Builder => $query->where('created_at', '<=', $promotion->ends_at))
            ->count();
    }

    private static function applyPromotionDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculated = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculated, 0);
    }
}
