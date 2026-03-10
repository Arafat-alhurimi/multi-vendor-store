<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Pages\ViewPromotion;
use App\Filament\Resources\Promotions\RelationManagers\PromotionItemsRelationManager;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static ?string $modelLabel = 'عرض تطبيق';

    protected static ?string $pluralModelLabel = 'عروض التطبيق';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'عروض التطبيق';

    protected static string | \UnitEnum | null $navigationGroup = 'العروض';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->label('عنوان العرض')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('صورة العرض')
                    ->image()
                    ->disk('s3')
                    ->directory('promotions')
                    ->visibility('public')
                    ->imagePreviewHeight(140),

                Hidden::make('level')
                    ->default('app'),

                Select::make('discount_type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed',
                    ])
                    ->required(),

                TextInput::make('discount_value')
                    ->label('قيمة الخصم')
                    ->numeric()
                    ->required(),

                DateTimePicker::make('starts_at')
                    ->label('تاريخ البدء')
                    ->timezone(config('app.timezone'))
                    ->required(),

                DateTimePicker::make('ends_at')
                    ->label('تاريخ الانتهاء')
                    ->timezone(config('app.timezone'))
                    ->required()
                    ->after('starts_at'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('s3')
                    ->circular()
                    ->defaultImageUrl(null),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),

                TextColumn::make('stores_in_offer_count')
                    ->label('عدد المتاجر في العرض')
                    ->state(fn (Promotion $record): int => static::resolvePromotionTargetStoreIds($record)->count()),

                TextColumn::make('products_in_offer_count')
                    ->label('عدد المنتجات بالكامل في العرض')
                    ->state(fn (Promotion $record): int => static::resolvePromotionTargetProductIds($record)->count()),

                TextColumn::make('discount_type')
                    ->label('نوع الخصم'),

                TextColumn::make('discount_value')
                    ->label('القيمة')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                BadgeColumn::make('pending_join_requests_count')
                    ->label('طلبات الانضمام')
                    ->state(fn (Promotion $record): int => (int) ($record->pending_join_requests_count ?? 0))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? 'يوجد طلبات ('.$state.')' : 'لا يوجد')
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
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
                TernaryFilter::make('has_items')
                    ->label('له عناصر مستهدفة')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('items'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('items'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_store_scope')
                    ->label('مرتبط بمتجر')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('store_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('store_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_pending_join_requests')
                    ->label('طلبات انضمام')
                    ->trueLabel('يوجد')
                    ->falseLabel('لا يوجد')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('items', fn (Builder $itemsQuery): Builder => $itemsQuery->where('status', 'pending')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('items', fn (Builder $itemsQuery): Builder => $itemsQuery->where('status', 'pending')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('starts_between')
                    ->label('تاريخ البدء')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('starts_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('starts_at', '<=', $date));
                    }),
                Filter::make('ends_between')
                    ->label('تاريخ الانتهاء')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('ends_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('ends_at', '<=', $date));
                    }),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PromotionItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'view' => ViewPromotion::route('/{record}'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('level', 'app')
            ->withCount([
                'items as pending_join_requests_count' => fn (Builder $query): Builder => $query->where('status', 'pending'),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Promotion::query()
            ->where('level', 'app')
            ->whereHas('items', fn (Builder $query): Builder => $query->where('status', 'pending'))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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

    private static function resolvePromotionTargetStoreIds(Promotion $promotion): Collection
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
            ->whereIn('id', static::resolvePromotionTargetProductIds($promotion)->all())
            ->pluck('store_id');

        return $directStoreIds
            ->merge($explicitStoreIds)
            ->merge($storeIdsFromProducts)
            ->filter()
            ->unique()
            ->values();
    }
}
