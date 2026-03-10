<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class JoinedPromotionsRelationManager extends RelationManager
{
    protected static string $relationship = 'joinedPromotionItems';

    protected static ?string $title = 'عروض انضم لها المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['promotion', 'promotable']))
            ->recordAction('viewDetails')
            ->recordUrl(null)
            ->columns([
                Tables\Columns\TextColumn::make('promotion.title')
                    ->label('اسم العرض')
                    ->searchable(),

                Tables\Columns\TextColumn::make('promotable_type')
                    ->label('النوع')
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            Product::class => 'منتج',
                            Category::class => 'قسم رئيسي',
                            Subcategory::class => 'قسم فرعي',
                            Store::class => 'متجر كامل',
                            default => class_basename($state),
                        };
                    }),

                Tables\Columns\TextColumn::make('promotable_name')
                    ->label('الاسم')
                    ->state(fn (PromotionItem $record): string => $this->resolvePromotableName($record)),

                Tables\Columns\TextColumn::make('target_products_count')
                    ->label('عدد المنتجات ضمن الطلب')
                    ->state(fn (PromotionItem $record): int => $this->resolveTargetProductIds($record)->count()),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'approved' => 'مشارك',
                        'rejected' => 'مرفوض',
                        default => '-',
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('viewDetails')
                    ->label('تفاصيل الطلب')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('7xl')
                    ->modalHeading(fn (PromotionItem $record): string => $record->status === 'approved' ? 'تفاصيل المشاركة' : 'تفاصيل الطلب')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->action(function (PromotionItem $record, array $arguments): void {
                        $decision = $arguments['decision'] ?? null;

                        if ($decision === 'approve') {
                            if ($this->hasActiveConflict($record)) {
                                Notification::make()
                                    ->title('تعذر الموافقة')
                                    ->body('لا يمكن الموافقة لأن النطاق المستهدف يحتوي منتجات منضمة لعروض نشطة أخرى.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $record->update(['status' => 'approved']);

                            Notification::make()
                                ->title('تم قبول الطلب')
                                ->success()
                                ->send();

                            return;
                        }

                        if ($decision === 'cancel_participation') {
                            $record->update(['status' => 'rejected']);

                            Notification::make()
                                ->title('تم إلغاء المشاركة')
                                ->warning()
                                ->send();

                            return;
                        }

                        if ($decision === 'reject') {
                            $record->update(['status' => 'rejected']);

                            Notification::make()
                                ->title('تم رفض الطلب')
                                ->warning()
                                ->send();
                        }
                    })
                    ->extraModalFooterActions(function (Action $action, PromotionItem $record): array {
                        if ($record->status === 'approved') {
                            return [
                                $action->makeModalSubmitAction('cancelParticipationFromModal', ['decision' => 'cancel_participation'])
                                    ->label('إلغاء المشاركة')
                                    ->color('danger'),
                            ];
                        }

                        return [
                            $action->makeModalSubmitAction('approveFromModal', ['decision' => 'approve'])
                                ->label('قبول')
                                ->color('success')
                                ->visible(fn (PromotionItem $item): bool => $item->status !== 'approved'),
                            $action->makeModalSubmitAction('rejectFromModal', ['decision' => 'reject'])
                                ->label('رفض')
                                ->color('danger')
                                ->visible(fn (PromotionItem $item): bool => $item->status !== 'rejected'),
                        ];
                    })
                    ->modalContent(fn (PromotionItem $record) => view('filament.promotions.request-details-modal', [
                        'mode' => $record->status === 'approved' ? 'participant' : 'request',
                        'record' => $record,
                        'summary' => $this->resolveRequestSummary($record),
                        'products' => $this->resolveTargetProductsWithMetrics($record),
                    ])),

                Action::make('approve')
                    ->label('قبول')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionItem $record): bool => $record->status === 'pending')
                    ->action(function (PromotionItem $record): void {
                        if ($this->hasActiveConflict($record)) {
                            Notification::make()
                                ->title('تعذر الموافقة')
                                ->body('لا يمكن الموافقة لأن النطاق المستهدف يحتوي منتجات منضمة لعروض نشطة أخرى.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => 'approved']);
                    }),

                Action::make('reject')
                    ->label('رفض')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionItem $record): bool => $record->status === 'pending')
                    ->action(fn (PromotionItem $record) => $record->update(['status' => 'rejected'])),
            ]);
    }

    public function getTabs(): array
    {
        $baseQuery = $this->getOwnerRecord()
            ->joinedPromotionItems()
            ->whereHas('promotion', fn (Builder $query): Builder => $query->where('level', 'app'));

        return [
            'participants' => Tab::make('المشاركات في العروض')
                ->badge((string) (clone $baseQuery)->where('status', 'approved')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'approved')
                    ->whereHas('promotion', fn (Builder $promotionQuery): Builder => $promotionQuery->where('level', 'app'))),
            'join_requests' => Tab::make('طلبات الانضمام')
                ->badge((string) (clone $baseQuery)->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', 'pending')
                    ->whereHas('promotion', fn (Builder $promotionQuery): Builder => $promotionQuery->where('level', 'app'))),
        ];
    }

    private function resolvePromotableName(PromotionItem $promotionItem): string
    {
        $promotable = $promotionItem->promotable;

        if (! $promotable) {
            return '-';
        }

        if ($promotable instanceof Product) {
            return (string) $promotable->name_ar;
        }

        if ($promotable instanceof Category) {
            return (string) $promotable->name_ar;
        }

        if ($promotable instanceof Store) {
            return (string) $promotable->name;
        }

        if ($promotable instanceof Subcategory) {
            return (string) $promotable->name_ar;
        }

        return (string) $promotionItem->promotable_id;
    }

    private function hasActiveConflict(PromotionItem $item): bool
    {
        $productIds = $this->resolveTargetProductIds($item);

        if ($productIds->isEmpty()) {
            return false;
        }

        $productMeta = Product::query()
            ->with('subcategory:id,category_id')
            ->whereIn('id', $productIds->all())
            ->get(['id', 'store_id', 'subcategory_id']);

        $storeIds = $productMeta->pluck('store_id')->filter()->unique()->values()->all();
        $subcategoryIds = $productMeta->pluck('subcategory_id')->filter()->unique()->values()->all();
        $categoryIds = $productMeta->pluck('subcategory.category_id')->filter()->unique()->values()->all();

        return Promotion::query()
            ->currentlyActive(now())
            ->whereKeyNot($item->promotion_id)
            ->whereHas('items', function ($query) use ($productIds, $storeIds, $subcategoryIds, $categoryIds): void {
                $query
                    ->approved()
                    ->where(function ($matchQuery) use ($productIds, $storeIds, $subcategoryIds, $categoryIds): void {
                        $matchQuery->where(function ($directProducts) use ($productIds): void {
                            $directProducts
                                ->where('promotable_type', Product::class)
                                ->whereIn('promotable_id', $productIds->all());
                        });

                        if (! empty($storeIds)) {
                            $matchQuery->orWhere(function ($storeItems) use ($storeIds): void {
                                $storeItems
                                    ->where('promotable_type', Store::class)
                                    ->whereIn('promotable_id', $storeIds);
                            });
                        }

                        if (! empty($subcategoryIds)) {
                            $matchQuery->orWhere(function ($subcategoryItems) use ($subcategoryIds): void {
                                $subcategoryItems
                                    ->where('promotable_type', Subcategory::class)
                                    ->whereIn('promotable_id', $subcategoryIds);
                            });
                        }

                        if (! empty($categoryIds)) {
                            $matchQuery->orWhere(function ($categoryItems) use ($categoryIds): void {
                                $categoryItems
                                    ->where('promotable_type', Category::class)
                                    ->whereIn('promotable_id', $categoryIds);
                            });
                        }
                    });
            })
            ->exists();
    }

    private function resolveTargetProductIds(PromotionItem $item): Collection
    {
        $storeId = $item->store_id;

        if ($item->promotable_type === Product::class) {
            return Product::query()
                ->whereKey($item->promotable_id)
                ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
                ->pluck('id');
        }

        if ($item->promotable_type === Store::class) {
            return Product::query()
                ->where('store_id', $item->promotable_id)
                ->pluck('id');
        }

        if ($item->promotable_type === Subcategory::class) {
            return Product::query()
                ->where('subcategory_id', $item->promotable_id)
                ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
                ->pluck('id');
        }

        if ($item->promotable_type === Category::class) {
            return Product::query()
                ->whereHas('subcategory', function ($query) use ($item): void {
                    $query->where('category_id', $item->promotable_id);
                })
                ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
                ->pluck('id');
        }

        return collect();
    }

    private function resolveTargetProducts(PromotionItem $item): Collection
    {
        return Product::query()
            ->with(['store:id,name', 'variants:id,product_id,price', 'subcategory:id,name_ar,category_id', 'subcategory.category:id,name_ar'])
            ->whereIn('id', $this->resolveTargetProductIds($item)->all())
            ->get(['id', 'store_id', 'subcategory_id', 'name_ar', 'base_price', 'stock']);
    }

    private function resolveRequestSummary(PromotionItem $item): array
    {
        $productIds = $this->resolveTargetProductIds($item);
        $isParticipant = $item->status === 'approved';
        $promotion = $item->promotion;

        if (! $promotion) {
            return [
                'products_count' => $productIds->count(),
                'cart_additions_count' => 0,
                'orders_count' => 0,
            ];
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with('variants:id,product_id,price')
            ->get(['id', 'base_price']);

        return [
            'products_count' => $productIds->count(),
            'cart_additions_count' => $isParticipant ? (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedCartAdditions($product, $promotion)) : 0,
            'orders_count' => $isParticipant ? (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedOrders($product, $promotion)) : 0,
        ];
    }

    private function resolveTargetProductsWithMetrics(PromotionItem $item): Collection
    {
        $promotion = $item->promotion;

        if (! $promotion) {
            return collect();
        }

        return $this->resolveTargetProducts($item)
            ->map(function (Product $product) use ($promotion): array {
                $basePrice = (float) $product->base_price;
                $afterPrice = $this->applyPromotionDiscount($basePrice, (string) $promotion->discount_type, (float) $promotion->discount_value);
                $cartCount = $this->countProductBenefitedCartAdditions($product, $promotion);
                $ordersCount = $this->countProductBenefitedOrders($product, $promotion);

                return [
                    'id' => $product->id,
                    'name_ar' => $product->name_ar,
                    'store_name' => $product->store?->name,
                    'category_name' => $product->subcategory?->category?->name_ar,
                    'subcategory_name' => $product->subcategory?->name_ar,
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'after_price' => number_format($afterPrice, 2, '.', ''),
                    'stock' => (int) $product->stock,
                    'cart_additions_count' => $cartCount,
                    'orders_count' => $ordersCount,
                ];
            })
            ->values();
    }

    private function countProductBenefitedCartAdditions(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            $this->applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    $this->applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) $product->cartItems()
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

    private function countProductBenefitedOrders(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            $this->applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    $this->applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) $product->orders()
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

    private function applyPromotionDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculated = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculated, 0);
    }
}
