<?php

namespace App\Filament\Resources\Subcategories\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubcategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name_ar')
                    ->label('الفئة الرئيسية')
                    ->searchable(),
                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                TextColumn::make('name_en')
                    ->label('الاسم (EN)')
                    ->searchable(),
                ImageColumn::make('image')
                    ->disk('s3')
                    ->label('صورة')
                    ->circular()
                    ->imageHeight(60),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
                TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    ->counts('products')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشطة')
                    ->falseLabel('غير نشطة')
                    ->placeholder('الكل'),
                TernaryFilter::make('has_products')
                    ->label('المنتجات')
                    ->trueLabel('لديها منتجات')
                    ->falseLabel('بدون منتجات')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('products'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('products'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_image')
                    ->label('الصورة')
                    ->trueLabel('لديها صورة')
                    ->falseLabel('بدون صورة')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('image')->where('image', '!=', ''),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereNull('image')->orWhere('image', '');
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_description')
                    ->label('الوصف')
                    ->trueLabel('لديها وصف')
                    ->falseLabel('بدون وصف')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner
                                ->whereNotNull('description_ar')->where('description_ar', '!=', '')
                                ->orWhereNotNull('description_en')->where('description_en', '!=', '');
                        }),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->where(function (Builder $x): void {
                                $x->whereNull('description_ar')->orWhere('description_ar', '');
                            })->where(function (Builder $x): void {
                                $x->whereNull('description_en')->orWhere('description_en', '');
                            });
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('category_id')
                    ->label('الفئة الرئيسية')
                    ->searchable()
                    ->options(fn (): array => Category::query()
                        ->orderBy('name_ar')
                        ->pluck('name_ar', 'id')
                        ->toArray()),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
