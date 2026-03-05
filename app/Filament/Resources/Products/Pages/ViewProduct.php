<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

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
                        TextEntry::make('subcategory.category.name_ar')->label('الفئة الرئيسية')->placeholder('-'),
                        TextEntry::make('subcategory.name_ar')->label('القسم الفرعي')->placeholder('-'),
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
                Tabs::make('التفاصيل المرتبطة')
                    ->tabs([
                        Tab::make('التعليقات')
                            ->badge(fn (): int => (int) $this->getRecord()->comments()->count())
                            ->schema([
                                RepeatableEntry::make('comments')
                                    ->label('تعليقات المنتج')
                                    ->placeholder('لا توجد تعليقات')
                                    ->schema([
                                        TextEntry::make('user.name')->label('المستخدم')->placeholder('-'),
                                        TextEntry::make('body')->label('التعليق')->placeholder('-')->columnSpanFull(),
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
                        Tab::make('المنتجات الفرعية')
                            ->badge(fn (): int => (int) $this->getRecord()->variants()->count())
                            ->schema([
                                RepeatableEntry::make('variants')
                                    ->label('المنتجات الفرعية')
                                    ->placeholder('لا توجد منتجات فرعية')
                                    ->schema([
                                        TextEntry::make('sku')->label('SKU')->placeholder('-'),
                                        TextEntry::make('price')->label('السعر')->money('SAR')->placeholder('-'),
                                        TextEntry::make('stock')->label('المخزون')->placeholder('-'),
                                        TextEntry::make('attribute_values')
                                            ->label('الخصائص')
                                            ->state(fn ($record): string => $record->attributeValues
                                                ->map(fn ($attributeValue) => ($attributeValue->attribute?->name_ar ?? 'غير محدد').': '.($attributeValue->value_ar ?: $attributeValue->value_en ?: '-'))
                                                ->implode(' | '))
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3),
                            ]),
                        Tab::make('العروض')
                            ->badge(fn (): int => (int) $this->getRecord()->promotionItems()->count())
                            ->schema([
                                RepeatableEntry::make('promotionItems')
                                    ->label('العروض المرتبطة')
                                    ->placeholder('لا توجد عروض')
                                    ->schema([
                                        TextEntry::make('promotion.title')->label('العرض')->placeholder('-'),
                                        TextEntry::make('promotion.discount_type')->label('نوع الخصم')->placeholder('-'),
                                        TextEntry::make('promotion.discount_value')->label('قيمة الخصم')->placeholder('-'),
                                        TextEntry::make('status')->label('الحالة')->badge()->placeholder('-'),
                                        TextEntry::make('promotion.starts_at')->label('بداية العرض')->since()->placeholder('-'),
                                        TextEntry::make('promotion.ends_at')->label('نهاية العرض')->since()->placeholder('-'),
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
}
