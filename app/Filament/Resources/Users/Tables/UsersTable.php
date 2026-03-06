<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use App\Models\User;
use App\Filament\Resources\Users\UserResource;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('phone')
                    ->label('هاتف العميل')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('cart_items_count')
                    ->label('منتجات السلة')
                    ->state(fn (User $record): int => (int) $record->cartItems()->count())
                    ->sortable(query: fn (Builder $query, string $direction): Builder =>
                        $query->withCount('cartItems')->orderBy('cart_items_count', $direction)),
                TextColumn::make('orders_count')
                    ->label('عدد الطلبات')
                    ->state(fn (User $record): int => (int) $record->orders()->count())
                    ->sortable(query: fn (Builder $query, string $direction): Builder =>
                        $query->withCount('orders')->orderBy('orders_count', $direction)),
                TextColumn::make('created_at')
                    ->label('تاريخ الانضمام')
                    ->dateTime('Y-m-d h:i A')
                    ->sortable(),
                BooleanColumn::make('is_active')
                    ->label('نشط'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('حالة الحساب')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
                TernaryFilter::make('has_cart_items')
                    ->label('السلة')
                    ->trueLabel('لديه عناصر')
                    ->falseLabel('بدون عناصر')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('cartItems'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('cartItems'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_orders')
                    ->label('الطلبات')
                    ->trueLabel('لديه طلبات')
                    ->falseLabel('بدون طلبات')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('orders'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('orders'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->actions([])
            ->bulkActions([]);

        }
}
