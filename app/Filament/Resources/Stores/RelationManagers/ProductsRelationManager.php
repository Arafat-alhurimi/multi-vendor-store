<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\Category;
use App\Models\Product;
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
            ->recordUrl(fn ($record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subcategory.category.name_ar')
                    ->label('الفئة الرئيسية')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('subcategory.name_ar')
                    ->label('القسم الفرعي')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('السعر بعد العرض')
                    ->state(fn (Product $record): string => $record->final_price)
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('المخزون'),
                Tables\Columns\IconColumn::make('is_under_offer')
                    ->label('ضمن عرض؟')
                    ->boolean()
                    ->state(fn (Product $record): bool => (float) $record->final_price < (float) $record->base_price),
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
                SelectFilter::make('category')
                    ->label('الفئة الرئيسية')
                    ->options(fn (): array => Category::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas('subcategory', fn (Builder $subQuery): Builder => $subQuery->where('category_id', $value));
                    }),
                SelectFilter::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->options(fn (): array => Subcategory::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray()),
            ])
            ->headerActions([])
            ->actions([
                Action::make('viewProduct')
                    ->label('عرض المنتج')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => ProductResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
