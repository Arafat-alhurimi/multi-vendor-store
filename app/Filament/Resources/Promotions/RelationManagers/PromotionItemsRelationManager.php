<?php

namespace App\Filament\Resources\Promotions\RelationManagers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class PromotionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'طلبات الانضمام';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('promotable_type')
                    ->label('نوع العنصر')
                    ->required()
                    ->options([
                        Product::class => 'Product',
                        Category::class => 'Category',
                        Subcategory::class => 'Subcategory',
                        Store::class => 'Store',
                    ]),

                Select::make('store_id')
                    ->label('المتجر المرتبط')
                    ->relationship('store', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('promotable_id')
                    ->label('معرّف العنصر')
                    ->required()
                    ->numeric(),

                Select::make('status')
                    ->label('الحالة')
                    ->required()
                    ->default('pending')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['promotable', 'store']))
            ->columns([
                Tables\Columns\TextColumn::make('promotable_type')
                    ->label('النوع')
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            Product::class => 'Product',
                            Category::class => 'Category',
                            Subcategory::class => 'Subcategory',
                            Store::class => 'Store',
                            default => class_basename($state),
                        };
                    }),

                Tables\Columns\TextColumn::make('promotable_id')
                    ->label('المعرّف'),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('المتجر المرتبط')
                    ->default('-'),

                Tables\Columns\TextColumn::make('promotable_name')
                    ->label('الاسم')
                    ->state(fn (PromotionItem $record): string => $this->resolvePromotableName($record)),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionItem $record): bool => $record->status !== 'approved')
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
                    ->label('Reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PromotionItem $record): bool => $record->status !== 'rejected')
                    ->action(fn (PromotionItem $record) => $record->update(['status' => 'rejected'])),
            ]);
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
}
