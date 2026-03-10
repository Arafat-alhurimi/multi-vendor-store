<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class ViewPromotion extends ViewRecord
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncPromotion')
                ->label('مزامنة العرض')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('promotions:deactivate-expired');

                    Notification::make()
                        ->title('تمت مزامنة العرض بنجاح')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('الهوية')
                    ->schema([
                        ImageEntry::make('image')
                            ->label('صورة العرض')
                            ->disk('s3')
                            ->height(220)
                            ->columnSpanFull(),
                        TextEntry::make('title')
                            ->label('اسم العرض')
                            ->weight('bold')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('تفاصيل العرض')
                    ->schema([
                        TextEntry::make('level')
                            ->label('المستوى')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'app' ? 'عرض تطبيق' : 'عرض متجر')
                            ->color(fn (string $state): string => $state === 'app' ? 'primary' : 'success'),
                        TextEntry::make('discount_type')
                            ->label('نوع الخصم')
                            ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة مئوية' : 'قيمة ثابتة'),
                        TextEntry::make('discount_value')
                            ->label('قيمة الخصم'),
                        TextEntry::make('starts_at')
                            ->label('تاريخ البدء')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-'),
                        TextEntry::make('ends_at')
                            ->label('تاريخ الانتهاء')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-'),
                        TextEntry::make('is_active')
                            ->label('حالة التفعيل')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('إحصائيات الاستفادة من العرض')
                    ->schema([
                        TextEntry::make('participating_stores_count')
                            ->label('عدد المتاجر المشاركة')
                            ->state(fn (): int => $this->resolveTargetStoreIds($this->getRecord())->count()),
                        TextEntry::make('participating_products_count')
                            ->label('عدد المنتجات المشاركة')
                            ->state(fn (): int => $this->resolveTargetProductIds($this->getRecord())->count()),
                        TextEntry::make('benefited_cart_additions_count')
                            ->label('إضافات السلة المستفيدة')
                            ->state(fn (): int => $this->countBenefitedCartAdditions($this->getRecord())),
                        TextEntry::make('benefited_orders_count')
                            ->label('الطلبات المستفيدة')
                            ->state(fn (): int => $this->countBenefitedOrders($this->getRecord())),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private function resolveTargetProductIds(Promotion $promotion): Collection
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

    private function resolveTargetStoreIds(Promotion $promotion): Collection
    {
        $items = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->approved()
            ->get(['promotable_type', 'promotable_id', 'store_id']);

        if ($items->isEmpty()) {
            return collect();
        }

        $directStoreIds = $items
            ->filter(fn (PromotionItem $item): bool => $item->promotable_type === Store::class)
            ->pluck('promotable_id');

        $explicitStoreIds = $items->pluck('store_id')->filter();

        $storeIdsFromProducts = Product::query()
            ->whereIn('id', $this->resolveTargetProductIds($promotion)->all())
            ->pluck('store_id');

        return $directStoreIds
            ->merge($explicitStoreIds)
            ->merge($storeIdsFromProducts)
            ->filter()
            ->unique()
            ->values();
    }

    private function countBenefitedCartAdditions(Promotion $promotion): int
    {
        $productIds = $this->resolveTargetProductIds($promotion);

        if ($productIds->isEmpty()) {
            return 0;
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with('variants:id,product_id,price')
            ->get(['id', 'base_price']);

        return (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedCartAdditions($product, $promotion));
    }

    private function countBenefitedOrders(Promotion $promotion): int
    {
        $productIds = $this->resolveTargetProductIds($promotion);

        if ($productIds->isEmpty()) {
            return 0;
        }

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with('variants:id,product_id,price')
            ->get(['id', 'base_price']);

        return (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedOrders($product, $promotion));
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

    private function applyPromotionDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculated = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculated, 0);
    }
}
