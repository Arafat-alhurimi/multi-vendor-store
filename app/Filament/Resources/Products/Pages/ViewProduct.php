<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Comment;
use App\Models\Category;
use App\Models\Product;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use App\Services\PriceService;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected ?array $appliedPromotionsCache = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('هوية المنتج')
                    ->schema([
                        ImageEntry::make('primary_image')
                            ->label('صورة المنتج')
                            ->state(fn (): ?string => $this->getRecord()->media()->where('file_type', 'image')->value('url'))
                            ->height(220)
                            ->columnSpanFull(),
                        TextEntry::make('name_ar')->label('الاسم (عربي)')->placeholder('-'),
                        TextEntry::make('name_en')->label('الاسم (EN)')->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('بيانات المنتج')
                    ->schema([
                        TextEntry::make('store.name')->label('المتجر')->placeholder('-'),
                        TextEntry::make('section_path')
                            ->label('القسم')
                            ->state(fn (): string => collect([
                                $this->getRecord()->subcategory?->category?->name_ar,
                                $this->getRecord()->subcategory?->name_ar,
                            ])->filter()->implode('>') ?: '-')
                            ->placeholder('-'),
                        TextEntry::make('base_price')->label('السعر الأساسي')->money('SAR'),
                        TextEntry::make('final_price')
                            ->label('السعر بعد العرض')
                            ->state(fn (): string => (string) $this->getRecord()->final_price)
                            ->money('SAR'),
                        TextEntry::make('stock')->label('المخزون الحالي'),
                        TextEntry::make('is_featured')
                            ->label('مميز')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا')
                            ->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                        TextEntry::make('is_active')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('description_ar')->label('الوصف (عربي)')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('description_en')->label('الوصف (EN)')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('إحصائيات المنتج')
                    ->schema([
                        TextEntry::make('variants_count')
                            ->label('عدد المنتجات الفرعية')
                            ->state(fn (): int => (int) $this->getRecord()->variants()->count()),
                        TextEntry::make('orders_total_count')
                            ->label('عدد الطلبات')
                            ->state(fn (): int =>
                                (int) $this->getRecord()->orders()->count()
                                + (int) $this->getRecord()->variants()->withCount('orders')->get()->sum('orders_count')
                            ),
                        TextEntry::make('cart_total_count')
                            ->label('إضافات السلة')
                            ->state(fn (): int =>
                                (int) $this->getRecord()->cartItems()->count()
                                + (int) $this->getRecord()->variants()->withCount('cartItems')->get()->sum('cart_items_count')
                            ),
                        TextEntry::make('favorites_count')
                            ->label('عدد المفضلة')
                            ->state(fn (): int => (int) $this->getRecord()->favorites()->count()),
                        TextEntry::make('comments_count')
                            ->label('عدد التعليقات')
                            ->state(fn (): int => (int) $this->getRecord()->comments()->count()),
                        TextEntry::make('avg_rating')
                            ->label('متوسط التقييم')
                            ->state(function (): ?string {
                                $avg = $this->getRecord()->ratings()->avg('value');

                                return $avg === null ? null : number_format((float) $avg, 2);
                            })
                            ->placeholder(''),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('رقابة المخزون')
                    ->schema([
                        TextEntry::make('estimated_initial_stock')
                            ->label('المخزون الابتدائي التقديري')
                            ->state(function (): int {
                                $product = $this->getRecord();

                                $soldQuantity =
                                    (int) $product->orders()->sum('quantity')
                                    + (int) $product->variants()->withSum('orders', 'quantity')->get()->sum('orders_sum_quantity');

                                return (int) $product->stock + $soldQuantity;
                            }),
                        TextEntry::make('current_stock')
                            ->label('المخزون الحالي')
                            ->state(fn (): int => (int) $this->getRecord()->stock),
                        TextEntry::make('sold_quantity')
                            ->label('إجمالي المبيع (طلبات)')
                            ->state(function (): int {
                                $product = $this->getRecord();

                                return
                                    (int) $product->orders()->sum('quantity')
                                    + (int) $product->variants()->withSum('orders', 'quantity')->get()->sum('orders_sum_quantity');
                            }),
                        TextEntry::make('in_cart_quantity')
                            ->label('الكمية الحالية في السلة')
                            ->state(function (): int {
                                $product = $this->getRecord();

                                return
                                    (int) $product->cartItems()->sum('quantity')
                                    + (int) $product->variants()->withSum('cartItems', 'quantity')->get()->sum('cart_items_sum_quantity');
                            }),
                        TextEntry::make('remaining_after_carts')
                            ->label('المتبقي بعد خصم السلال (تقديري)')
                            ->state(function (): int {
                                $product = $this->getRecord();

                                $inCart =
                                    (int) $product->cartItems()->sum('quantity')
                                    + (int) $product->variants()->withSum('cartItems', 'quantity')->get()->sum('cart_items_sum_quantity');

                                return max((int) $product->stock - $inCart, 0);
                            }),
                        TextEntry::make('variants_stock_total')
                            ->label('إجمالي مخزون المنتجات الفرعية')
                            ->state(fn (): int => (int) $this->getRecord()->variants()->sum('stock')),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('الخصائص والقيم المتاحة')
                    ->schema([
                        TextEntry::make('attributes_values_summary')
                            ->label('الخصائص والقيم')
                            ->state(function (): array {
                                $product = $this->getRecord();

                                $variantValues = $product->variants()
                                    ->with('attributeValues.attribute')
                                    ->get()
                                    ->flatMap(fn ($variant) => $variant->attributeValues);

                                $directValues = $product->attributeValues()
                                    ->with('attribute')
                                    ->get();

                                $grouped = $directValues
                                    ->concat($variantValues)
                                    ->filter(fn ($value): bool => is_object($value))
                                    ->groupBy(fn ($attributeValue) => $attributeValue->attribute?->name_ar ?? 'غير محدد');

                                return $grouped
                                    ->map(function ($values, $attributeName): string {
                                        $availableValues = collect($values)
                                            ->map(function ($value): ?string {
                                                $valueAr = data_get($value, 'value_ar');
                                                $valueEn = data_get($value, 'value_en');

                                                return filled($valueAr) ? $valueAr : (filled($valueEn) ? $valueEn : null);
                                            })
                                            ->filter()
                                            ->unique()
                                            ->values()
                                            ->implode('، ');

                                        return $attributeName.': '.($availableValues !== '' ? $availableValues : '-');
                                    })
                                    ->values()
                                    ->all();
                            })
                            ->listWithLineBreaks()
                            ->placeholder('لا توجد خصائص')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Tabs::make('التعليقات والوسائط')
                    ->tabs([
                        Tab::make('التعليقات')
                            ->badge(fn (): int => (int) $this->getRecord()->comments()->count())
                            ->schema([
                                RepeatableEntry::make('comments')
                                    ->label('تعليقات المنتج')
                                    ->placeholder('لا توجد تعليقات')
                                    ->schema([
                                        TextEntry::make('user.name')->label('المستخدم')->placeholder('-'),
                                        TextEntry::make('body')
                                            ->label('التعليق')
                                            ->placeholder('-')
                                            ->columnSpanFull()
                                            ->suffixAction(
                                                Actions\Action::make('deleteProductComment')
                                                    ->icon('heroicon-o-x-mark')
                                                    ->tooltip('حذف التعليق')
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->action(function (?Comment $record): void {
                                                        if (! $record) {
                                                            return;
                                                        }

                                                        $this->deleteComment($record->id);
                                                    })
                                            ),
                                        TextEntry::make('comment_reports_count')
                                            ->label('بلاغات على التعليق')
                                            ->state(fn (?Comment $record): int => (int) ($record?->reports()->count() ?? 0))
                                            ->badge()
                                            ->color(fn (?Comment $record): string => (int) ($record?->reports()->count() ?? 0) > 0 ? 'danger' : 'success'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('الوسائط')
                            ->badge(fn (): int => (int) $this->getRecord()->media()->count())
                            ->schema([
                                RepeatableEntry::make('media')
                                    ->label('وسائط المنتج')
                                    ->placeholder('لا توجد وسائط')
                                    ->schema([
                                        TextEntry::make('file_name')->label('اسم الملف')->placeholder('-'),
                                        TextEntry::make('file_type')->label('النوع')->badge()->placeholder('-'),
                                        ImageEntry::make('url')
                                            ->label('معاينة الصورة')
                                            ->visible(fn ($record): bool => $record?->file_type === 'image'),
                                        TextEntry::make('video_preview')
                                            ->label('معاينة الفيديو')
                                            ->state(fn ($record): ?string => filled($record?->url)
                                                ? '<video src="'.$record->url.'" controls preload="metadata" width="180" style="max-height:120px;border-radius:8px;"></video>'
                                                : null)
                                            ->html()
                                            ->visible(fn ($record): bool => $record?->file_type === 'video')
                                            ->placeholder(''),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
                Tabs::make('التفاصيل المرتبطة')
                    ->tabs([
                        Tab::make('المنتجات الفرعية')
                            ->badge(fn (): int => (int) $this->getRecord()->variants()->count())
                            ->schema([
                                RepeatableEntry::make('variants')
                                    ->label('المنتجات الفرعية')
                                    ->placeholder('لا توجد منتجات فرعية')
                                    ->schema([
                                        TextEntry::make('sku')->label('SKU')->placeholder('-'),
                                        TextEntry::make('price')->label('السعر')->money('SAR')->placeholder('-'),
                                        TextEntry::make('final_price')
                                            ->label('السعر بعد العرض')
                                            ->state(fn ($record): string => app(PriceService::class)
                                                ->resolveFinalPriceForVariant($this->getRecord(), $record))
                                            ->money('SAR')
                                            ->placeholder('-'),
                                        TextEntry::make('stock')->label('المخزون')->placeholder('-'),
                                        TextEntry::make('cart_items_count')
                                            ->label('عدد الإضافات للسلة')
                                            ->state(fn ($record): int => (int) $record->cartItems()->count()),
                                        TextEntry::make('orders_count')
                                            ->label('عدد الطلبات')
                                            ->state(fn ($record): int => (int) $record->orders()->count()),
                                        TextEntry::make('attribute_values')
                                            ->label('الخصائص')
                                            ->state(fn ($record): string => $record->attributeValues
                                                ->map(fn ($attributeValue) => ($attributeValue->attribute?->name_ar ?? 'غير محدد').': '.($attributeValue->value_ar ?: $attributeValue->value_en ?: '-'))
                                                ->implode(' | '))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(4),
                            ]),
                        Tab::make('العروض')
                            ->badge(fn (): int => count($this->resolveAppliedPromotions()))
                            ->schema([
                                RepeatableEntry::make('applied_promotions')
                                    ->label('العروض المرتبطة')
                                    ->state(fn (): array => $this->resolveAppliedPromotions())
                                    ->placeholder('لا توجد عروض')
                                    ->schema([
                                        TextEntry::make('title')->label('العرض')->placeholder('-'),
                                        TextEntry::make('level')->label('مستوى العرض')->badge()->placeholder('-'),
                                        TextEntry::make('applied_via')->label('يخضع عبر')->placeholder('-'),
                                        TextEntry::make('discount_type')->label('نوع الخصم')->placeholder('-'),
                                        TextEntry::make('discount_value')->label('قيمة الخصم')->placeholder('-'),
                                        TextEntry::make('active_state')
                                            ->label('نشط؟')
                                            ->badge()
                                            ->color(fn (string $state): string => $state === 'نشط' ? 'success' : 'danger')
                                            ->placeholder('-'),
                                        TextEntry::make('starts_at')->label('بداية العرض')->since()->placeholder('-'),
                                        TextEntry::make('ends_at')->label('نهاية العرض')->since()->placeholder('-'),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('الطلبات')
                            ->badge(fn (): int =>
                                (int) $this->getRecord()->orders()->count()
                                + (int) $this->getRecord()->variants()->withCount('orders')->get()->sum('orders_count')
                            )
                            ->schema([
                                RepeatableEntry::make('orders')
                                    ->label('طلبات المنتج المباشرة')
                                    ->placeholder('لا توجد طلبات مباشرة')
                                    ->schema([
                                        TextEntry::make('order_number')->label('رقم الطلب')->placeholder('-'),
                                        TextEntry::make('user.name')->label('العميل')->placeholder('-'),
                                        TextEntry::make('quantity')->label('الكمية')->placeholder('-'),
                                        TextEntry::make('total_price')->label('الإجمالي')->money('SAR')->placeholder('-'),
                                        TextEntry::make('status')->label('الحالة')->badge()->placeholder('-'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('سجل تغيّر المخزون')
                            ->badge(fn (): int => (int) $this->getRecord()->orders()->count())
                            ->schema([
                                RepeatableEntry::make('stock_movements')
                                    ->label('آخر الحركات المؤثرة على المخزون')
                                    ->state(function (): array {
                                        $product = $this->getRecord();

                                        $directOrders = $product->orders()
                                            ->with('user')
                                            ->latest('created_at')
                                            ->limit(20)
                                            ->get()
                                            ->map(fn ($order): array => [
                                                'order_number' => $order->order_number,
                                                'source' => 'طلب مباشر',
                                                'customer' => $order->user?->name,
                                                'quantity' => (int) $order->quantity,
                                                'created_at' => $order->created_at,
                                            ]);

                                        $variantOrders = $product->variants()
                                            ->with(['orders.user'])
                                            ->get()
                                            ->flatMap(fn ($variant) => $variant->orders->map(fn ($order): array => [
                                                'order_number' => $order->order_number,
                                                'source' => 'طلب على منتج فرعي ('.$variant->sku.')',
                                                'customer' => $order->user?->name,
                                                'quantity' => (int) $order->quantity,
                                                'created_at' => $order->created_at,
                                            ]));

                                        return $directOrders
                                            ->concat($variantOrders)
                                            ->sortByDesc('created_at')
                                            ->take(20)
                                            ->values()
                                            ->all();
                                    })
                                    ->placeholder('لا توجد حركات مخزون حتى الآن')
                                    ->schema([
                                        TextEntry::make('order_number')->label('رقم الطلب')->placeholder('-'),
                                        TextEntry::make('source')->label('المصدر')->placeholder('-'),
                                        TextEntry::make('customer')->label('العميل')->placeholder('-'),
                                        TextEntry::make('quantity')
                                            ->label('الكمية المسحوبة')
                                            ->state(fn ($record): string => '-'.((int) ($record['quantity'] ?? 0))),
                                        TextEntry::make('created_at')
                                            ->label('التاريخ')
                                            ->since()
                                            ->placeholder('-'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function deleteComment(int|string $commentId): void
    {
        $this->getRecord()->comments()->whereKey($commentId)->delete();
    }

    private function resolveAppliedPromotions(): array
    {
        if ($this->appliedPromotionsCache !== null) {
            return $this->appliedPromotionsCache;
        }

        /** @var Product $product */
        $product = $this->getRecord()->loadMissing('subcategory:id,category_id');

        $storeId = (int) $product->store_id;
        $productId = (int) $product->id;
        $subcategoryId = $product->subcategory_id ? (int) $product->subcategory_id : null;
        $categoryId = $product->subcategory?->category_id ? (int) $product->subcategory->category_id : null;

        $items = PromotionItem::query()
            ->with('promotion')
            ->approved()
            ->whereHas('promotion', function ($query) use ($storeId): void {
                $query
                    ->whereIn('level', ['app', 'store'])
                    ->where(function ($levelQuery) use ($storeId): void {
                        $levelQuery
                            ->where('level', 'app')
                            ->orWhere(function ($storeLevelQuery) use ($storeId): void {
                                $storeLevelQuery
                                    ->where('level', 'store')
                                    ->where('store_id', $storeId);
                            });
                    });
            })
            ->where(function ($query) use ($productId, $subcategoryId, $categoryId, $storeId): void {
                $query
                    ->where(function ($directProduct) use ($productId, $storeId): void {
                        $directProduct
                            ->where('promotable_type', Product::class)
                            ->where('promotable_id', $productId)
                            ->where(function ($storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    })
                    ->orWhere(function ($storeScope) use ($storeId): void {
                        $storeScope
                            ->where('promotable_type', Store::class)
                            ->where('promotable_id', $storeId)
                            ->where(function ($storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });

                if ($subcategoryId !== null) {
                    $query->orWhere(function ($subcategoryScope) use ($subcategoryId, $storeId): void {
                        $subcategoryScope
                            ->where('promotable_type', Subcategory::class)
                            ->where('promotable_id', $subcategoryId)
                            ->where(function ($storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });
                }

                if ($categoryId !== null) {
                    $query->orWhere(function ($categoryScope) use ($categoryId, $storeId): void {
                        $categoryScope
                            ->where('promotable_type', Category::class)
                            ->where('promotable_id', $categoryId)
                            ->where(function ($storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });
                }
            })
            ->get();

        $this->appliedPromotionsCache = $items
            ->groupBy('promotion_id')
            ->map(function (Collection $promotionItems): array {
                $promotion = $promotionItems->first()?->promotion;

                return [
                    'title' => $promotion?->title ?? '-',
                    'level' => ($promotion?->level ?? null) === 'store' ? 'متجر' : 'تطبيق',
                    'applied_via' => $promotionItems
                        ->map(fn (PromotionItem $item): string => $this->mapPromotableType($item->promotable_type))
                        ->unique()
                        ->values()
                        ->implode(' | '),
                    'discount_type' => match ($promotion?->discount_type) {
                        'percentage' => 'نسبة مئوية',
                        'fixed' => 'قيمة ثابتة',
                        default => '-',
                    },
                    'discount_value' => $promotion ? (string) $promotion->discount_value : '-',
                    'active_state' => $promotion?->isEffectivelyActive() ? 'نشط' : 'غير نشط',
                    'starts_at' => $promotion?->starts_at,
                    'ends_at' => $promotion?->ends_at,
                ];
            })
            ->values()
            ->all();

        return $this->appliedPromotionsCache;
    }

    private function mapPromotableType(?string $type): string
    {
        return match ($type) {
            Product::class => 'منتج',
            Category::class => 'قسم رئيسي',
            Subcategory::class => 'قسم فرعي',
            Store::class => 'متجر',
            default => '-'
        };
    }
}
