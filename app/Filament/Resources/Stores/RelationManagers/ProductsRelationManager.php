<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Subcategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'منتجات المتجر';

    public string $activeMainCategory = 'all';

    private array $activePromotionCache = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->relationship('subcategory', 'name_ar')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required(),
                TextInput::make('name_en')
                    ->label('الاسم (EN)')
                    ->required(),
                Textarea::make('description_ar')
                    ->label('الوصف (عربي)'),
                Textarea::make('description_en')
                    ->label('الوصف (EN)'),
                TextInput::make('base_price')
                    ->label('السعر الأساسي')
                    ->numeric()
                    ->required(),
                TextInput::make('stock')
                    ->label('المخزون')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_featured')
                    ->label('مميز؟')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->modifyQueryUsing(function (Builder $query): Builder {
                if ($this->activeMainCategory === 'all') {
                    return $query;
                }

                return $query->whereHas('subcategory', fn (Builder $subQuery): Builder => $subQuery->where('category_id', (int) $this->activeMainCategory));
            })
            ->recordUrl(fn ($record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('section_path')
                    ->label('القسم')
                    ->state(fn (Product $record): string => collect([
                        $record->subcategory?->category?->name_ar,
                        $record->subcategory?->name_ar,
                    ])->filter()->implode('>') ?: '-')
                    ->badge()
                    ->color('info')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner
                                ->whereHas('subcategory', fn (Builder $sub): Builder => $sub->where('name_ar', 'like', "%{$search}%"))
                                ->orWhereHas('subcategory.category', fn (Builder $cat): Builder => $cat->where('name_ar', 'like', "%{$search}%"));
                        });
                    })
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('المخزون'),
                Tables\Columns\TextColumn::make('offer_name')
                    ->label('اسم العرض')
                    ->state(fn (Product $record): ?string => $this->resolveActivePromotionForProduct($record)?->title)
                    ->badge()
                    ->color('success')
                    ->placeholder(''),
                Tables\Columns\IconColumn::make('is_under_offer')
                    ->label('ضمن عرض؟')
                    ->boolean()
                    ->state(fn (Product $record): bool => $this->resolveActivePromotionForProduct($record) !== null),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('السعر بعد العرض')
                    ->state(function (Product $record): ?string {
                        $promotion = $this->resolveActivePromotionForProduct($record);

                        if (! $promotion) {
                            return null;
                        }

                        $basePrice = (float) $record->base_price;
                        $discountValue = (float) $promotion->discount_value;

                        $final = $promotion->discount_type === 'percentage'
                            ? $basePrice - (($basePrice * $discountValue) / 100)
                            : $basePrice - $discountValue;

                        return number_format(max($final, 0), 2, '.', '');
                    })
                    ->money('SAR')
                    ->placeholder(''),
                Tables\Columns\TextColumn::make('cart_total_count')
                    ->label('إضافات السلة')
                    ->state(fn (Product $record): int =>
                        (int) $record->cartItems()->count()
                        + (int) $record->variants()->withCount('cartItems')->get()->sum('cart_items_count')
                    ),
                Tables\Columns\TextColumn::make('orders_total_count')
                    ->label('الطلبات')
                    ->state(fn (Product $record): int =>
                        (int) $record->orders()->count()
                        + (int) $record->variants()->withCount('orders')->get()->sum('orders_count')
                    ),
                Tables\Columns\TextColumn::make('avg_rating')
                    ->label('متوسط التقييم')
                    ->state(function (Product $record): ?string {
                        $avgRating = $record->ratings()->avg('value');

                        return $avgRating === null ? null : number_format((float) $avgRating, 2);
                    })
                    ->placeholder(''),
                Tables\Columns\TextColumn::make('favorites_count')
                    ->label('عدد المفضلة')
                    ->state(fn (Product $record): int => (int) $record->favorites()->count()),
            ])
            ->filters([
                TernaryFilter::make('is_under_offer')
                    ->label('هل يخضع لعرض؟')
                    ->trueLabel('ضمن عرض')
                    ->falseLabel('بدون عرض')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('promotionItems'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('promotionItems'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('in_cart')
                    ->label('مضاف للسلة')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereHas('cartItems')->orWhereHas('variants.cartItems');
                        }),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('cartItems')->whereDoesntHave('variants.cartItems'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_orders')
                    ->label('له طلبات')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereHas('orders')->orWhereHas('variants.orders');
                        }),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('orders')->whereDoesntHave('variants.orders'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_favorites')
                    ->label('له إضافات مفضلة')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('favorites'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('favorites'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_ratings')
                    ->label('له تقييمات')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('ratings'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('ratings'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->options(fn (): array => Subcategory::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray()),
            ])
            ->headerActions($this->getMainCategoryQuickActions())
            ->actions([
                Action::make('viewProduct')
                    ->label('عرض المنتج')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => ProductResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }

    private function getMainCategoryQuickActions(): array
    {
        $actions = [
            Action::make('main_category_all')
                ->label('الكل')
                ->color($this->activeMainCategory === 'all' ? 'primary' : 'gray')
                ->action(function (): void {
                    $this->activeMainCategory = 'all';
                }),
        ];

        $categories = $this->getOwnerRecord()
            ->categories()
            ->orderBy('categories.name_ar')
            ->get(['categories.id', 'categories.name_ar']);

        $categoryActions = $categories
            ->map(function ($category): Action {
                $categoryId = (string) $category->id;

                return Action::make('main_category_'.$categoryId)
                    ->label((string) $category->name_ar)
                    ->color($this->activeMainCategory === $categoryId ? 'primary' : 'gray')
                    ->action(function () use ($categoryId): void {
                        $this->activeMainCategory = $categoryId;
                    });
            })
            ->all();

        return array_merge($actions, $categoryActions);
    }

    private function resolveActivePromotionForProduct(Product $product): ?Promotion
    {
        $cacheKey = (int) $product->id;

        if (array_key_exists($cacheKey, $this->activePromotionCache)) {
            return $this->activePromotionCache[$cacheKey];
        }

        $product->loadMissing('subcategory:id,category_id');

        $storeId = (int) $product->store_id;
        $productId = (int) $product->id;
        $subcategoryId = $product->subcategory_id ? (int) $product->subcategory_id : null;
        $categoryId = $product->subcategory?->category_id ? (int) $product->subcategory->category_id : null;

        $item = PromotionItem::query()
            ->with('promotion')
            ->approved()
            ->whereHas('promotion', function (Builder $query) use ($storeId): void {
                $query
                    ->currentlyActive(now())
                    ->whereIn('level', ['app', 'store'])
                    ->where(function (Builder $levelQuery) use ($storeId): void {
                        $levelQuery
                            ->where('level', 'app')
                            ->orWhere(function (Builder $storeLevelQuery) use ($storeId): void {
                                $storeLevelQuery
                                    ->where('level', 'store')
                                    ->where('store_id', $storeId);
                            });
                    });
            })
            ->where(function (Builder $query) use ($productId, $subcategoryId, $categoryId, $storeId): void {
                $query
                    ->where(function (Builder $directProduct) use ($productId, $storeId): void {
                        $directProduct
                            ->where('promotable_type', Product::class)
                            ->where('promotable_id', $productId)
                            ->where(function (Builder $storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    })
                    ->orWhere(function (Builder $storeScope) use ($storeId): void {
                        $storeScope
                            ->where('promotable_type', \App\Models\Store::class)
                            ->where('promotable_id', $storeId)
                            ->where(function (Builder $storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });

                if ($subcategoryId !== null) {
                    $query->orWhere(function (Builder $subcategoryScope) use ($subcategoryId, $storeId): void {
                        $subcategoryScope
                            ->where('promotable_type', Subcategory::class)
                            ->where('promotable_id', $subcategoryId)
                            ->where(function (Builder $storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });
                }

                if ($categoryId !== null) {
                    $query->orWhere(function (Builder $categoryScope) use ($categoryId, $storeId): void {
                        $categoryScope
                            ->where('promotable_type', \App\Models\Category::class)
                            ->where('promotable_id', $categoryId)
                            ->where(function (Builder $storeContext) use ($storeId): void {
                                $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    });
                }
            })
            ->orderBy('created_at')
            ->first();

        return $this->activePromotionCache[$cacheKey] = $item?->promotion;
    }
}
